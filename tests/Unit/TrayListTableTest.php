<?php
/**
 * The tray list: columns, row actions, and the query behind a page.
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TrayListTableTest extends TestCase {

	protected function setUp(): void {
		bfmt_test_reset();
		$GLOBALS['bfmt_types'][7] = 'mgtrack_tray';
	}

	private function cell( string $column, int $id = 7 ): string {
		$item     = new stdClass();
		$item->ID = $id;

		return ( new BFMT_Tray_List_Table() )->column_default( $item, $column );
	}

	public function test_the_columns(): void {
		$this->assertSame(
			[ 'cb', 'variety', 'tray_size', 'seeding_date', 'expected_harvest_date', 'status', 'row_actions_col' ],
			array_keys( ( new BFMT_Tray_List_Table() )->get_columns() )
		);
	}

	public function test_variety_and_seeded_date_sort_and_seeded_starts_descending(): void {
		$this->assertSame(
			[ 'variety' => [ 'variety', false ], 'seeding_date' => [ 'seeding_date', true ] ],
			( new BFMT_Tray_List_Table() )->get_sortable_columns()
		);
	}

	public function test_the_only_bulk_action_is_move_to_trash(): void {
		$method = new ReflectionMethod( BFMT_Tray_List_Table::class, 'get_bulk_actions' );

		$this->assertSame( [ 'bulk_trash' => 'Move to Trash' ], $method->invoke( new BFMT_Tray_List_Table() ) );
	}

	public function test_the_checkbox_carries_the_tray_id(): void {
		$item     = new stdClass();
		$item->ID = 7;

		$this->assertSame( '<input type="checkbox" name="tray_ids[]" value="7">', ( new BFMT_Tray_List_Table() )->column_cb( $item ) );
	}

	public function test_text_cells_are_escaped_and_empty_ones_show_a_dash(): void {
		$GLOBALS['bfmt_terms'][7] = [ (object) [ 'name' => '<i>Kale</i>', 'term_id' => 1 ] ];
		update_post_meta( 7, '_mgtrack_tray_size', '10x20 <b>' );

		$this->assertSame( '&lt;i&gt;Kale&lt;/i&gt;', $this->cell( 'variety' ) );
		$this->assertSame( '10x20 &lt;b&gt;', $this->cell( 'tray_size' ) );
		$this->assertSame( '&mdash;', $this->cell( 'expected_harvest_date' ) );
		$this->assertSame( '', $this->cell( 'no_such_column' ) );
	}

	public function test_the_status_column_is_a_class_styled_badge(): void {
		$this->assertSame( '<span class="bfmt-badge bfmt-badge--active">Active</span>', $this->cell( 'status' ) );
	}

	public function test_the_edit_link_opens_this_trays_form(): void {
		$this->assertMatchesRegularExpression( '#<a href="[^"]*page=bfmt-trays&amp;action=edit&amp;tray_id=7" aria-label="[^"]*">Edit</a>#', $this->cell( 'row_actions_col' ) );
	}

	public function test_the_trash_link_asks_through_a_data_attribute_not_inline_script(): void {
		$actions = $this->cell( 'row_actions_col' );

		$this->assertStringContainsString( 'data-bfmt-confirm="Move this tray to the trash?"', $actions );
		$this->assertStringNotContainsString( 'onclick', $actions );
		$this->assertStringContainsString( '_wpnonce=test-bfmt_trash_tray_7', $actions );
	}

	public function test_a_growing_tray_offers_harvested_and_failed_each_with_its_own_nonce(): void {
		$actions = $this->cell( 'row_actions_col' );

		$this->assertMatchesRegularExpression( '#action=harvest&amp;tray_id=7&amp;_wpnonce=test-bfmt_harvest_tray_7" aria-label="[^"]*" class="bfmt-harvest">Mark harvested</a>#', $actions );
		$this->assertMatchesRegularExpression( '#action=fail&amp;tray_id=7&amp;_wpnonce=test-bfmt_fail_tray_7" aria-label="[^"]*" class="bfmt-fail">Mark failed</a>#', $actions );
		$this->assertStringNotContainsString( 'Mark growing', $actions );
	}

	public function test_a_finished_tray_offers_only_the_undo(): void {
		update_post_meta( 7, '_mgtrack_harvest_date', '2026-09-01' );
		$harvested = $this->cell( 'row_actions_col' );

		$GLOBALS['bfmt_meta'] = [];
		update_post_meta( 7, '_mgtrack_failed', '2026-09-02' );
		$failed = $this->cell( 'row_actions_col' );

		foreach ( [ $harvested, $failed ] as $actions ) {
			$this->assertMatchesRegularExpression( '#_wpnonce=test-bfmt_reopen_tray_7" aria-label="[^"]*" class="bfmt-reopen">Mark growing</a>#', $actions );
			$this->assertStringNotContainsString( 'Mark harvested', $actions );
			$this->assertStringNotContainsString( 'Mark failed', $actions );
		}
	}

	public function test_the_action_url_carries_the_action_the_tray_and_a_matching_nonce(): void {
		$this->assertSame(
			'https://example.test/wp-admin/admin.php?page=bfmt-trays&action=fail&tray_id=3&_wpnonce=test-bfmt_fail_tray_3',
			BFMT_Tray_List_Table::action_url( 'fail', 3 )
		);
	}

	/**
	 * The default sort must not require a seeded date. A plain meta_key
	 * orderby inner-joins the meta table and drops every tray without one.
	 */
	public function test_the_default_sort_keeps_trays_without_a_seeded_date(): void {
		( new BFMT_Tray_List_Table() )->prepare_items();

		$args = $GLOBALS['bfmt_query_args'];
		$this->assertArrayNotHasKey( 'meta_key', $args );
		$this->assertSame( 'OR', $args['meta_query']['relation'] );
		$this->assertSame( [ 'key' => '_mgtrack_seeding_date', 'compare' => 'EXISTS' ], $args['meta_query']['seeded'] );
		$this->assertSame( [ 'key' => '_mgtrack_seeding_date', 'compare' => 'NOT EXISTS' ], $args['meta_query']['unseeded'] );
		// Ordered by the NOT EXISTS clause, whose join is keyed to the seeded
		// date — the EXISTS clause's join is not, under an OR.
		$this->assertSame( [ 'unseeded' => 'DESC', 'ID' => 'DESC' ], $args['orderby'] );
	}

	public function test_a_page_is_one_query_of_twenty_five(): void {
		$GLOBALS['bfmt_query_result'] = [ 'posts' => [ 'a', 'b' ], 'found_posts' => 60, 'max_num_pages' => 3 ];
		$table          = new BFMT_Tray_List_Table();
		$table->pagenum = 2;

		$table->prepare_items();

		$args = $GLOBALS['bfmt_query_args'];
		$this->assertSame( 'mgtrack_tray', $args['post_type'] );
		$this->assertSame( 'publish', $args['post_status'] );
		$this->assertSame( 25, $args['posts_per_page'] );
		$this->assertSame( 2, $args['paged'] );
		$this->assertSame( [ 'a', 'b' ], $table->items );
		$this->assertSame( [ 'total_items' => 60, 'per_page' => 25, 'total_pages' => 3 ], $table->pagination_args );
	}

	public function test_sorting_by_variety_sorts_by_title_in_the_query(): void {
		bfmt_test_request( [ 'orderby' => 'variety', 'order' => 'asc' ] );

		( new BFMT_Tray_List_Table() )->prepare_items();

		$this->assertSame( 'title', $GLOBALS['bfmt_query_args']['orderby'] );
		$this->assertSame( 'ASC', $GLOBALS['bfmt_query_args']['order'] );
		$this->assertArrayNotHasKey( 'meta_query', $GLOBALS['bfmt_query_args'] );
	}

	public function test_an_unknown_order_is_descending(): void {
		bfmt_test_request( [ 'order' => 'sideways' ] );

		( new BFMT_Tray_List_Table() )->prepare_items();

		$this->assertSame( [ 'unseeded' => 'DESC', 'ID' => 'DESC' ], $GLOBALS['bfmt_query_args']['orderby'] );
	}

	public function test_the_empty_state(): void {
		ob_start();
		( new BFMT_Tray_List_Table() )->no_items();

		$this->assertSame( 'No trays yet. Add your first one to start the log.', ob_get_clean() );
	}

	/**
	 * Every link names its tray for a screen reader, because every row repeats
	 * the same link words.
	 */
	public function test_every_row_link_names_its_tray(): void {
		$GLOBALS['bfmt_terms'][7] = [ (object) [ 'name' => 'Sunflower', 'term_id' => 1 ] ];
		update_post_meta( 7, '_mgtrack_seeding_date', '2026-09-24' );

		$actions = $this->cell( 'row_actions_col' );

		foreach ( [ 'Edit', 'Mark harvested', 'Mark failed', 'Trash' ] as $label ) {
			$this->assertStringContainsString( 'aria-label="' . $label . ': Sunflower, seeded 2026-09-24"', $actions, $label );
		}
		$this->assertSame( 4, substr_count( $actions, 'aria-label=' ) );
	}

	/**
	 * A hostile translation comes out escaped in the label, the link text and
	 * the confirm attribute.
	 */
	public function test_translated_link_text_is_escaped(): void {
		$GLOBALS['bfmt_filters']['gettext'] = static fn( string $t ): string => str_replace( [ 'Mark', 'Move' ], [ '<b>"Mark', '<b>"Move' ], $t );

		$actions = $this->cell( 'row_actions_col' );

		$this->assertStringNotContainsString( '<b>', $actions );
		$this->assertStringContainsString( '&lt;b&gt;&quot;Mark harvested</a>', $actions );
		$this->assertStringContainsString( 'aria-label="&lt;b&gt;&quot;Mark harvested:', $actions );
		$this->assertStringContainsString( 'data-bfmt-confirm="&lt;b&gt;&quot;Move this tray to the trash?"', $actions );
	}

	public function test_ascending_order_also_breaks_ties_ascending(): void {
		bfmt_test_request( [ 'order' => 'asc' ] );

		( new BFMT_Tray_List_Table() )->prepare_items();

		$this->assertSame( [ 'unseeded' => 'ASC', 'ID' => 'ASC' ], $GLOBALS['bfmt_query_args']['orderby'] );
	}

	public function test_the_label_leaves_out_a_missing_date_and_names_a_tray_with_no_variety(): void {
		$GLOBALS['bfmt_terms'][7] = [ (object) [ 'name' => 'Sunflower', 'term_id' => 1 ] ];
		$this->assertStringContainsString( 'aria-label="Edit: Sunflower"', $this->cell( 'row_actions_col' ) );

		$GLOBALS['bfmt_terms'] = [];
		update_post_meta( 7, '_mgtrack_seeding_date', '2026-09-24' );
		$this->assertStringContainsString( 'aria-label="Edit: Tray 7, seeded 2026-09-24"', $this->cell( 'row_actions_col' ) );
	}

	public function test_a_tray_pro_finished_offers_no_way_back(): void {
		update_post_meta( 7, '_mgtrack_harvest_date', '2026-09-01' );
		update_post_meta( 7, '_mgtrack_yield_weight', '0' );

		$this->assertSame( [], BFMT_Tray_List_Table::status_actions( 7 ) );
		$this->assertStringNotContainsString( 'Mark ', $this->cell( 'row_actions_col' ) );
	}

	public function test_a_tray_finished_here_with_a_free_slot_can_be_put_back(): void {
		update_post_meta( 7, '_mgtrack_location', '1A3' );
		update_post_meta( 7, '_mgtrack_failed', '2026-09-01' );
		$GLOBALS['bfmt_posts_result'] = [];

		$this->assertSame( [ 'reopen' ], array_keys( BFMT_Tray_List_Table::status_actions( 7 ) ) );
	}

	public function test_a_growing_tray_with_a_rack_slot_can_still_be_finished(): void {
		update_post_meta( 7, '_mgtrack_location', '1A3' );

		$this->assertSame( [ 'harvest', 'fail' ], array_keys( BFMT_Tray_List_Table::status_actions( 7 ) ) );
	}
}
