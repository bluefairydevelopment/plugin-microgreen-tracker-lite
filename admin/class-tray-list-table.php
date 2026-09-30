<?php
/**
 * The tray list.
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class BFMT_Tray_List_Table extends WP_List_Table {

	private const PER_PAGE = 25;

	public function __construct() {
		parent::__construct( [ 'singular' => 'tray', 'plural' => 'trays', 'ajax' => false ] );
	}

	/**
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return [
			'cb'                    => '<input type="checkbox">',
			'variety'               => __( 'Variety', 'bluefairy-microgreen-tracker' ),
			'tray_size'             => __( 'Tray size', 'bluefairy-microgreen-tracker' ),
			'seeding_date'          => __( 'Seeded', 'bluefairy-microgreen-tracker' ),
			'expected_harvest_date' => __( 'Expected harvest', 'bluefairy-microgreen-tracker' ),
			'status'                => __( 'Status', 'bluefairy-microgreen-tracker' ),
			'row_actions_col'       => __( 'Actions', 'bluefairy-microgreen-tracker' ),
		];
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public function get_sortable_columns(): array {
		return [
			'variety'      => [ 'variety', false ],
			'seeding_date' => [ 'seeding_date', true ],
		];
	}

	/**
	 * @return array<string, string>
	 */
	protected function get_bulk_actions(): array {
		return [ 'bulk_trash' => __( 'Move to Trash', 'bluefairy-microgreen-tracker' ) ];
	}

	/**
	 * @param WP_Post $item
	 */
	public function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="tray_ids[]" value="%d">', absint( $item->ID ) );
	}

	/**
	 * @param WP_Post $item
	 * @param string  $column_name
	 */
	public function column_default( $item, $column_name ): string {
		$id = (int) $item->ID;

		switch ( $column_name ) {
			case 'variety':
				$variety = BFMT_Tray::variety( $id );

				return '' !== $variety ? esc_html( $variety ) : '&mdash;';

			case 'tray_size':
			case 'seeding_date':
			case 'expected_harvest_date':
				$value = (string) BFMT_Tray::get( $id, $column_name );

				return '' !== $value ? esc_html( $value ) : '&mdash;';

			case 'status':
				return BFMT_Status::badge( BFMT_Status::get( $id ) );

			case 'row_actions_col':
				return $this->actions_for( $id );

			default:
				return '';
		}
	}

	private function actions_for( int $tray_id ): string {
		$links = [
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( add_query_arg( [ 'action' => 'edit', 'tray_id' => $tray_id ], self::page_url() ) ),
				esc_html__( 'Edit', 'bluefairy-microgreen-tracker' )
			),
		];

		// A growing tray can be finished either way; a finished one can be put
		// back, which is the undo for a mis-tap.
		$status_actions = BFMT_Status::is_finished( $tray_id )
			? [ 'reopen' => __( 'Mark growing', 'bluefairy-microgreen-tracker' ) ]
			: [
				'harvest' => __( 'Mark harvested', 'bluefairy-microgreen-tracker' ),
				'fail'    => __( 'Mark failed', 'bluefairy-microgreen-tracker' ),
			];

		foreach ( $status_actions as $action => $label ) {
			$links[] = sprintf(
				'<a href="%s" class="bfmt-%s">%s</a>',
				esc_url( self::action_url( $action, $tray_id ) ),
				esc_attr( $action ),
				esc_html( $label )
			);
		}

		$links[] = sprintf(
			'<a href="%s" data-bfmt-confirm="%s">%s</a>',
			esc_url( self::action_url( 'trash', $tray_id ) ),
			esc_attr__( 'Move this tray to the trash?', 'bluefairy-microgreen-tracker' ),
			esc_html__( 'Trash', 'bluefairy-microgreen-tracker' )
		);

		return implode( ' | ', $links );
	}

	private static function page_url(): string {
		return admin_url( 'admin.php?page=' . BFMT_Tray_Admin::PAGE_SLUG );
	}

	/**
	 * A single-tray action link, carrying a nonce keyed to the action and the tray.
	 */
	public static function action_url( string $action, int $tray_id ): string {
		return wp_nonce_url(
			add_query_arg( [ 'action' => $action, 'tray_id' => $tray_id ], self::page_url() ),
			'bfmt_' . $action . '_tray_' . $tray_id
		);
	}

	/**
	 * Query one page of trays.
	 *
	 * The old free plugin's list loaded every row, sorted the whole set in PHP
	 * and then sliced 25 out of it — while its readme advertised "no record
	 * cap". Sorting and paging happen in the query here.
	 */
	public function prepare_items(): void {
		// Reading a sort order to build a query is not a state change, so there
		// is nothing here for a nonce to protect.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'seeding_date';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order = ( isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ) ? 'ASC' : 'DESC';

		$args = [
			'post_type'      => BFMT_Tray::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $this->get_pagenum(),
		];

		if ( 'variety' === $orderby ) {
			// The title is "Variety — date", so ordering by title orders by
			// variety, case-insensitively, in the database. The old list used
			// strcmp, which sorted "Zucchini" above "amaranth".
			$args['orderby'] = 'title';
			$args['order']   = $order;
		} else {
			// Sort by the seeded date WITHOUT requiring one. A plain meta_key
			// orderby inner-joins the meta table and silently drops every tray
			// that has no seeded date — which is how a Pro tray started with a
			// soak goes missing from "every tray in one list". The OR with NOT
			// EXISTS keeps those rows; they sort as empty.
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- sorting by the seeded date is the point of the screen.
			$args['meta_query'] = [
				'relation' => 'OR',
				'seeded'   => [ 'key' => '_mgtrack_seeding_date', 'compare' => 'EXISTS' ],
				'unseeded' => [ 'key' => '_mgtrack_seeding_date', 'compare' => 'NOT EXISTS' ],
			];
			$args['orderby'] = [ 'seeded' => $order, 'ID' => $order ];
		}

		$query = new WP_Query( $args );

		$this->items = $query->posts;

		$this->set_pagination_args( [
			'total_items' => (int) $query->found_posts,
			'per_page'    => self::PER_PAGE,
			'total_pages' => (int) $query->max_num_pages,
		] );

		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
	}

	public function no_items(): void {
		esc_html_e( 'No trays yet. Add your first one to start the log.', 'bluefairy-microgreen-tracker' );
	}
}
