<?php

namespace WPMDB\Anonymization;

/**
 * Deterministic (keyed) anonymization.
 *
 * When a secret is configured, the fake value generated for an original value
 * is a pure function of (secret, fake data type and arguments, normalised
 * original value). The same real email address therefore becomes the same fake
 * email address in every table, row and run, which keeps joins and distinct
 * counts intact on an anonymized database.
 *
 * The Faker generator is seeded from an HMAC-SHA256 of the original value keyed
 * by the secret. Values that need to stay unique (email addresses and user
 * names) also get a fragment of that HMAC embedded, so two different originals
 * can't produce the same fake value.
 *
 * Without a secret, the same uniqueness-sensitive types get a random fragment
 * in the same format instead (see generate_random_unique()), so fake email
 * addresses and user names don't collide in random mode either.
 */
class Deterministic {

	/**
	 * Bump if the derivation changes, so old and new outputs never silently mix.
	 */
	const VERSION = 'v1';

	/**
	 * Length (in hex characters) of the HMAC or random fragment embedded in
	 * unique values. 12 hex characters = 48 bits.
	 */
	const FRAGMENT_LENGTH = 12;

	/**
	 * Reserved domain (RFC 2606) used for deterministic fake email addresses.
	 */
	const EMAIL_DOMAIN = 'example.com';

	/**
	 * @var string
	 */
	protected $secret;

	/**
	 * Faker formatters that produce email addresses. They share one canonical
	 * key so the same original maps to the same fake whichever is configured.
	 *
	 * @var array
	 */
	protected $email_types = array( 'email', 'safeEmail', 'freeEmail', 'companyEmail' );

	/**
	 * Faker formatters that produce user names, which must stay unique.
	 *
	 * @var array
	 */
	protected $username_types = array( 'userName' );

	/**
	 * Faker formatters that are never generated deterministically.
	 *
	 * @var array
	 */
	protected $random_types = array( 'password' );

	/**
	 * @param string $secret
	 */
	public function __construct( $secret ) {
		$this->secret = is_string( $secret ) ? $secret : '';
	}

	/**
	 * Build an instance from the environment (constant, filter) with an optional
	 * explicit override, e.g. from the WP-CLI --secret option.
	 *
	 * @param string|null $override
	 *
	 * @return Deterministic
	 */
	public static function from_environment( $override = null ) {
		return new static( static::resolve_secret( $override ) );
	}

	/**
	 * Resolve the secret. Precedence: explicit override, then the
	 * `wpmdb_anonymization_secret` filter, which receives the value of the
	 * `WPMDB_ANONYMIZATION_SECRET` constant (or an empty string).
	 *
	 * @param string|null $override
	 *
	 * @return string
	 */
	public static function resolve_secret( $override = null ) {
		if ( is_string( $override ) && '' !== $override ) {
			return $override;
		}

		$secret = defined( 'WPMDB_ANONYMIZATION_SECRET' ) ? (string) WPMDB_ANONYMIZATION_SECRET : '';

		if ( function_exists( 'apply_filters' ) ) {
			$secret = apply_filters( 'wpmdb_anonymization_secret', $secret );
		}

		return is_string( $secret ) ? $secret : '';
	}

	/**
	 * @return bool
	 */
	public function is_enabled() {
		return '' !== $this->secret;
	}

	/**
	 * Should this type/original pair be generated deterministically?
	 *
	 * @param string $fake_data_type
	 * @param mixed  $original
	 *
	 * @return bool
	 */
	public function applies( $fake_data_type, $original ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		if ( null === $original || ! is_scalar( $original ) ) {
			return false;
		}

		return ! in_array( $fake_data_type, $this->random_types, true );
	}

	/**
	 * Generate the fake value for an original value.
	 *
	 * @param \Faker\Generator $faker
	 * @param string           $fake_data_type
	 * @param array            $args
	 * @param mixed            $original
	 *
	 * @return mixed
	 */
	public function generate( $faker, $fake_data_type, array $args, $original ) {
		$hash = $this->hash( $fake_data_type, $args, $original );

		$faker->seed( $this->seed_from_hash( $hash ) );

		try {
			if ( $this->is_unique_type( $fake_data_type ) ) {
				$value = $this->unique_value( $fake_data_type, $faker->userName(), substr( $hash, 32, self::FRAGMENT_LENGTH ) );
			} else {
				$value = call_user_func_array( array( $faker, $fake_data_type ), $args );
			}
		} finally {
			// Re-seed randomly so later non-deterministic calls aren't predictable.
			$faker->seed();
		}

		return $value;
	}

