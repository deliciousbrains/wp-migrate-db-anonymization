<?php

namespace WPMDB\Anonymization\Tests\Unit;

use Faker\Factory;
use WPMDB\Anonymization\Anonymizer;
use WPMDB\Anonymization\Config\Rule;
use WPMDB\Anonymization\Deterministic;
use WPMDB\Anonymization\Tests\Doubles\FakerSpy;
use WPMDB\Anonymization\Tests\TestCase;

/**
 * Hash-shaped values (md5, sha1, sha256, uuid), such as licence keys and
 * tokens, are often unique. Faker builds them from mt_rand(), seeded with only
 * 28 bits in deterministic mode, so they are built from a keyed hash or
 * random_bytes() instead.
 */
class HashUniquenessTest extends TestCase {

	const COUNT = 100000;

	/**
	 * @var string Key built at runtime.
	 */
	protected $key;

	/**
	 * @var Deterministic With $key.
	 */
	protected $keyed;

	/**
	 * @var Deterministic Without a key, i.e. random mode.
	 */
	protected $random;

	protected function setUp(): void {
		parent::setUp();
		$this->key    = bin2hex( random_bytes( 16 ) );
		$this->keyed  = new Deterministic( $this->key );
		$this->random = new Deterministic( '' );
	}

	public function type_provider() {
		return array(
			'md5'    => array( 'md5', '/^[0-9a-f]{32}$/' ),
			'sha1'   => array( 'sha1', '/^[0-9a-f]{40}$/' ),
			'sha256' => array( 'sha256', '/^[0-9a-f]{64}$/' ),
			'uuid'   => array( 'uuid', '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/' ),
		);
	}

	/**
	 * @param string $type
	 *
	 * @return Rule
	 */
	protected function rule( $type ) {
		$rule = new Rule( 'edd_licenses', 'license_key' );
		$rule->load( array( 'fake_data_type' => $type ) );

		return $rule;
	}

	/**
	 * @dataProvider type_provider
	 */
	public function test_hash_types_are_unique_types( $type ) {
		$this->assertTrue( $this->keyed->is_unique_type( $type ) );
		$this->assertTrue( $this->keyed->is_hash_type( $type ) );
	}

	/**
	 * @dataProvider type_provider
	 */
	public function test_deterministic_values_are_unique_and_shaped( $type, $pattern ) {
		$faker = Factory::create();
		$rule  = $this->rule( $type );
		$seen  = array();

		for ( $i = 0; $i < self::COUNT; $i++ ) {
			$value = $rule->anonymize( $faker, "licence-{$i}", $this->keyed );
			if ( 1 !== preg_match( $pattern, $value ) ) {
				$this->fail( "{$value} does not match {$pattern}" );
			}
			$seen[ $value ] = true;
		}

		$this->assertCount( self::COUNT, $seen );
	}

	/**
	 * @dataProvider type_provider
	 */
	public function test_random_values_are_unique_and_shaped( $type, $pattern ) {
		$faker = Factory::create();
		$rule  = $this->rule( $type );
		$seen  = array();

		for ( $i = 0; $i < self::COUNT; $i++ ) {
			// The same original every time: in random mode the output must still differ.
			$value = $rule->anonymize( $faker, 'same-licence', $this->random );
			if ( 1 !== preg_match( $pattern, $value ) ) {
				$this->fail( "{$value} does not match {$pattern}" );
			}
			$seen[ $value ] = true;
		}

		$this->assertCount( self::COUNT, $seen );
	}

	/**
	 * @dataProvider type_provider
	 */
	public function test_same_original_gives_same_value_with_a_key( $type ) {
		$value = $this->rule( $type )->anonymize( Factory::create(), 'licence-1', $this->keyed );

		$this->assertSame( $value, $this->rule( $type )->anonymize( Factory::create(), ' licence-1 ', $this->keyed ), 'Whitespace is trimmed.' );
		$this->assertSame( $value, ( new Deterministic( $this->key ) )->generate( Factory::create(), $type, array(), 'licence-1' ) );
		$this->assertNotSame( $value, $this->rule( $type )->anonymize( Factory::create(), 'licence-2', $this->keyed ) );
		$this->assertNotSame( $value, ( new Deterministic( bin2hex( random_bytes( 16 ) ) ) )->generate( Factory::create(), $type, array(), 'licence-1' ) );
	}

	/**
	 * @dataProvider type_provider
	 */
	public function test_faker_is_not_used( $type ) {
		$faker = new FakerSpy();

		$this->keyed->generate( $faker, $type, array(), 'licence-1' );
		$this->random->generate_random_unique( $faker, $type );

		$this->assertSame( array(), $faker->calls );
	}

	public function test_sha256_does_not_reuse_the_seed_and_fragment_hash() {
		$hash  = $this->keyed->hash( 'sha256', array(), 'licence-1' );
		$value = $this->keyed->generate( Factory::create(), 'sha256', array(), 'licence-1' );

		$this->assertNotSame( $hash, $value );
		$this->assertSame( bin2hex( $this->keyed->value_hash( 'sha256', array(), 'licence-1' ) ), $value );
	}

	public function test_each_hash_type_is_keyed_separately() {
		$md5  = $this->keyed->generate( Factory::create(), 'md5', array(), 'licence-1' );
		$sha1 = $this->keyed->generate( Factory::create(), 'sha1', array(), 'licence-1' );

		$this->assertNotSame( $md5, substr( $sha1, 0, 32 ) );
	}

	public function test_same_licence_key_gives_same_md5_in_every_table() {
		$config = $this->make_config(
			array(
				'edd_licenses' => array( 'license_key' => array( 'fake_data_type' => 'md5' ) ),
				'postmeta'     => array(
					'meta_value' => array(
						array(
							'constraint'     => array( 'meta_key' => '_edd_sl_key' ),
							'fake_data_type' => 'md5',
						),
					),
				),
			)
		);
		$anonymizer  = new Anonymizer( $config, Factory::create(), $this->keyed );
		$licence_key = md5( 'licence-' . mt_rand() );

		$from_licences = $anonymizer->anonymize_value( 'edd_licenses', 'license_key', $this->row( array( 'license_key' => $licence_key ) ), $licence_key );
		$from_meta     = $anonymizer->anonymize_value( 'postmeta', 'meta_value', $this->row( array( 'meta_key' => '_edd_sl_key', 'meta_value' => $licence_key ) ), $licence_key );

		$this->assertMatchesRegularExpression( '/^[0-9a-f]{32}$/', $from_licences );
		$this->assertNotSame( $licence_key, $from_licences );
		$this->assertSame( $from_licences, $from_meta );
	}
}
