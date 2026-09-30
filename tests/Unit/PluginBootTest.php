<?php
/**
 * Boot.
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PluginBootTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['bfmt_calls']    = [];
		$GLOBALS['bfmt_is_admin'] = false;
	}

	/**
	 * Positive control: the stub records a call, so the absence asserted below
	 * means the call was not made rather than that nothing could see it.
	 */
	public function test_the_recorder_sees_a_textdomain_call_when_one_is_made(): void {
		load_plugin_textdomain( 'bluefairy-microgreen-tracker' );

		$this->assertCount( 1, bfmt_test_calls( 'load_plugin_textdomain' ) );
	}

	/**
	 * WordPress.org loads a hosted plugin's translations itself (since 4.6), and
	 * Plugin Check flags the manual call.
	 */
	public function test_boot_does_not_load_the_textdomain_by_hand(): void {
		BFMT_Plugin::boot();

		$this->assertSame( [], bfmt_test_calls( 'load_plugin_textdomain' ) );
	}

	public function test_the_bootstrap_file_does_not_either(): void {
		$root = dirname( __DIR__, 2 );

		foreach ( [ 'bluefairy-microgreen-tracker.php', 'includes/class-plugin.php' ] as $file ) {
			$this->assertStringNotContainsString( 'load_plugin_textdomain', (string) file_get_contents( $root . '/' . $file ), $file );
		}
	}
}
