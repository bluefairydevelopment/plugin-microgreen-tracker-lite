<?php
/**
 * WP_List_Table subclass for the Grow Tracker admin list view.
 *
 * @package BlueFairy\MicrogreenTrackerLite
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// WP_List_Table is not auto-loaded on the front end or in some admin contexts.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class BFMTL_Grow_List_Table extends WP_List_Table {

	/** @var array<int, object> */
	private array $raw_items = [];

	public function __construct() {
		parent::__construct( [
			'singular' => 'grow',
			'plural'   => 'grows',
			'ajax'     => false,
		] );
	}

	// -------------------------------------------------------------------------
	// Column definitions
	// -------------------------------------------------------------------------

	/**
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return [
			'cb'                    => '<input type="checkbox">',
			'variety'               => __( 'Variety', 'bluefairy-microgreen-tracker-lite' ),
			'tray_size'             => __( 'Tray Size', 'bluefairy-microgreen-tracker-lite' ),
			'seeded_date'           => __( 'Seeded Date', 'bluefairy-microgreen-tracker-lite' ),
			'expected_harvest_date' => __( 'Expected Harvest', 'bluefairy-microgreen-tracker-lite' ),
			'status'                => __( 'Status', 'bluefairy-microgreen-tracker-lite' ),
			'actions_col'           => __( 'Actions', 'bluefairy-microgreen-tracker-lite' ),
		];
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public function get_sortable_columns(): array {
		return [
			'variety'               => [ 'variety', false ],
			'seeded_date'           => [ 'seeded_date', true ],
			'expected_harvest_date' => [ 'expected_harvest_date', false ],
			'status'                => [ 'status', false ],
		];
	}

	/**
	 * @return array<string, string>
	 */
	protected function get_bulk_actions(): array {
		return [
			'bulk_delete' => __( 'Delete', 'bluefairy-microgreen-tracker-lite' ),
		];
	}

	// -------------------------------------------------------------------------
	// Column renderers
	// -------------------------------------------------------------------------

	/**
	 * Checkbox column.
	 *
	 * @param object $item
	 * @return string
	 */
	public function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="grow_ids[]" value="%d">',
			absint( $item->id )
		);
	}

	/**
	 * Fallback column renderer.
	 *
	 * @param object $item
	 * @param string $column_name
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'variety':
				return esc_html( $item->variety );

			case 'tray_size':
				return $item->tray_size !== '' ? esc_html( $item->tray_size ) : '&mdash;';

			case 'seeded_date':
				return esc_html( $item->seeded_date );

			case 'expected_harvest_date':
				return $item->expected_harvest_date ? esc_html( $item->expected_harvest_date ) : '&mdash;';

			case 'status':
				return $this->status_badge( $item->status );

			case 'actions_col':
				return $this->row_actions_html( $item );

			default:
				return '';
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Render a coloured status badge.
	 */
	private function status_badge( string $status ): string {
		$colours = [
			'active'    => '#166534;background:#dcfce7',
			'harvested' => '#1e40af;background:#dbeafe',
			'failed'    => '#991b1b;background:#fee2e2',
		];
		$labels = [
			'active'    => __( 'Active', 'bluefairy-microgreen-tracker-lite' ),
			'harvested' => __( 'Harvested', 'bluefairy-microgreen-tracker-lite' ),
			'failed'    => __( 'Failed', 'bluefairy-microgreen-tracker-lite' ),
		];

		$style = isset( $colours[ $status ] )
			? 'color:' . $colours[ $status ] . ';padding:2px 8px;border-radius:3px;font-size:.8em;font-weight:600;display:inline-block;'
			: '';
		$label = $labels[ $status ] ?? esc_html( $status );

		return sprintf(
			'<span style="%s">%s</span>',
			esc_attr( $style ),
			esc_html( $label )
		);
	}

	/**
	 * Render Edit | Delete action links for a row.
	 */
	private function row_actions_html( object $item ): string {
		$id       = absint( $item->id );
		$page_url = admin_url( 'options-general.php?page=bfmtl-grow-tracker' );

		$edit_url   = add_query_arg( [ 'action' => 'edit', 'grow_id' => $id ], $page_url );
		$delete_url = wp_nonce_url(
			add_query_arg( [ 'action' => 'delete', 'grow_id' => $id ], $page_url ),
			'bfmtl_delete_grow_' . $id
		);

		return sprintf(
			'<a href="%s">%s</a> | <a href="%s" onclick="return confirm(\'%s\')">%s</a>',
			esc_url( $edit_url ),
			esc_html__( 'Edit', 'bluefairy-microgreen-tracker-lite' ),
			esc_url( $delete_url ),
			esc_js( __( 'Delete this grow record? This cannot be undone.', 'bluefairy-microgreen-tracker-lite' ) ),
			esc_html__( 'Delete', 'bluefairy-microgreen-tracker-lite' )
		);
	}

	// -------------------------------------------------------------------------
	// Data preparation
	// -------------------------------------------------------------------------

	/**
	 * Load, sort, paginate, and assign items.
	 */
	public function prepare_items(): void {
		$this->raw_items = BFMTL_DB::get_all();

		// Sorting.
		$orderby  = sanitize_key( $_GET['orderby'] ?? 'seeded_date' );
		$order    = strtoupper( sanitize_key( $_GET['order'] ?? 'desc' ) ) === 'ASC' ? 'ASC' : 'DESC';
		$allowed  = [ 'variety', 'seeded_date', 'expected_harvest_date', 'status' ];
		if ( ! in_array( $orderby, $allowed, true ) ) {
			$orderby = 'seeded_date';
		}

		usort( $this->raw_items, static function ( object $a, object $b ) use ( $orderby, $order ): int {
			$va = (string) ( $a->$orderby ?? '' );
			$vb = (string) ( $b->$orderby ?? '' );
			$cmp = strcmp( $va, $vb );
			return $order === 'ASC' ? $cmp : -$cmp;
		} );

		// Pagination.
		$per_page     = 25;
		$total_items  = count( $this->raw_items );
		$current_page = $this->get_pagenum();

		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total_items / $per_page ),
		] );

		$offset      = ( $current_page - 1 ) * $per_page;
		$this->items = array_slice( $this->raw_items, $offset, $per_page );

		// Column headers.
		$columns               = $this->get_columns();
		$sortable              = $this->get_sortable_columns();
		$hidden                = [];
		$this->_column_headers = [ $columns, $hidden, $sortable ];
	}
}
