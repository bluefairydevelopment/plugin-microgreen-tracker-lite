<?php
/**
 * The one link and the one panel that name the paid add-on.
 *
 * WordPress.org allows a free plugin to mention its paid version; what it does
 * not allow is a claim the paid version cannot keep. 1.1.0's panel said Pro
 * worked out the harvest date "from how much seed you sowed". Pro actually uses
 * each variety's days-to-harvest range, and the readme said so — so the two
 * disagreed, and the one on screen was wrong.
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ProUpsellTest extends TestCase {

	private static function pid_file(): string {
		return sys_get_temp_dir() . '/bfmt-upsell-test-parent.pid';
	}


	/**
	 * Only the in-process tests call this, so the pid it leaves behind is the
	 * parent's. setUp()/setUpBeforeClass() would not do: a child process runs
	 * those too.
	 */
	private function panel(): string {
		file_put_contents( self::pid_file(), (string) getmypid() );

		ob_start();
		BFMT_Pro_Upsell::render_panel();

		return (string) ob_get_clean();
	}

	public function test_the_panel_describes_the_harvest_calculation_pro_actually_does(): void {
		$panel = $this->panel();

		$this->assertStringContainsString( 'Expected harvest worked out for you, from each variety&#039;s days to harvest', $panel );
		$this->assertStringNotContainsString( 'seed you sowed', $panel );
	}

	public function test_the_panel_list_is_styled_by_class_not_attribute(): void {
		$panel = $this->panel();

		$this->assertStringContainsString( '<ul class="bfmt-pro-features">', $panel );
		$this->assertStringNotContainsString( 'style=', $panel );
	}

	public function test_the_panel_lists_seven_features_and_one_button(): void {
		$panel = $this->panel();

		$this->assertSame( 7, substr_count( $panel, '<li>' ) );
		$this->assertSame( 1, substr_count( $panel, 'button-primary' ) );
		$this->assertStringContainsString( 'href="https://wordpress.bluefairydevelopment.com/product/bluefairy-microgreen-tracker-pro/"', $panel );
	}

	public function test_the_plugin_row_gets_one_link_to_pro(): void {
		$links = BFMT_Pro_Upsell::action_links( [ 'deactivate' => '<a>Deactivate</a>' ] );

		$this->assertCount( 2, $links );
		$this->assertStringContainsString( 'Get Microgreen Tracker Pro', end( $links ) );
		$this->assertStringContainsString( 'rel="noopener noreferrer"', end( $links ) );
	}

	/**
	 * bfmtp_plugin() cannot be undeclared once declared, so this runs in a
	 * child process. (Attributes, not @annotations: this PHPUnit ignored the
	 * docblock form and ran it in-process — the pid check below caught that.)
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_nothing_is_shown_once_pro_is_active(): void {
		// The eval below declares bfmtp_plugin() for good. Proof this is a child
		// process, so the declaration cannot leak into the rest of the suite.
		$this->assertNotSame( trim( (string) file_get_contents( self::pid_file() ) ), (string) getmypid() );

		require dirname( __DIR__ ) . '/bootstrap.php';

		// Positive control: before Pro exists, the panel prints.
		ob_start();
		BFMT_Pro_Upsell::render_panel();
		$this->assertNotSame( '', ob_get_clean() );

		eval( 'function bfmtp_plugin() { return "1.4.0"; }' );

		ob_start();
		BFMT_Pro_Upsell::render_panel();
		$this->assertSame( '', ob_get_clean() );

		$this->assertSame( [ 'x' ], BFMT_Pro_Upsell::action_links( [ 'x' ] ) );
	}
}
