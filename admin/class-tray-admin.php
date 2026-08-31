<?php
/**
 * The Microgreens screen: list, add, edit, trash.
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

class BFMT_Tray_Admin {

	public const PAGE_SLUG  = 'bfmt-trays';
	public const CAPABILITY = 'edit_posts';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
		add_action( 'admin_init', [ __CLASS__, 'handle_actions' ] );
	}

	/**
	 * A top-level menu.
	 *
	 * 1.0.0 put this under Settings. It is a place to keep records, not a
	 * setting, and burying it there cost the free plugin its one screen.
	 */
	public static function register_menu(): void {
		add_menu_page(
			__( 'Microgreens', 'bluefairy-microgreen-tracker' ),
			__( 'Microgreens', 'bluefairy-microgreen-tracker' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			[ __CLASS__, 'render' ],
			'dashicons-carrot',
			26
		);

		do_action( 'bfmt_admin_menu', self::PAGE_SLUG, self::CAPABILITY );
	}

	private static function page_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	public static function handle_actions(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- deciding whether this request is ours; each branch below verifies its own nonce.
		if ( ! isset( $_REQUEST['page'] ) || self::PAGE_SLUG !== sanitize_key( wp_unslash( $_REQUEST['page'] ) ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see above.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		if ( 'trash' === $action ) {
			self::do_trash();
		} elseif ( 'bulk_trash' === $action ) {
			self::do_bulk_trash();
		} elseif ( 'save' === $action ) {
			self::do_save();
		}
	}

	private static function guard(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage trays.', 'bluefairy-microgreen-tracker' ) );
		}
	}

	private static function do_trash(): void {
		self::guard();

		$tray_id = isset( $_GET['tray_id'] ) ? absint( wp_unslash( $_GET['tray_id'] ) ) : 0;

		check_admin_referer( 'bfmt_trash_tray_' . $tray_id );

		$notice = BFMT_Tray::trash( $tray_id )
			? [ 'updated', __( 'Tray moved to the trash.', 'bluefairy-microgreen-tracker' ) ]
			: [ 'error', __( 'That tray could not be found.', 'bluefairy-microgreen-tracker' ) ];

		self::redirect_with_notice( $notice );
	}

	private static function do_bulk_trash(): void {
		self::guard();
		check_admin_referer( 'bulk-trays' );

		$ids = isset( $_POST['tray_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['tray_ids'] ) ) : [];
		$moved = 0;

		foreach ( array_filter( $ids ) as $id ) {
			if ( BFMT_Tray::trash( $id ) ) {
				++$moved;
			}
		}

		self::redirect_with_notice( [
			'updated',
			sprintf(
				/* translators: %d: number of trays */
				_n( '%d tray moved to the trash.', '%d trays moved to the trash.', $moved, 'bluefairy-microgreen-tracker' ),
				$moved
			),
		] );
	}

	/**
	 * Save, and say what actually happened.
	 *
	 * 1.0.0 discarded the return value of its insert and reported success
	 * unconditionally, so a date the database rejected was reported as "Grow
	 * record added."
	 */
	private static function do_save(): void {
		self::guard();

		$tray_id = isset( $_POST['tray_id'] ) ? absint( wp_unslash( $_POST['tray_id'] ) ) : 0;

		check_admin_referer( $tray_id > 0 ? 'bfmt_edit_tray_' . $tray_id : 'bfmt_add_tray' );

		$fields = [
			'variety'               => isset( $_POST['variety'] ) ? sanitize_text_field( wp_unslash( $_POST['variety'] ) ) : '',
			'tray_size'             => isset( $_POST['tray_size'] ) ? sanitize_text_field( wp_unslash( $_POST['tray_size'] ) ) : '',
			'seeding_date'          => isset( $_POST['seeding_date'] ) ? sanitize_text_field( wp_unslash( $_POST['seeding_date'] ) ) : '',
			'expected_harvest_date' => isset( $_POST['expected_harvest_date'] ) ? sanitize_text_field( wp_unslash( $_POST['expected_harvest_date'] ) ) : '',
		];

		$result = $tray_id > 0
			? BFMT_Tray::update( $tray_id, $fields )
			: BFMT_Tray::create( $fields );

		if ( is_wp_error( $result ) ) {
			self::redirect_with_notice(
				[ 'error', $result->get_error_message() ],
				$tray_id > 0 ? [ 'action' => 'edit', 'tray_id' => $tray_id ] : [ 'action' => 'add' ]
			);
		}

		self::redirect_with_notice( [
			'updated',
			$tray_id > 0
				? __( 'Tray updated.', 'bluefairy-microgreen-tracker' )
				: __( 'Tray added.', 'bluefairy-microgreen-tracker' ),
		] );
	}

	/**
	 * @param array{0:string,1:string}   $notice
	 * @param array<string, string|int>  $args
	 */
	private static function redirect_with_notice( array $notice, array $args = [] ): void {
		set_transient( 'bfmt_notice_' . get_current_user_id(), $notice, 30 );
		wp_safe_redirect( add_query_arg( $args, self::page_url() ) );
		exit;
	}

	private static function show_notice(): void {
		$notice = get_transient( 'bfmt_notice_' . get_current_user_id() );

		if ( ! is_array( $notice ) || 2 !== count( $notice ) ) {
			return;
		}

		delete_transient( 'bfmt_notice_' . get_current_user_id() );

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( 'error' === $notice[0] ? 'error' : 'success' ),
			esc_html( $notice[1] )
		);
	}

	public static function render(): void {
		self::guard();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- choosing which read-only view to draw.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see above.
		$tray_id = isset( $_GET['tray_id'] ) ? absint( wp_unslash( $_GET['tray_id'] ) ) : 0;

		echo '<div class="wrap">';
		self::show_notice();

		if ( 'add' === $action || ( 'edit' === $action && BFMT_Tray::is_tray( $tray_id ) ) ) {
			self::render_form( 'edit' === $action ? $tray_id : 0 );
		} else {
			self::render_list();
		}

		echo '</div>';
	}

	private static function render_list(): void {
		$table = new BFMT_Tray_List_Table();
		$table->prepare_items();

		printf(
			'<h1 class="wp-heading-inline">%s</h1> <a href="%s" class="page-title-action">%s</a><hr class="wp-header-end">',
			esc_html__( 'Trays', 'bluefairy-microgreen-tracker' ),
			esc_url( add_query_arg( 'action', 'add', self::page_url() ) ),
			esc_html__( 'Add tray', 'bluefairy-microgreen-tracker' )
		);

		echo '<form method="post">';
		wp_nonce_field( 'bulk-trays' );
		printf( '<input type="hidden" name="page" value="%s">', esc_attr( self::PAGE_SLUG ) );
		$table->display();
		echo '</form>';

		do_action( 'bfmt_after_tray_list' );

		BFMT_Pro_Upsell::render_panel();
	}

	private static function render_form( int $tray_id ): void {
		$editing  = $tray_id > 0;
		$variety  = $editing ? BFMT_Tray::variety( $tray_id ) : '';
		$size     = $editing ? (string) BFMT_Tray::get( $tray_id, 'tray_size' ) : '';
		$seeded   = $editing ? (string) BFMT_Tray::get( $tray_id, 'seeding_date' ) : '';
		$expected = $editing ? (string) BFMT_Tray::get( $tray_id, 'expected_harvest_date' ) : '';

		printf(
			'<h1>%s</h1>',
			esc_html( $editing
				? __( 'Edit tray', 'bluefairy-microgreen-tracker' )
				: __( 'Add tray', 'bluefairy-microgreen-tracker' ) )
		);

		echo '<form method="post">';
		wp_nonce_field( $editing ? 'bfmt_edit_tray_' . $tray_id : 'bfmt_add_tray' );
		printf( '<input type="hidden" name="page" value="%s">', esc_attr( self::PAGE_SLUG ) );
		echo '<input type="hidden" name="action" value="save">';
		printf( '<input type="hidden" name="tray_id" value="%d">', absint( $tray_id ) );

		echo '<table class="form-table" role="presentation"><tbody>';
		self::field( 'variety', __( 'Variety', 'bluefairy-microgreen-tracker' ), $variety, 'text', __( 'Required.', 'bluefairy-microgreen-tracker' ) );
		self::field( 'tray_size', __( 'Tray size', 'bluefairy-microgreen-tracker' ), $size, 'text', __( 'Whatever you call it — 10×20, half tray, and so on.', 'bluefairy-microgreen-tracker' ) );
		self::field( 'seeding_date', __( 'Seeded', 'bluefairy-microgreen-tracker' ), $seeded, 'date', __( 'Required.', 'bluefairy-microgreen-tracker' ) );
		self::field(
			'expected_harvest_date',
			__( 'Expected harvest', 'bluefairy-microgreen-tracker' ),
			$expected,
			'date',
			BFMT_Pro_Upsell::pro_is_active()
				? __( 'Left blank, Pro works this out from the variety.', 'bluefairy-microgreen-tracker' )
				: __( 'Optional. Microgreen Tracker Pro works this out for you from the variety.', 'bluefairy-microgreen-tracker' )
		);
		echo '</tbody></table>';

		do_action( 'bfmt_tray_form_fields', $tray_id );

		submit_button( $editing
			? __( 'Update tray', 'bluefairy-microgreen-tracker' )
			: __( 'Add tray', 'bluefairy-microgreen-tracker' ) );

		echo '</form>';
	}

	private static function field( string $name, string $label, string $value, string $type, string $help ): void {
		printf(
			'<tr><th scope="row"><label for="bfmt-%1$s">%2$s</label></th><td>'
			. '<input type="%3$s" id="bfmt-%1$s" name="%1$s" value="%4$s" class="regular-text">'
			. '<p class="description">%5$s</p></td></tr>',
			esc_attr( $name ),
			esc_html( $label ),
			esc_attr( $type ),
			esc_attr( $value ),
			esc_html( $help )
		);
	}
}
