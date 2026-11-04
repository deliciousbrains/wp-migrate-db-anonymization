<?php

namespace WPMDB\Anonymization\Tests\Doubles;

use WPMDB\Anonymization\CLI;

/**
 * Exposes the command's helpers and lets tests simulate multisite.
 */
class TestableCLI extends CLI {

	/**
	 * Table prefixes to report as the install's sites, or null for the real lookup.
	 *
	 * @var array|null
	 */
	public $site_prefixes;

	public function call( $method, ...$args ) {
		return $this->{$method}( ...$args );
	}

	protected function site_prefixes() {
		return null === $this->site_prefixes ? parent::site_prefixes() : $this->site_prefixes;
	}
}
