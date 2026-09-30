<?php
/**
 * The Microgreens screen: wiring, the request handlers, and what it prints.
 *
 * Handlers end in wp_safe_redirect() + exit or wp_die(); the stubs throw
 * BFMT_Test_Halt instead, so each test catches it and asserts what happened
 * before the request would have ended.
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TrayAdminTest extends TestCase {

	protected function setUp(): void {
		bfmt_test_reset();
		$GLOBALS['bfmt_types'][1] = 'mgtrack_tray';
		$GLOBALS['bfmt_types'][2] = 'mgtrack_tray';
		$GLOBALS['bfmt_caps']     = [ 'edit_posts' => true ];
	}

	/**
	 * Run handle_actions() for a request and return how it ended.
	 */
	private function handle( array $get, array $post = [] ): BFMT_Test_Halt {
		bfmt_test_request( $get, $post );

		try {
			BFMT_Tray_Admin::handle_actions();
		} catch ( BFMT_Test_Halt $halt ) {
			return $halt;
		}

		$this->fail( 'the handler did not end the request' );
	}

	private function notice(): array {
		return $GLOBALS['bfmt_transients']['bfmt_notice_5'] ?? [];
	}

	private function render( array $get = [] ): string {
		bfmt_test_request( $get );
		ob_start();

		try {
			BFMT_Tray_Admin::render();
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	// ------------------------------------------------------------------ wiring

	public function test_init_wires_the_menu_the_handlers_and_the_assets(): void {
		BFMT_Tray_Admin::init();

		$this->assertSame( [
			[ 'admin_menu', [ BFMT_Tray_Admin::class, 'register_menu' ], 10 ],
			[ 'admin_init', [ BFMT_Tray_Admin::class, 'handle_actions' ], 10 ],
			[ 'admin_enqueue_scripts', [ BFMT_Tray_Admin::class, 'enqueue_assets' ], 10 ],
		], bfmt_test_calls( 'add_action' ) );
	}

	public function test_the_menu_is_top_level_and_gated_on_the_screen_capability(): void {
		BFMT_Tray_Admin::register_menu();

		$menu = bfmt_test_calls( 'add_menu_page' )[0];
		$this->assertSame( 'edit_posts', $menu[2] );
		$this->assertSame( 'bfmt-trays', $menu[3] );
		$this->assertSame( [ BFMT_Tray_Admin::class, 'render' ], $menu[4] );
		$this->assertSame( [ 'bfmt_admin_menu', 'bfmt-trays', 'edit_posts' ], bfmt_test_calls( 'do_action' )[0] );
	}

	// ------------------------------------------------------ which action runs

	public function test_the_top_bulk_menu_is_read(): void {
		bfmt_test_request( [], [ 'action' => 'bulk_trash', 'action2' => '-1' ] );

		$this->assertSame( 'bulk_trash', BFMT_Tray_Admin::requested_action() );
	}

	/**
	 * The menu below the table posts as action2 with action = -1. 1.1.0 read
	 * only `action`, so the bottom menu did nothing.
	 */
	public function test_the_bottom_bulk_menu_is_read_when_the_top_is_unset(): void {
		bfmt_test_request( [], [ 'action' => '-1', 'action2' => 'bulk_trash' ] );

		$this->assertSame( 'bulk_trash', BFMT_Tray_Admin::requested_action() );
	}

	public function test_no_action_is_empty(): void {
		bfmt_test_request( [], [ 'action' => '-1', 'action2' => '-1' ] );
		$this->assertSame( '', BFMT_Tray_Admin::requested_action() );

		bfmt_test_request();
		$this->assertSame( '', BFMT_Tray_Admin::requested_action() );
	}

	public function test_a_request_for_another_page_is_ignored(): void {
		bfmt_test_request( [ 'page' => 'other', 'action' => 'trash', 'tray_id' => '1' ] );

		BFMT_Tray_Admin::handle_actions();

		$this->assertSame( [], bfmt_test_calls( 'check_admin_referer' ) );
		$this->assertSame( [], bfmt_test_calls( 'wp_trash_post' ) );
	}

	// ----------------------------------------------------------- permissions

	/**
	 * Every action is refused without the capability, before any nonce or write.
	 */
	public function test_every_action_needs_the_capability(): void {
		foreach ( [ 'trash', 'harvest', 'fail', 'reopen', 'bulk_trash', 'save' ] as $action ) {
			bfmt_test_reset();
			$GLOBALS['bfmt_types'][1] = 'mgtrack_tray';

			$halt = $this->handle( [ 'page' => 'bfmt-trays', 'action' => $action, 'tray_id' => '1' ], [ 'tray_ids' => [ '1' ] ] );

			$this->assertSame( 'die', $halt->kind, $action );
			$this->assertSame( [], bfmt_test_calls( 'check_admin_referer' ), $action );
			$this->assertSame( [], bfmt_test_calls( 'update_post_meta' ), $action );
			$this->assertSame( [], bfmt_test_calls( 'wp_trash_post' ), $action );
		}
	}

	// ----------------------------------------------------------- status actions

	/**
	 * @return array<string, array{string, string, string}>
	 */
	public static function statusActions(): array {
		return [
			'harvest' => [ 'harvest', 'harvested', 'Tray marked harvested.' ],
			'fail'    => [ 'fail', 'failed', 'Tray marked failed.' ],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'statusActions' )]
	public function test_a_status_action_checks_its_own_nonce_and_changes_the_tray( string $action, string $status, string $message ): void {
		$halt = $this->handle( [ 'page' => 'bfmt-trays', 'action' => $action, 'tray_id' => '1' ] );

		$this->assertSame( 'redirect', $halt->kind );
		$this->assertSame( [ [ 'bfmt_' . $action . '_tray_1' ] ], bfmt_test_calls( 'check_admin_referer' ) );
		$this->assertSame( $status, BFMT_Status::get( 1 ) );
		$this->assertSame( [ 'updated', $message ], $this->notice() );
	}

	public function test_harvest_records_today(): void {
		$this->handle( [ 'page' => 'bfmt-trays', 'action' => 'harvest', 'tray_id' => '1' ] );

		$this->assertSame( '2026-09-30', $GLOBALS['bfmt_meta'][1]['_mgtrack_harvest_date'] );
	}

	public function test_fail_records_today_as_a_date(): void {
		$this->handle( [ 'page' => 'bfmt-trays', 'action' => 'fail', 'tray_id' => '1' ] );

		$this->assertSame( '2026-09-30', $GLOBALS['bfmt_meta'][1]['_mgtrack_failed'] );
	}

	/**
	 * A double click reports success, not "could not be changed".
	 */
	public function test_a_repeated_status_action_still_reports_success(): void {
		$this->handle( [ 'page' => 'bfmt-trays', 'action' => 'harvest', 'tray_id' => '1' ] );
		$this->handle( [ 'page' => 'bfmt-trays', 'action' => 'harvest', 'tray_id' => '1' ] );

		$this->assertSame( [ 'updated', 'Tray marked harvested.' ], $this->notice() );
	}

	public function test_a_status_action_with_a_bad_nonce_changes_nothing(): void {
		$GLOBALS['bfmt_nonce_ok'] = false;

		$halt = $this->handle( [ 'page' => 'bfmt-trays', 'action' => 'harvest', 'tray_id' => '1' ] );

		$this->assertSame( 'nonce', $halt->kind );
		$this->assertSame( [], bfmt_test_calls( 'update_post_meta' ) );
	}

	public function test_every_writing_action_stops_at_a_bad_nonce(): void {
		foreach ( [ 'trash', 'harvest', 'fail', 'reopen', 'bulk_trash', 'save' ] as $action ) {
			bfmt_test_reset();
			$GLOBALS['bfmt_types'][1] = 'mgtrack_tray';
			$GLOBALS['bfmt_caps']     = [ 'edit_posts' => true ];
			$GLOBALS['bfmt_nonce_ok'] = false;

			$halt = $this->handle( [ 'page' => 'bfmt-trays', 'action' => $action, 'tray_id' => '1' ], [ 'tray_ids' => [ '1' ], 'variety' => 'Kale', 'seeding_date' => '2026-09-01' ] );

			$this->assertSame( 'nonce', $halt->kind, $action );
			$this->assertSame( [], bfmt_test_calls( 'update_post_meta' ), $action );
			$this->assertSame( [], bfmt_test_calls( 'wp_trash_post' ), $action );
			$this->assertSame( [], bfmt_test_calls( 'wp_insert_post' ), $action );
		}
	}

	public function test_reopen_undoes_a_finish(): void {
		update_post_meta( 1, '_mgtrack_failed', '2026-09-01' );

		$this->handle( [ 'page' => 'bfmt-trays', 'action' => 'reopen', 'tray_id' => '1' ] );

		$this->assertSame( 'active', BFMT_Status::get( 1 ) );
		$this->assertSame( [ [ 'bfmt_reopen_tray_1' ] ], bfmt_test_calls( 'check_admin_referer' ) );
		$this->assertSame( [ 'updated', 'Tray is growing again.' ], $this->notice() );
	}

	public function test_a_status_action_on_a_missing_tray_reports_an_error(): void {
		$this->handle( [ 'page' => 'bfmt-trays', 'action' => 'harvest', 'tray_id' => '999' ] );

		$this->assertSame( [ 'error', 'That tray could not be changed.' ], $this->notice() );
	}

	// ----------------------------------------------------------------- trash

	public function test_trash_checks_the_trays_nonce_and_trashes_it(): void {
		$halt = $this->handle( [ 'page' => 'bfmt-trays', 'action' => 'trash', 'tray_id' => '2' ] );

		$this->assertSame( 'redirect', $halt->kind );
		$this->assertSame( [ [ 'bfmt_trash_tray_2' ] ], bfmt_test_calls( 'check_admin_referer' ) );
		$this->assertSame( [ [ 2 ] ], bfmt_test_calls( 'wp_trash_post' ) );
		$this->assertSame( [ 'updated', 'Tray moved to the trash.' ], $this->notice() );
	}

	public function test_trashing_a_missing_tray_says_so(): void {
		$this->handle( [ 'page' => 'bfmt-trays', 'action' => 'trash', 'tray_id' => '999' ] );

		$this->assertSame( [ 'error', 'That tray could not be found.' ], $this->notice() );
	}

	public function test_bulk_trash_from_either_menu_counts_what_it_moved(): void {
		foreach ( [ [ 'action' => 'bulk_trash', 'action2' => '-1' ], [ 'action' => '-1', 'action2' => 'bulk_trash' ] ] as $menus ) {
			bfmt_test_reset();
			$GLOBALS['bfmt_types'][1] = $GLOBALS['bfmt_types'][2] = 'mgtrack_tray';
			$GLOBALS['bfmt_caps']     = [ 'edit_posts' => true ];

			$this->handle( [], [ 'page' => 'bfmt-trays', 'tray_ids' => [ '1', '2', '999', '0' ] ] + $menus );

			$this->assertSame( [ [ 'bulk-trays' ] ], bfmt_test_calls( 'check_admin_referer' ) );
			$this->assertSame( [ [ 1 ], [ 2 ] ], bfmt_test_calls( 'wp_trash_post' ) );
			$this->assertSame( [ 'updated', '2 trays moved to the trash.' ], $this->notice() );
		}
	}

	// ------------------------------------------------------------------ save

	public function test_saving_a_new_tray_uses_the_add_nonce_and_reports_it(): void {
		$halt = $this->handle( [], [ 'page' => 'bfmt-trays', 'action' => 'save', 'tray_id' => '0', 'variety' => 'Kale', 'seeding_date' => '2026-09-01' ] );

		$this->assertSame( 'redirect', $halt->kind );
		$this->assertSame( [ [ 'bfmt_add_tray' ] ], bfmt_test_calls( 'check_admin_referer' ) );
		$this->assertCount( 1, bfmt_test_calls( 'wp_insert_post' ) );
		$this->assertSame( [ 'updated', 'Tray added.' ], $this->notice() );
	}

	public function test_a_refused_save_goes_back_to_the_form_with_the_reason(): void {
		$halt = $this->handle( [], [ 'page' => 'bfmt-trays', 'action' => 'save', 'tray_id' => '0', 'variety' => 'Kale', 'seeding_date' => 'yesterday' ] );

		$this->assertStringContainsString( 'action=add', (string) $halt->payload );
		$this->assertSame( 'error', $this->notice()[0] );
		$this->assertStringContainsString( 'yesterday', $this->notice()[1] );
		$this->assertSame( [], bfmt_test_calls( 'wp_insert_post' ) );
	}

	public function test_editing_uses_the_trays_own_nonce(): void {
		$this->handle( [], [ 'page' => 'bfmt-trays', 'action' => 'save', 'tray_id' => '1', 'variety' => 'Kale', 'seeding_date' => '2026-09-01' ] );

		$this->assertSame( [ [ 'bfmt_edit_tray_1' ] ], bfmt_test_calls( 'check_admin_referer' ) );
		$this->assertSame( [ 'updated', 'Tray updated.' ], $this->notice() );
	}

	public function test_a_refused_edit_goes_back_to_that_trays_form(): void {
		$halt = $this->handle( [], [ 'page' => 'bfmt-trays', 'action' => 'save', 'tray_id' => '1', 'seeding_date' => '2026-02-30' ] );

		$this->assertStringContainsString( 'action=edit', (string) $halt->payload );
		$this->assertStringContainsString( 'tray_id=1', (string) $halt->payload );
	}

	// --------------------------------------------------------------- render

	public function test_the_screen_refuses_without_the_capability(): void {
		$GLOBALS['bfmt_caps'] = [];

		$this->expectException( BFMT_Test_Halt::class );
		$this->render();
	}

	public function test_the_list_view_prints_the_table_the_panel_and_one_nonce(): void {
		$html = $this->render();

		$this->assertStringContainsString( '<h1 class="wp-heading-inline">Trays</h1>', $html );
		$this->assertStringContainsString( 'action=add', $html );
		$this->assertStringContainsString( 'wp-list-table', $html );
		$this->assertStringContainsString( 'Microgreen Tracker Pro', $html );
		// WP_List_Table prints the bulk nonce itself; the screen must not add a second.
		$this->assertSame( [], bfmt_test_calls( 'wp_nonce_field' ) );
		$this->assertSame( 'bfmt_after_tray_list', bfmt_test_calls( 'do_action' )[0][0] );
	}

	public function test_a_stored_notice_is_shown_once_and_escaped(): void {
		$GLOBALS['bfmt_transients']['bfmt_notice_5'] = [ 'error', '<b>bad</b>' ];

		$html = $this->render();

		$this->assertStringContainsString( '<div class="notice notice-error is-dismissible"><p>&lt;b&gt;bad&lt;/b&gt;</p></div>', $html );
		$this->assertSame( [ [ 'bfmt_notice_5' ] ], bfmt_test_calls( 'delete_transient' ) );
		$this->assertStringNotContainsString( 'notice-', $this->render(), 'shown once' );
	}

	public function test_a_malformed_notice_is_ignored(): void {
		$GLOBALS['bfmt_transients']['bfmt_notice_5'] = [ 'only one part' ];

		$this->assertStringNotContainsString( 'notice-', $this->render() );
	}

	public function test_the_add_form_carries_four_fields_and_the_add_nonce(): void {
		$html = $this->render( [ 'action' => 'add' ] );

		$this->assertStringContainsString( '<h1>Add tray</h1>', $html );
		foreach ( [ 'variety', 'tray_size', 'seeding_date', 'expected_harvest_date' ] as $field ) {
			$this->assertStringContainsString( 'name="' . $field . '"', $html, $field );
		}
		$this->assertSame( [ [ 'bfmt_add_tray' ] ], bfmt_test_calls( 'wp_nonce_field' ) );
		$this->assertStringContainsString( 'type="date" id="bfmt-seeding_date"', $html );
		$this->assertStringContainsString( BFMT_Tray_Admin::harvest_help( false ), str_replace( '&#039;', "'", $html ) );
	}

	public function test_the_edit_form_is_filled_in_and_promises_nothing_about_pro(): void {
		$GLOBALS['bfmt_terms'][1] = [ (object) [ 'name' => 'Kale "Red"', 'term_id' => 7 ] ];
		update_post_meta( 1, '_mgtrack_seeding_date', '2026-09-01' );

		$html = $this->render( [ 'action' => 'edit', 'tray_id' => '1' ] );

		$this->assertStringContainsString( '<h1>Edit tray</h1>', $html );
		$this->assertStringContainsString( 'value="Kale &quot;Red&quot;"', $html );
		$this->assertStringContainsString( 'value="2026-09-01"', $html );
		$this->assertSame( [ [ 'bfmt_edit_tray_1' ] ], bfmt_test_calls( 'wp_nonce_field' ) );
		$this->assertStringNotContainsString( 'Pro', $html );
	}

	public function test_editing_something_that_is_not_a_tray_falls_back_to_the_list(): void {
		$html = $this->render( [ 'action' => 'edit', 'tray_id' => '999' ] );

		$this->assertStringContainsString( '<h1 class="wp-heading-inline">Trays</h1>', $html );
	}

	/**
	 * Pro fills a blank date only when a tray is ADDED, and only from a
	 * variety that has days to harvest. Neither form may promise more.
	 */
	public function test_the_harvest_help_promises_only_what_pro_does(): void {
		$this->assertSame( 'Optional.', BFMT_Tray_Admin::harvest_help( true ) );
		$this->assertSame(
			"Optional. Microgreen Tracker Pro can fill it in for you from each variety's days to harvest.",
			BFMT_Tray_Admin::harvest_help( false )
		);
	}

	/**
	 * Pro deactivated but its records present: the one-click actions would
	 * leave a stale yield or double-book a rack slot, so they are refused.
	 */
	public function test_a_status_action_on_a_tray_with_pro_records_is_refused(): void {
		foreach ( [ 'harvest', 'fail', 'reopen' ] as $action ) {
			bfmt_test_reset();
			$GLOBALS['bfmt_types'][1] = 'mgtrack_tray';
			$GLOBALS['bfmt_caps']     = [ 'edit_posts' => true ];
			$GLOBALS['bfmt_meta'][1]  = [ '_mgtrack_location' => '1A3', '_mgtrack_harvest_date' => '2026-09-01' ];

			$this->handle( [ 'page' => 'bfmt-trays', 'action' => $action, 'tray_id' => '1' ] );

			$this->assertSame( 'error', $this->notice()[0], $action );
			$this->assertSame( [], bfmt_test_calls( 'update_post_meta' ), $action );
			$this->assertSame( [], bfmt_test_calls( 'delete_post_meta' ), $action );
		}
	}

	public function test_failed_lite_rows_are_announced_with_a_warning(): void {
		$GLOBALS['bfmt_options']['bfmt_legacy_import'] = [ 'imported' => 4, 'skipped' => 0, 'failed' => 2 ];

		$html = $this->render();

		$this->assertStringContainsString( '<div class="notice notice-warning"><p>2 trays from Microgreen Tracker Lite could not be imported. Keep Lite installed', $html );
	}

	public function test_no_warning_when_every_lite_row_imported_or_there_was_no_import(): void {
		$this->assertStringNotContainsString( 'notice-warning', $this->render() );

		$GLOBALS['bfmt_options']['bfmt_legacy_import'] = [ 'imported' => 4, 'skipped' => 1, 'failed' => 0 ];
		$this->assertStringNotContainsString( 'notice-warning', $this->render() );
	}
}
