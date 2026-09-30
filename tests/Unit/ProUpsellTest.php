<?php
/**
 * The one link and the one panel that name the paid add-on.
 *
 * WordPress.org allows a free plugin to mention its paid version; what it does
 * not allow is a claim the paid version cannot keep. 1.1.0's panel said Pro
 * worked out the harvest date "from how much seed you sowed" and offered
 * "germination rates". Pro uses each variety's days-to-harvest range, and its
 * reports show yield, harvested and failed counts and cost per tray.
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ProUpsellTest extends TestCase {

	protected function setUp(): void {
		bfmt_test_reset();
	}

	/**
	 * Only the in-process tests call this, so the pid it leaves behind is the
	 * parent's. setUp()/setUpBeforeClass() would not do: a child process runs
	 * those too.
	 */
	private function panel(): string {
		file_put_contents( sys_get_temp_dir() . '/bfmt-upsell-test-parent.pid', (string) getmypid() );

		ob_start();
		BFMT_Pro_Upsell::render_panel();

		return (string) ob_get_clean();
	}

	public function test_init_adds_the_link_to_this_plugins_row_only(): void {
		BFMT_Pro_Upsell::init();

		$this->assertSame(
			[ [ 'plugin_action_links_' . BFMT_BASENAME, [ BFMT_Pro_Upsell::class, 'action_links' ], 10 ] ],
			bfmt_test_calls( 'add_filter' )
		);
		$this->assertSame( 'plugin_action_links_bluefairy-microgreen-tracker/bluefairy-microgreen-tracker.php', 'plugin_action_links_' . BFMT_BASENAME );
	}

	public function test_the_panel_describes_the_harvest_calculation_pro_actually_does(): void {
		$panel = $this->panel();

		$this->assertStringContainsString( 'Expected harvest worked out for you, from each variety&#039;s days to harvest', $panel );
		$this->assertStringNotContainsString( 'seed you sowed', $panel );
	}

	public function test_the_panel_names_only_reports_pro_has(): void {
		$panel = $this->panel();

		$this->assertStringContainsString( 'Average yield, harvested and failed counts per variety, and cost per tray', $panel );
		$this->assertStringNotContainsString( 'germination rate', $panel );
		$this->assertStringContainsString( 'A daily digest email', $panel );
		$this->assertStringContainsString( 'A Farm Worker role', $panel );
	}

	public function test_the_panel_list_is_styled_by_class_not_attribute(): void {
		$panel = $this->panel();

		$this->assertStringContainsString( '<ul class="bfmt-pro-features">', $panel );
		$this->assertStringNotContainsString( 'style=', $panel );
	}

	public function test_the_panel_lists_eight_features_and_one_button(): void {
		$panel = $this->panel();

		$this->assertSame( 8, substr_count( $panel, '<li>' ) );
		$this->assertSame( 1, substr_count( $panel, 'button-primary' ) );
		$this->assertStringContainsString( 'href="https://wordpress.bluefairydevelopment.com/product/bluefairy-microgreen-tracker-pro/"', $panel );
	}

	public function test_the_plugin_row_gets_one_link_to_pro(): void {
		$links = BFMT_Pro_Upsell::action_links( [ 'deactivate' => '<a>Deactivate</a>' ] );

		$this->assertCount( 2, $links );
		$this->assertStringContainsString( 'Get Microgreen Tracker Pro', end( $links ) );
		$this->assertStringContainsString( 'rel="noopener noreferrer"', end( $links ) );
		$this->assertStringContainsString( 'target="_blank"', end( $links ) );
	}

	public function test_pro_is_not_active_without_its_marker(): void {
		$this->assertFalse( BFMT_Pro_Upsell::pro_is_active() );
	}

	/**
	 * bfmtp_plugin() cannot be undeclared once declared, so this runs in a
	 * child process. (Attributes, not @annotations: this PHPUnit ignored the
	 * docblock form and ran it in-process — the pid check below caught that.)
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_nothing_is_shown_once_pro_is_active(): void {
		// Proof this is a child process, so the declaration below cannot leak
		// into the rest of the suite.
		$this->assertNotSame( trim( (string) file_get_contents( sys_get_temp_dir() . '/bfmt-upsell-test-parent.pid' ) ), (string) getmypid() );

		require dirname( __DIR__ ) . '/bootstrap.php';

		// Positive control: before Pro exists, the panel prints.
		ob_start();
		BFMT_Pro_Upsell::render_panel();
		$this->assertNotSame( '', ob_get_clean() );

		eval( 'function bfmtp_plugin() { return "1.4.0"; }' );

		$this->assertTrue( BFMT_Pro_Upsell::pro_is_active() );

		ob_start();
		BFMT_Pro_Upsell::render_panel();
		$this->assertSame( '', ob_get_clean() );

		$this->assertSame( [ 'x' ], BFMT_Pro_Upsell::action_links( [ 'x' ] ) );
		$this->assertStringContainsString( 'Left blank, Pro fills it in', BFMT_Tray_Admin::harvest_help( false ) );
		$this->assertSame( 'Optional.', BFMT_Tray_Admin::harvest_help( true ) );

		// With Pro on, the base list offers no status links, and a crafted one
		// is refused before anything is written: Pro records harvests with
		// yield and sale, failures with a reason, and frees rack slots.
		$GLOBALS['bfmt_types'][9] = 'mgtrack_tray';
		$GLOBALS['bfmt_caps']     = [ 'edit_posts' => true ];
		$this->assertSame( [], BFMT_Tray_List_Table::status_actions( 9 ) );

		bfmt_test_request( [ 'page' => 'bfmt-trays', 'action' => 'harvest', 'tray_id' => '9' ] );
		try {
			BFMT_Tray_Admin::handle_actions();
			$this->fail( 'the handler did not end the request' );
		} catch ( BFMT_Test_Halt $halt ) {
			$this->assertSame( 'redirect', $halt->kind );
		}
		$this->assertSame( [], bfmt_test_calls( 'update_post_meta' ) );
		$this->assertSame( 'error', $GLOBALS['bfmt_transients']['bfmt_notice_5'][0] );
		$this->assertStringContainsString( "Pro's tray screens", $GLOBALS['bfmt_transients']['bfmt_notice_5'][1] );
	}

	public function test_without_pro_the_list_offers_the_status_links(): void {
		$GLOBALS['bfmt_types'][9] = 'mgtrack_tray';

		$this->assertSame( [ 'harvest', 'fail' ], array_keys( BFMT_Tray_List_Table::status_actions( 9 ) ) );
	}
}