	/**
	 * Generate a random but unique value for a uniqueness-sensitive type
	 * (email addresses and user names) when no secret is configured.
	 *
	 * Faker's own output repeats on large data sets, which breaks unique
	 * columns (for example a plugin table with a UNIQUE email column). WP
	 * Migrate also boots a fresh Faker for every request, so Faker's unique()
	 * modifier can't help. Instead a random fragment from random_bytes() is
	 * embedded, in the same format as deterministic mode, so collisions are
	 * as unlikely as there and the output looks the same in both modes.
	 *
	 * @param \Faker\Generator $faker
	 * @param string           $fake_data_type
	 *
	 * @return string
	 */
	public function generate_random_unique( $faker, $fake_data_type ) {
		$fragment = bin2hex( random_bytes( (int) ( self::FRAGMENT_LENGTH / 2 ) ) );

		return $this->unique_value( $fake_data_type, $faker->userName(), $fragment );
	}

	/**
	 * Is this a type whose fake values must not collide (email addresses and
	 * user names)?
	 *
	 * @param string $fake_data_type
	 *
	 * @return bool
	 */
	public function is_unique_type( $fake_data_type ) {
		return $this->is_email_type( $fake_data_type ) || $this->is_username_type( $fake_data_type );
	}

	/**
	 * HMAC-SHA256 (hex) of the canonical type key and the normalised original.
	 *
	 * @param string $fake_data_type
	 * @param array  $args
	 * @param mixed  $original
	 *
	 * @return string
	 */
	public function hash( $fake_data_type, array $args, $original ) {
		$message = implode(
			"\x1f",
			array(
				self::VERSION,
				$this->type_key( $fake_data_type, $args ),
				$this->normalise( $fake_data_type, $original ),
			)
		);

		return hash_hmac( 'sha256', $message, $this->secret );
	}

	/**
	 * Integer seed from the first 28 bits of the hash (fits a 32-bit signed int).
	 *
	 * @param string $hash
	 *
	 * @return int
	 */
	public function seed_from_hash( $hash ) {
		return (int) hexdec( substr( $hash, 0, 7 ) );
	}

	/**
	 * Normalise the original before hashing. Whitespace is trimmed for every
	 * type; email addresses and user names are also lower-cased so
	 * `Foo@Example.com` and `foo@example.com` map to the same fake value.
	 *
	 * @param string $fake_data_type
	 * @param mixed  $original
	 *
	 * @return string
	 */
	public function normalise( $fake_data_type, $original ) {
		$value = trim( (string) $original );

		if ( $this->is_email_type( $fake_data_type ) || $this->is_username_type( $fake_data_type ) ) {
			$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
		}

		return $value;
	}

	/**
	 * @param string $fake_data_type
	 * @param array  $args
	 *
	 * @return string
	 */
	protected function type_key( $fake_data_type, array $args ) {
		if ( $this->is_email_type( $fake_data_type ) ) {
			return 'email';
		}

		if ( empty( $args ) ) {
			return (string) $fake_data_type;
		}

		$encoded = json_encode( $args );

		return $fake_data_type . ':' . ( false === $encoded ? serialize( $args ) : $encoded );
	}

	/**
	 * A unique user name, or email address at the reserved domain, built from
	 * a Faker user name and a hex fragment.
	 *
	 * @param string $fake_data_type
	 * @param string $username
	 * @param string $fragment
	 *
	 * @return string
	 */
	protected function unique_value( $fake_data_type, $username, $fragment ) {
		$value = $this->unique_local_part( $username, $fragment );
		if ( $this->is_email_type( $fake_data_type ) ) {
			$value .= '@' . self::EMAIL_DOMAIN;
		}

		return $value;
	}

	/**
	 * Build a unique local part: a sanitised Faker user name plus a fragment.
	 * In deterministic mode the fragment comes from different bits of the HMAC
	 * than the seed; in random mode it comes from random_bytes().
	 *
	 * @param string $username
	 * @param string $fragment
	 *
	 * @return string
	 */
	protected function unique_local_part( $username, $fragment ) {
		$username = strtolower( (string) preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $username ) );
		$username = trim( substr( $username, 0, 30 ), '._-' );

		if ( '' === $username ) {
			$username = 'user';
		}

		return $username . '.' . $fragment;
	}

	/**
	 * @param string $fake_data_type
	 *
	 * @return bool
	 */
	protected function is_email_type( $fake_data_type ) {
		return in_array( $fake_data_type, $this->email_types, true );
	}

	/**
	 * @param string $fake_data_type
	 *
	 * @return bool
	 */
	protected function is_username_type( $fake_data_type ) {
		return in_array( $fake_data_type, $this->username_types, true );
	}
}
