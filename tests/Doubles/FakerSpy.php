<?php

namespace WPMDB\Anonymization\Tests\Doubles;

/**
 * Stands in for a Faker generator: returns the formatter name and records calls.
 */
class FakerSpy {

	/**
	 * @var array
	 */
	public $calls = array();

	public function __call( $name, $arguments ) {
		$this->calls[] = array( $name, $arguments );

		return $name;
	}
}
