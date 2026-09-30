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

	/**
	 * Who may use the screen, and every action on it.
	 *
	 * The tray log is the farm's shared record, not per-author content: anyone
	 * trusted to log a tray may correct, finish or trash any tray, which is how
	 * a grow room works. So every action checks this capability, a nonce keyed
	 * to the action (and to the tray, for single-tray actions), and that the id
	 * really is a tray. Trays are never public — the post type is registered
	 * with public => false — and trashing is recoverable.
	 */
	public const CAPABILITY = 'edit_posts';

	/** Single-tray actions that change status, and the nonce each carries. */
	public const STATUS_ACTIONS = [ 'harvest', 'fail', 'reopen' ];

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
		add_action( 'admin_init', [ __CLASS__, 'handle_actions' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	/**
	 * The screen's stylesheet and script, on this screen only.
	 *
	 * The status colours go in as inline style on the same handle, one rule per
	 * status, so an add-on's statuses are coloured by the same mechanism as the
	 * built-in three.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'bfmt-admin', BFMT_URL . 'assets/css/admin.css', [], BFMT_VERSION );
		wp_add_inline_style( 'bfmt-admin', BFMT_Status::stylesheet() );
		wp_enqueue_script( 'bfmt-admin', BFMT_URL . 'assets/js/admin.js', [], BFMT_VERSION, [ 'in_footer' => true ] );
	}

	/**
	 * A top-level menu.
	 *
	 * A place to keep records, not a setting, so it is its own menu rather
	 * than a page under Settings.
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

		$action = self::requested_action();

		if ( in_array( $action, self::STATUS_ACTIONS, true ) ) {
			self::do_status( $action );
		} elseif ( 'trash' === $action ) {
			self::do_trash();
		} elseif ( 'bulk_trash' === $action ) {
			self::do_bulk_trash();
		} elseif ( 'save' === $action ) {
			self::do_save();
		}
	}

	/**
	 * The action this request asks for.
	 *
	 * The list table has a bulk-action menu above AND below the table. The top
	 * one posts as `action`, the bottom one as `action2`, and whichever was not
	 * used arrives as "-1". Both are read, so either menu works.
	 */
	public static function requested_action(): string {
		foreach ( [ 'action', 'action2' ] as $field ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- choosing the handler; each handler verifies its own nonce.
			$value = isset( $_REQUEST[ $field ] ) ? sanitize_key( wp_unslash( $_REQUEST[ $field ] ) ) : '';

			if ( '' !== $value && '-1' !== $value ) {
				return $value;
			}
		}

		return '';
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

	/**
	 * Mark harvested, mark failed, or put back to growing.
	 */
	private static function do_status( string $action ): void {
		self::guard();

		$tray_id = isset( $_GET['tray_id'] ) ? absint( wp_unslash( $_GET['tray_id'] ) ) : 0;

		check_admin_referer( 'bfmt_' . $action . '_tray_' . $tray_id );

		// The list hides these while Pro is active (see
		// BFMT_Tray_List_Table::status_actions()); a crafted or stale link is
		// refused here too, so Pro's harvest, failure and rack records cannot
		// be bypassed.
		if ( BFMT_Pro_Upsell::pro_is_active() || BFMT_Status::has_pro_record( $tray_id ) ) {
			self::redirect_with_notice( [ 'error', __( 'This tray has Microgreen Tracker Pro records. Record its harvest or failure from Pro\'s tray screens.', 'bluefairy-microgreen-tracker' ) ] );
		}

		$done = match ( $action ) {
			'harvest' => BFMT_Status::mark_harvested( $tray_id, current_time( 'Y-m-d' ) ),
			'fail'    => BFMT_Status::mark_failed( $tray_id, current_time( 'Y-m-d' ) ),
			default   => BFMT_Status::reopen( $tray_id ),
		};

		$messages = [
			'harvest' => __( 'Tray marked harvested.', 'bluefairy-microgreen-tracker' ),
			'fail'    => __( 'Tray marked failed.', 'bluefairy-microgreen-tracker' ),
			'reopen'  => __( 'Tray is growing again.', 'bluefairy-microgreen-tracker' ),
		];

		self::redirect_with_notice(
			$done
				? [ 'updated', $messages[ $action ] ]
				: [ 'error', __( 'That tray could not be changed.', 'bluefairy-microgreen-tracker' ) ]
		);
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
	 * The result of the write decides the notice, so a refused save is never
	 * reported as saved.
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

	/**
	 * Say so when Lite rows could not be imported.
	 *
	 * The FAQ tells growers to delete Lite once this plugin is active, and
	 * deleting Lite drops its table. A row that failed to import would go with
	 * it, so the screen says how many failed and to keep Lite until then.
	 */
	private static function show_import_warning(): void {
		$result = get_option( BFMT_Migrator::DONE_OPTION );
		$failed = is_array( $result ) ? (int) ( $result['failed'] ?? 0 ) : 0;

		if ( $failed < 1 ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html( sprintf(
				/* translators: %d: number of rows */
				_n(
					'%d tray from Microgreen Tracker Lite could not be imported. Keep Lite installed: deleting it removes its table, and that tray with it.',
					'%d trays from Microgreen Tracker Lite could not be imported. Keep Lite installed: deleting it removes its table, and those trays with it.',
					$failed,
					'bluefairy-microgreen-tracker'
				),
				$failed
			) )
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
		self::show_import_warning();

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

		// No wp_nonce_field() here: WP_List_Table prints the bulk-trays nonce
		// itself, and a second copy is a duplicate element id.
		echo '<form method="post">';
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
			self::harvest_help( $editing )
		);
		echo '</tbody></table>';

		do_action( 'bfmt_tray_form_fields', $tray_id );

		submit_button( $editing
			? __( 'Update tray', 'bluefairy-microgreen-tracker' )
			: __( 'Add tray', 'bluefairy-microgreen-tracker' ) );

		echo '</form>';
	}

	/**
	 * The help line under Expected harvest.
	 *
	 * Pro fills the date in when a tray is ADDED with it blank, from the
	 * variety's days-to-harvest range, and only if the variety has one. It does
	 * nothing on edit, so the edit form does not promise it.
	 */
	public static function harvest_help( bool $editing ): string {
		if ( $editing ) {
			return __( 'Optional.', 'bluefairy-microgreen-tracker' );
		}

		return BFMT_Pro_Upsell::pro_is_active()
			? __( 'Optional. Left blank, Pro fills it in from the variety\'s days to harvest, if the variety has them.', 'bluefairy-microgreen-tracker' )
			: __( 'Optional. Microgreen Tracker Pro can fill it in for you from each variety\'s days to harvest.', 'bluefairy-microgreen-tracker' );
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
