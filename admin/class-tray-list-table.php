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
		// Each row repeats the same link words, so each link also names its
		// tray for a screen reader: "Mark harvested: Sunflower, seeded 2026-09-24".
		$variety = BFMT_Tray::variety( $tray_id );
		$seeded  = (string) BFMT_Tray::get( $tray_id, 'seeding_date' );

		if ( '' === $variety ) {
			/* translators: %d: tray id */
			$variety = sprintf( __( 'Tray %d', 'bluefairy-microgreen-tracker' ), $tray_id );
		}

		$tray = '' === $seeded
			? $variety
			: sprintf(
				/* translators: 1: variety, 2: seeded date */
				__( '%1$s, seeded %2$s', 'bluefairy-microgreen-tracker' ),
				$variety,
				$seeded
			);

		$link = static fn( string $url, string $label, string $attrs = '' ): string => sprintf(
			'<a href="%s" aria-label="%s"%s>%s</a>',
			esc_url( $url ),
			/* translators: 1: action, e.g. "Mark harvested"; 2: the tray */
			esc_attr( sprintf( __( '%1$s: %2$s', 'bluefairy-microgreen-tracker' ), $label, $tray ) ),
			$attrs,
			esc_html( $label )
		);

		$links = [
			$link(
				add_query_arg( [ 'action' => 'edit', 'tray_id' => $tray_id ], self::page_url() ),
				__( 'Edit', 'bluefairy-microgreen-tracker' )
			),
		];

		foreach ( self::status_actions( $tray_id ) as $action => $label ) {
			$links[] = $link( self::action_url( $action, $tray_id ), $label, sprintf( ' class="bfmt-%s"', esc_attr( $action ) ) );
		}

		$links[] = $link(
			self::action_url( 'trash', $tray_id ),
			__( 'Trash', 'bluefairy-microgreen-tracker' ),
			sprintf( ' data-bfmt-confirm="%s"', esc_attr__( 'Move this tray to the trash?', 'bluefairy-microgreen-tracker' ) )
		);

		return implode( ' | ', $links );
	}

	/**
	 * The status links a row offers.
	 *
	 * None while Pro is active: Pro records a harvest with its yield and sale,
	 * a failure with its reason, and frees or re-checks a rack slot as a tray
	 * finishes or comes back. A one-click status change here would skip all of
	 * that, so with Pro on, trays are finished from Pro's own screens.
	 *
	 * Otherwise a growing tray can be finished either way — finishing frees
	 * any rack slot in Pro's own reckoning, so it corrupts nothing. A finished
	 * tray can be put back (the undo for a mis-tap) unless Pro finished it:
	 * reopening would keep Pro's yield or reason and could put two trays in
	 * one slot.
	 *
	 * @return array<string, string> action => label
	 */
	public static function status_actions( int $tray_id ): array {
		if ( BFMT_Pro_Upsell::pro_is_active() ) {
			return [];
		}

		if ( BFMT_Status::is_finished( $tray_id ) ) {
			return BFMT_Status::has_pro_record( $tray_id )
				? []
				: [ 'reopen' => __( 'Mark growing', 'bluefairy-microgreen-tracker' ) ];
		}

		return [
				'harvest' => __( 'Mark harvested', 'bluefairy-microgreen-tracker' ),
				'fail'    => __( 'Mark failed', 'bluefairy-microgreen-tracker' ),
			];
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
	 * Sorting and paging happen in the query, never in PHP, so a page costs
	 * the same however many trays have been logged.
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
			// variety, case-insensitively, in the database ("amaranth" before
			// "Zucchini", as a person would expect).
			$args['orderby'] = 'title';
			$args['order']   = $order;
		} else {
			// Sort by the seeded date WITHOUT requiring one. A plain meta_key
			// orderby inner-joins the meta table and drops every tray that has
			// no seeded date (a Pro tray started with a soak). The OR with NOT
			// EXISTS keeps those rows.
			//
			// Order by the NOT EXISTS clause, not the EXISTS one: its join is
			// keyed to _mgtrack_seeding_date, so its value is the seeded date or
			// NULL. The EXISTS clause's join carries no key condition under an
			// OR, and ordering by it sorts an unseeded tray by whatever other
			// meta row it happens to have. NULLs sort last descending.
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- sorting by the seeded date is the point of the screen.
			$args['meta_query'] = [
				'relation' => 'OR',
				'seeded'   => [ 'key' => '_mgtrack_seeding_date', 'compare' => 'EXISTS' ],
				'unseeded' => [ 'key' => '_mgtrack_seeding_date', 'compare' => 'NOT EXISTS' ],
			];
			$args['orderby'] = [ 'unseeded' => $order, 'ID' => $order ];
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
