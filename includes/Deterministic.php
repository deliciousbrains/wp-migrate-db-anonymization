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
 * Hash-shaped values (md5, sha1, sha256 and uuid), such as licence keys and
 * tokens, are not taken from Faker: its output depends on a seed of only 28
 * bits, so distinct originals could get the same fake. They are built
 * directly from a second keyed hash of the original instead.
 *
 * Without a secret, the same uniqueness-sensitive types get a random fragment
 * in the same format instead (see generate_random_unique()), and hash-shaped
 * values are built from random_bytes(), so fake values don't collide in
 * random mode either.
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
	 * Faker formatters that produce hash-shaped values, mapped to their length
	 * in hex characters. They are built from a keyed hash or random bytes
	 * rather than Faker, so they don't collide.
	 *
	 * @var array
	 */
	protected $hash_types = array(
		'md5'    => 32,
		'sha1'   => 40,
		'sha256' => 64,
		'uuid'   => 32,
	);

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
		if ( $this->is_hash_type( $fake_data_type ) ) {
			return $this->hash_value( $fake_data_type, $this->value_hash( $fake_data_type, $args, $original ) );
		}

		$hash = $this->hash( $fake_data_type, $args, $original );

		$faker->seed( $this->seed_from_hash( $hash ) );

		try {
			if ( $this->is_unique_type( $fake_data_type ) ) {
				$base  = $this->is_domain_type( $fake_data_type ) ? $faker->domainWord() : $faker->userName();
				$value = $this->unique_value( $fake_data_type, $base, substr( $hash, 32, self::FRAGMENT_LENGTH ) );
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
	 * (email addresses, user names, domain names, URLs and hash-shaped
	 * values) when no secret is configured.
	 *
	 * Faker's own output repeats on large data sets, which breaks unique
	 * columns (for example a plugin table with a UNIQUE email column). WP
	 * Migrate also boots a fresh Faker for every request, so Faker's unique()
	 * modifier can't help. Instead a random fragment from random_bytes() is
	 * embedded, in the same format as deterministic mode, so collisions are
	 * as unlikely as there and the output looks the same in both modes.
	 * Hash-shaped values are built entirely from random_bytes(), as Faker's
	 * come from mt_rand() and repeat on large tables.
	 *
	 * @param \Faker\Generator $faker
	 * @param string           $fake_data_type
	 *
	 * @return string
	 */
	public function generate_random_unique( $faker, $fake_data_type ) {
		if ( $this->is_hash_type( $fake_data_type ) ) {
			return $this->hash_value( $fake_data_type, random_bytes( 32 ) );
		}

		$fragment = bin2hex( random_bytes( (int) ( self::FRAGMENT_LENGTH / 2 ) ) );
		$base     = $this->is_domain_type( $fake_data_type ) ? $faker->domainWord() : $faker->userName();

		return $this->unique_value( $fake_data_type, $base, $fragment );
	}

	/**
	 * Is this a type whose fake values must not collide (email addresses, user
	 * names, domain names, URLs and hash-shaped values)? Domains and URLs are
	 * often unique too, e.g. EDD Software Licensing's UNIQUE (site_name,
	 * license_id), as are licence keys and tokens.
	 *
	 * @param string $fake_data_type
	 *
	 * @return bool
	 */
	public function is_unique_type( $fake_data_type ) {
		return $this->is_email_type( $fake_data_type ) || $this->is_username_type( $fake_data_type ) || $this->is_domain_type( $fake_data_type ) || $this->is_hash_type( $fake_data_type );
	}

	/**
	 * Is this a hash-shaped type (md5, sha1, sha256 or uuid)?
	 *
	 * @param string $fake_data_type
	 *
	 * @return bool
	 */
	public function is_hash_type( $fake_data_type ) {
		return is_string( $fake_data_type ) && isset( $this->hash_types[ $fake_data_type ] );
	}

	/**
	 * @param string $fake_data_type
	 *
	 * @return bool
	 */
	public function is_domain_type( $fake_data_type ) {
		return in_array( $fake_data_type, array( 'domainName', 'url' ), true );
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
	 * Raw HMAC-SHA256 used to build a hash-shaped value. A second keyed hash,
	 * labelled differently from hash(), so the value shares no bits with the
	 * seed or fragment derived from hash().
	 *
	 * @param string $fake_data_type
	 * @param array  $args
	 * @param mixed  $original
	 *
	 * @return string 32 raw bytes.
	 */
	public function value_hash( $fake_data_type, array $args, $original ) {
		$message = implode(
			"\x1f",
			array(
				self::VERSION,
				'value',
				$this->type_key( $fake_data_type, $args ),
				$this->normalise( $fake_data_type, $original ),
			)
		);

		return hash_hmac( 'sha256', $message, $this->secret, true );
	}

	/**
	 * Format 32 bytes (a keyed hash or random bytes) as a hash-shaped value:
	 * lower-case hex of the type's length, or an RFC 4122 version 4 UUID.
	 *
	 * @param string $fake_data_type md5, sha1, sha256 or uuid.
	 * @param string $bytes          At least 32 raw bytes.
	 *
	 * @return string
	 */
	protected function hash_value( $fake_data_type, $bytes ) {
		if ( 'uuid' !== $fake_data_type ) {
			return substr( bin2hex( $bytes ), 0, $this->hash_types[ $fake_data_type ] );
		}

		$bytes    = substr( $bytes, 0, 16 );
		$bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | 0x40 ); // Version 4.
		$bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 ); // RFC 4122 variant.

		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $bytes ), 4 ) );
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

		if ( $this->is_email_type( $fake_data_type ) || $this->is_username_type( $fake_data_type ) || 'domainName' === $fake_data_type ) {
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
		if ( $this->is_domain_type( $fake_data_type ) ) {
			// A subdomain of the reserved domain, so the fake never resolves to a real site.
			$label  = trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $username ) ), '-' );
			$domain = ( '' === $label ? 'site' : substr( $label, 0, 40 ) ) . '-' . $fragment . '.' . self::EMAIL_DOMAIN;

			return 'url' === $fake_data_type ? 'https://' . $domain . '/' : $domain;
		}

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
