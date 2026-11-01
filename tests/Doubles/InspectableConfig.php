<?php

namespace WPMDB\Anonymization\Tests\Doubles;

use WPMDB\Anonymization\Config\Config;

/**
 * Config that exposes its raw data array for assertions.
 */
class InspectableConfig extends Config {

	/**
	 * @return array
	 */
	public function data() {
		return $this->data;
	}
}
