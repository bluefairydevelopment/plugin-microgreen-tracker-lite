<?php
/**
 * The plugin file, boot, activation and uninstall.
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class PluginBootTest extends TestCase {

	protected function setUp(): void {
		bfmt_test_reset();
	}

	private static function root(): string {
		return dirname( __DIR__, 2 );
	}

	// ------------------------------------------------------------ plugin file

	public function test_the_plugin_file_registers_activation_and_boot(): void {
		$load = $GLOBALS['bfmt_load_calls'];

		$this->assertContains( [ 'register_activation_hook', [ self::root() . '/bluefairy-microgreen-tracker.php', [ 'BFMT_Plugin', 'activate' ] ] ], $load );
		$this->assertContains( [ 'add_action', [ 'plugins_loaded', [ 'BFMT_Plugin', 'boot' ], 10 ] ], $load );
	}

	public function test_the_marker_function_reports_the_version(): void {
		$this->assertSame( BFMT_VERSION, bfmt_plugin() );
	}

	/**
	 * One version, three places. The directory serves the readme's Stable tag;
	 * WordPress reads the header; the code reads the constant.
	 */
	public function test_the_version_agrees_everywhere(): void {
		$main   = (string) file_get_contents( self::root() . '/bluefairy-microgreen-tracker.php' );
		$readme = (string) file_get_contents( self::root() . '/readme.txt' );

		$this->assertMatchesRegularExpression( '/^\s*\*\s*Version:\s*' . preg_quote( BFMT_VERSION, '/' ) . '\s*$/m', $main );
		$this->assertMatchesRegularExpression( '/^Stable tag:\s*' . preg_quote( BFMT_VERSION, '/' ) . '\s*$/m', $readme );
		$this->assertMatchesRegularExpression( '/^= ' . preg_quote( BFMT_VERSION, '/' ) . ' =$/m', $readme, 'the readme changelog has an entry for it' );
	}

	public function test_the_plugin_header_has_no_blank_line_inside_it(): void {
		$main = (string) file_get_contents( self::root() . '/bluefairy-microgreen-tracker.php' );
		preg_match( '#/\*\*(.*?)\*/#s', $main, $header );

		$this->assertDoesNotMatchRegularExpression( '/\n\s*\n/', $header[1] );
	}

	public function test_the_paths_point_at_the_plugin(): void {
		$this->assertSame( self::root() . '/', BFMT_PATH );
		$this->assertSame( 'bluefairy-microgreen-tracker/bluefairy-microgreen-tracker.php', BFMT_BASENAME );
		$this->assertStringEndsWith( '/bluefairy-microgreen-tracker/', BFMT_URL );
	}

	// ------------------------------------------------------------------ boot

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

	/**
	 * bfmt_loaded is where Pro registers its fields and answers the status
	 * filters; it has to fire after the tray is wired and before the admin
	 * screen, which reads both.
	 */
	public function test_boot_in_admin_fires_bfmt_loaded_between_the_tray_and_the_screen(): void {
		$GLOBALS['bfmt_is_admin'] = true;

		BFMT_Plugin::boot();

		$sequence = bfmt_test_sequence();
		$tray     = array_search( 'add_action:init', $sequence, true );
		$loaded   = array_search( 'do_action:bfmt_loaded', $sequence, true );
		$screen   = array_search( 'add_action:admin_menu', $sequence, true );
		$upsell   = array_search( 'add_filter:plugin_action_links_' . BFMT_BASENAME, $sequence, true );

		$this->assertNotFalse( $tray );
		$this->assertNotFalse( $loaded );
		$this->assertNotFalse( $screen );
		$this->assertNotFalse( $upsell );
		$this->assertLessThan( $loaded, $tray );
		$this->assertLessThan( $screen, $loaded );
	}

	public function test_boot_on_the_front_end_wires_no_admin_screen(): void {
		BFMT_Plugin::boot();

		$sequence = bfmt_test_sequence();
		$this->assertContains( 'add_action:init', $sequence );
		$this->assertContains( 'do_action:bfmt_loaded', $sequence );
		$this->assertNotContains( 'add_action:admin_menu', $sequence );
		$this->assertNotContains( 'add_filter:plugin_action_links_' . BFMT_BASENAME, $sequence );
	}

	// ------------------------------------------------------------ activation

	public function test_activation_registers_the_tray_and_imports_only_when_there_is_a_table(): void {
		$GLOBALS['wpdb'] = new class() {
			public string $prefix = 'wp_';
			public int $selects   = 0;
			public function esc_like( $t ) { return $t; }
			public function prepare( $q, ...$a ) { return $q; }
			public function get_var( $q ) { return null; }
			public function get_results( $q ) { ++$this->selects; return []; }
		};

		BFMT_Plugin::activate();

		$this->assertCount( 1, bfmt_test_calls( 'register_post_type' ) );
		$this->assertSame( 0, $GLOBALS['wpdb']->selects, 'no legacy table, so no import' );
		$this->assertSame( [], bfmt_test_calls( 'update_option' ) );
	}

	public function test_activation_runs_the_import_when_the_table_is_there(): void {
		$GLOBALS['wpdb'] = new class() {
			public string $prefix = 'wp_';
			public function esc_like( $t ) { return $t; }
			public function prepare( $q, ...$a ) { return $q; }
			public function get_var( $q ) { return 'wp_bfmtl_grows'; }
			public function get_results( $q ) { return []; }
		};

		BFMT_Plugin::activate();

		$this->assertSame( [ 'imported' => 0, 'skipped' => 0, 'failed' => 0 ], $GLOBALS['bfmt_options']['bfmt_legacy_import'] );
	}

	// -------------------------------------------------------------- uninstall

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_uninstall_removes_the_two_options_and_keeps_every_tray(): void {
		require dirname( __DIR__ ) . '/bootstrap.php';
		bfmt_test_reset();

		define( 'WP_UNINSTALL_PLUGIN', 'bluefairy-microgreen-tracker/bluefairy-microgreen-tracker.php' );
		require dirname( __DIR__, 2 ) . '/uninstall.php';

		$this->assertSame( [ [ 'bfmt_features' ], [ 'bfmt_legacy_import' ] ], bfmt_test_calls( 'delete_option' ) );
		$this->assertSame( [], bfmt_test_calls( 'wp_trash_post' ) );
		$this->assertSame( [], bfmt_test_calls( 'delete_post_meta' ) );
		$this->assertSame( [ 'delete_option:bfmt_features', 'delete_option:bfmt_legacy_import' ], bfmt_test_sequence(), 'nothing else ran' );
	}
}
