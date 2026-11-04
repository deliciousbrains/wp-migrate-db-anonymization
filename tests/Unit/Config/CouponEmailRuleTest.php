<?php

namespace WPMDB\Anonymization\Tests\Unit\Config;

use WPMDB\Anonymization\Config\Rule;
use WPMDB\Anonymization\Tests\TestCase;

class CouponEmailRuleTest extends TestCase {

	/**
	 * @return Rule
	 */
	protected function coupon_rule() {
		$config = include WPMDB_ANONYMIZATION_PLUGIN_DIR . '/config/woocommerce.php';
		foreach ( $config['postmeta']['meta_value'] as $data ) {
			if ( isset( $data['constraint']['meta_key'] ) && 'customer_email' === $data['constraint']['meta_key'] ) {
				$rule = new Rule( 'postmeta', 'meta_value' );
				$rule->load( $data );

				return $rule;
			}
		}

		$this->fail( 'No customer_email rule in config/woocommerce.php' );
	}

	public function test_restricted_coupons_are_anonymized() {
		$row = (object) array( 'meta_key' => 'customer_email', 'meta_value' => serialize( array( 'real@customer.test' ) ) );
		$this->assertTrue( $this->coupon_rule()->valid( $row ) );
	}

	public function test_unrestricted_coupons_stay_unrestricted() {
		$row = (object) array( 'meta_key' => 'customer_email', 'meta_value' => 'a:0:{}' );
		$this->assertFalse( $this->coupon_rule()->valid( $row ) );
	}
}
