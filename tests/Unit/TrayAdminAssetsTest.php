<?php
/**
 * The screen's stylesheet and script.
 *
 * 1.1.0 printed its CSS as style attributes and its confirm prompt as an
 * onclick attribute. The WordPress.org review asks for both to go through the
 * enqueue API, which is what 1.1.1 does — on the Microgreens screen only.
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TrayAdminAssetsTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['bfmt_calls']   = [];
		$GLOBALS['bfmt_filters'] = [];
	}

	public function test_the_screen_enqueues_its_stylesheet_and_script(): void {
		BFMT_Tray_Admin::enqueue_assets( 'toplevel_page_bfmt-trays' );

		$this->assertSame(
			[ [ 'bfmt-admin', BFMT_URL . 'assets/css/admin.css', [], BFMT_VERSION ] ],
			bfmt_test_calls( 'wp_enqueue_style' )
		);
		$this->assertSame(
			[ [ 'bfmt-admin', BFMT_URL . 'assets/js/admin.js', [], BFMT_VERSION, [ 'in_footer' => true ] ] ],
			bfmt_test_calls( 'wp_enqueue_script' )
		);
	}

	public function test_the_status_colours_go_in_as_inline_style_on_the_same_handle(): void {
		BFMT_Tray_Admin::enqueue_assets( 'toplevel_page_bfmt-trays' );

		$this->assertSame( [ [ 'bfmt-admin', BFMT_Status::stylesheet() ] ], bfmt_test_calls( 'wp_add_inline_style' ) );
		$this->assertNotSame( '', BFMT_Status::stylesheet() );
	}

	public function test_no_other_screen_gets_them(): void {
		foreach ( [ 'index.php', 'plugins.php', 'toplevel_page_other', 'microgreens_page_bfmt-trays-extra', '' ] as $hook ) {
			BFMT_Tray_Admin::enqueue_assets( $hook );
		}

		$this->assertSame( [], $GLOBALS['bfmt_calls'] );
	}

	public function test_the_files_it_enqueues_exist(): void {
		$root = dirname( __DIR__, 2 );

		$this->assertFileExists( $root . '/assets/css/admin.css' );
		$this->assertFileExists( $root . '/assets/js/admin.js' );
	}

	public function test_the_neutral_badge_colour_lives_in_the_stylesheet(): void {
		$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/admin.css' );

		$this->assertMatchesRegularExpression( '/\.bfmt-badge\s*\{[^}]*background:\s*#f3f4f6/', $css );
		$this->assertMatchesRegularExpression( '/\.bfmt-pro-features\s*\{[^}]*list-style:\s*disc/', $css );
	}

	/**
	 * The trash prompt reads its question from the link, so the script is only
	 * useful if the list table puts it there.
	 */
	public function test_the_script_prompts_from_the_links_data_attribute(): void {
		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/admin.js' );

		$this->assertStringContainsString( 'a[data-bfmt-confirm]', $js );
		$this->assertStringContainsString( "getAttribute( 'data-bfmt-confirm' )", $js );
		$this->assertStringContainsString( 'preventDefault', $js );
	}
}
