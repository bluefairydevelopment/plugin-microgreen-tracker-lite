<?php
/**
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FeatureRegistryTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['bfmt_options'] = [];
		$GLOBALS['bfmt_filters'] = [];
	}

	public function test_a_feature_with_no_stored_value_is_on(): void {
		$this->assertTrue( BFMT_Feature_Registry::is_enabled( 'reports' ) );
	}

	/**
	 * Pro's switches arrive after this option was last written; defaulting an
	 * unknown key to off would silently disable something just paid for.
	 */
	public function test_a_feature_the_stored_option_predates_is_on(): void {
		$GLOBALS['bfmt_options']['bfmt_features'] = [ 'racks' => true ];

		$this->assertTrue( BFMT_Feature_Registry::is_enabled( 'reports' ) );
	}

	public function test_a_feature_stored_as_off_is_off(): void {
		$GLOBALS['bfmt_options']['bfmt_features'] = [ 'racks' => false ];

		$this->assertFalse( BFMT_Feature_Registry::is_enabled( 'racks' ) );
	}

	public function test_only_keys_with_a_label_are_stored(): void {
		$GLOBALS['bfmt_filters']['bfmt_feature_labels'] = static fn( array $l ): array => $l + [ 'racks' => 'Racks' ];

		$stored = BFMT_Feature_Registry::sanitize( [ 'racks' => '1', 'smuggled' => '1' ] );

		$this->assertSame( [ 'racks' ], array_keys( $stored ) );
	}
}
