<?php

namespace WPMDB\Anonymization\Tests\Doubles;

use WPMDB\Anonymization\Config\Config;

/**
 * Config that exposes its raw data array for assertions.
 */
class InspectableConfig extends Config {

	/**
	 * The loaded config. Config loads lazily, so trigger it first.
	 *
	 * @return array
	 */
	public function data() {
		$this->maybe_load();

		return $this->data;
	}
}
