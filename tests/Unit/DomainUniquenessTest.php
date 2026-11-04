<?php

namespace WPMDB\Anonymization\Tests\Unit;

use Faker\Factory;
use WPMDB\Anonymization\Deterministic;
use WPMDB\Anonymization\Tests\TestCase;

/**
 * Domain names and URLs are often unique (e.g. EDD Software Licensing's
 * UNIQUE (site_name, license_id)), so they get a hash fragment like emails.
 */
class DomainUniquenessTest extends TestCase {

	const DOMAIN_PATTERN = '/^[a-z0-9-]{1,40}-[a-f0-9]{12}\.example\.com$/';
	const URL_PATTERN    = '#^https://[a-z0-9-]{1,40}-[a-f0-9]{12}\.example\.com/$#';

	public function test_domain_types_are_unique_types() {
		$deterministic = new Deterministic( 'secret' );
		$this->assertTrue( $deterministic->is_unique_type( 'domainName' ) );
		$this->assertTrue( $deterministic->is_unique_type( 'url' ) );
		$this->assertFalse( $deterministic->is_unique_type( 'domainWord' ) );
	}

	/**
	 * @dataProvider type_provider
	 */
	public function test_deterministic_domains_are_unique_and_stable( $type, $pattern ) {
		$faker         = Factory::create();
		$deterministic = new Deterministic( 'secret' );
		$seen          = array();

		for ( $i = 0; $i < 50000; $i++ ) {
			$value = $deterministic->generate( $faker, $type, array(), "site{$i}.real.test" );
			$this->assertMatchesRegularExpression( $pattern, $value );
			$seen[ $value ] = true;
		}

		$this->assertCount( 50000, $seen );
		$this->assertSame(
			$deterministic->generate( $faker, $type, array(), 'shop.real.test' ),
			$deterministic->generate( Factory::create(), $type, array(), 'shop.real.test' )
		);
	}

	/**
	 * @dataProvider type_provider
	 */
	public function test_random_domains_are_unique( $type, $pattern ) {
		$faker         = Factory::create();
		$deterministic = new Deterministic( '' );
		$seen          = array();

		for ( $i = 0; $i < 50000; $i++ ) {
			$value = $deterministic->generate_random_unique( $faker, $type );
			$this->assertMatchesRegularExpression( $pattern, $value );
			$seen[ $value ] = true;
		}

		$this->assertCount( 50000, $seen );
	}

	public function type_provider() {
		return array(
			'domainName' => array( 'domainName', self::DOMAIN_PATTERN ),
			'url'        => array( 'url', self::URL_PATTERN ),
		);
	}

	public function test_domain_names_ignore_case() {
		$faker         = Factory::create();
		$deterministic = new Deterministic( 'secret' );

		$this->assertSame(
			$deterministic->generate( $faker, 'domainName', array(), 'Shop.Real.Test' ),
			$deterministic->generate( $faker, 'domainName', array(), 'shop.real.test' )
		);
	}
}
