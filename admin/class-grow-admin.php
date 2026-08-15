<?php
/**
 * Admin menu, add/edit form, and delete handler for BlueFairy Microgreen Tracker Lite.
 *
 * @package BlueFairy\MicrogreenTrackerLite
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

class BFMTL_Grow_Admin {

	const PAGE_SLUG = 'bfmtl-grow-tracker';

	/**
	 * Register all admin hooks. Called from BFMTL_Plugin::init().
	 */
	public static function init(): void {
		add_action( 'admin_menu',  [ __CLASS__, 'register_menu' ] );
		add_action( 'admin_init',  [ __CLASS__, 'handle_early_actions' ] );
	}

	/**
	 * Register the Settings > Grow Tracker sub-menu page.
	 */
	public static function register_menu(): void {
		add_options_page(
			__( 'Grow Tracker', 'bluefairy-microgreen-tracker-lite' ),
			__( 'Grow Tracker', 'bluefairy-microgreen-tracker-lite' ),
			'manage_options',
			self::PAGE_SLUG,
			[ __CLASS__, 'render_page' ]
		);
	}

	// -------------------------------------------------------------------------
	// Early action handler (runs on admin_init — before output)
	// -------------------------------------------------------------------------

	/**
	 * Handle POST saves and GET deletes before any output is sent.
	 */
	public static function handle_early_actions(): void {
		if ( ! is_admin() ) {
			return;
		}
		// Only act on our page.
		$page = sanitize_key( $_REQUEST['page'] ?? '' );
		if ( $page !== self::PAGE_SLUG ) {
			return;
		}

		$action = sanitize_key( $_REQUEST['action'] ?? '' );

		// --- DELETE (GET with nonce in URL) ---
		if ( $action === 'delete' ) {
			self::handle_delete();
			return;
		}

		// --- BULK DELETE ---
		if ( $action === 'bulk_delete' || ( isset( $_POST['action'] ) && $_POST['action'] === 'bulk_delete' ) || ( isset( $_POST['action2'] ) && $_POST['action2'] === 'bulk_delete' ) ) {
			self::handle_bulk_delete();
			return;
		}

		// --- ADD (POST) ---
		if ( $action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
			self::handle_save( 0 );
			return;
		}

		// --- EDIT (POST) ---
		if ( $action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
			$grow_id = absint( $_POST['grow_id'] ?? 0 );
			if ( $grow_id > 0 ) {
				self::handle_save( $grow_id );
			}
			return;
		}
	}

	// -------------------------------------------------------------------------
	// Action handlers
	// -------------------------------------------------------------------------

	private static function handle_delete(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'bluefairy-microgreen-tracker-lite' ) );
		}

		$grow_id = absint( $_GET['grow_id'] ?? 0 );
		if ( $grow_id <= 0 ) {
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bfmtl_delete_grow_' . $grow_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'bluefairy-microgreen-tracker-lite' ) );
		}

		BFMTL_DB::delete( $grow_id );
		wp_safe_redirect( add_query_arg( [ 'page' => self::PAGE_SLUG, 'deleted' => '1' ], admin_url( 'options-general.php' ) ) );
		exit;
	}

	private static function handle_bulk_delete(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'bluefairy-microgreen-tracker-lite' ) );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'bulk-grows' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'bluefairy-microgreen-tracker-lite' ) );
		}

		$ids = isset( $_POST['grow_ids'] ) ? array_map( 'absint', (array) $_POST['grow_ids'] ) : [];
		$ids = array_filter( $ids );

		if ( ! empty( $ids ) ) {
			$deleted = BFMTL_DB::delete_bulk( $ids );
			wp_safe_redirect( add_query_arg( [ 'page' => self::PAGE_SLUG, 'bulk_deleted' => $deleted ], admin_url( 'options-general.php' ) ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( [ 'page' => self::PAGE_SLUG ], admin_url( 'options-general.php' ) ) );
		exit;
	}

	private static function handle_save( int $grow_id ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'bluefairy-microgreen-tracker-lite' ) );
		}

		$nonce_action = $grow_id > 0 ? 'bfmtl_edit_grow_' . $grow_id : 'bfmtl_add_grow';
		if ( ! isset( $_POST['bfmtl_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bfmtl_nonce'] ) ), $nonce_action ) ) {
			wp_die( esc_html__( 'Security check failed.', 'bluefairy-microgreen-tracker-lite' ) );
		}

		$variety  = sanitize_text_field( wp_unslash( $_POST['variety'] ?? '' ) );
		$tray_size = sanitize_text_field( wp_unslash( $_POST['tray_size'] ?? '' ) );
		$seeded_date = sanitize_text_field( wp_unslash( $_POST['seeded_date'] ?? '' ) );
		$expected_harvest = sanitize_text_field( wp_unslash( $_POST['expected_harvest_date'] ?? '' ) );
		$status   = sanitize_key( $_POST['status'] ?? 'active' );

		// Validate required fields.
		if ( $variety === '' || $seeded_date === '' ) {
			self::set_notice( 'error', __( 'Variety and Seeded Date are required.', 'bluefairy-microgreen-tracker-lite' ) );
			$redirect_args = [ 'page' => self::PAGE_SLUG, 'action' => $grow_id > 0 ? 'edit' : 'add' ];
			if ( $grow_id > 0 ) {
				$redirect_args['grow_id'] = $grow_id;
			}
			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'options-general.php' ) ) );
			exit;
		}

		// Validate date format.
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $seeded_date ) ) {
			$seeded_date = '';
		}
		if ( $expected_harvest !== '' && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expected_harvest ) ) {
			$expected_harvest = '';
		}

		// Validate status enum.
		if ( ! in_array( $status, [ 'active', 'harvested', 'failed' ], true ) ) {
			$status = 'active';
		}

		$data = [
			'variety'               => $variety,
			'tray_size'             => $tray_size,
			'seeded_date'           => $seeded_date,
			'expected_harvest_date' => $expected_harvest !== '' ? $expected_harvest : null,
			'status'                => $status,
		];

		if ( $grow_id > 0 ) {
			BFMTL_DB::update( $grow_id, $data );
			self::set_notice( 'success', __( 'Grow record updated.', 'bluefairy-microgreen-tracker-lite' ) );
		} else {
			BFMTL_DB::insert( $data );
			self::set_notice( 'success', __( 'Grow record added.', 'bluefairy-microgreen-tracker-lite' ) );
		}

		wp_safe_redirect( add_query_arg( [ 'page' => self::PAGE_SLUG ], admin_url( 'options-general.php' ) ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Page renderer — dispatches to list or form view
	// -------------------------------------------------------------------------

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'bluefairy-microgreen-tracker-lite' ) );
		}

		$action  = sanitize_key( $_GET['action'] ?? 'list' );
		$grow_id = absint( $_GET['grow_id'] ?? 0 );

		if ( $action === 'add' ) {
			self::render_form( null );
		} elseif ( $action === 'edit' && $grow_id > 0 ) {
			$row = BFMTL_DB::get_by_id( $grow_id );
			self::render_form( $row ?: null );
		} else {
			self::render_list();
		}
	}

	// -------------------------------------------------------------------------
	// List view
	// -------------------------------------------------------------------------

	private static function render_list(): void {
		self::show_query_notices();
		self::show_transient_notice();

		$table = new BFMTL_Grow_List_Table();
		$table->prepare_items();
		$page_url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'Grow Tracker', 'bluefairy-microgreen-tracker-lite' ); ?>
			</h1>
			<a href="<?php echo esc_url( add_query_arg( 'action', 'add', $page_url ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New Grow', 'bluefairy-microgreen-tracker-lite' ); ?>
			</a>
			<hr class="wp-header-end">

			<form method="post" action="<?php echo esc_url( $page_url ); ?>">
				<?php
				// The nonce field name must match what WP_List_Table expects for bulk actions: bulk-{plural}.
				wp_nonce_field( 'bulk-grows' );
				$table->display();
				?>
			</form>

			<?php self::render_upgrade_notice(); ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Add / Edit form
	// -------------------------------------------------------------------------

	/**
	 * @param object|null $row  Existing DB row when editing; null when adding.
	 */
	private static function render_form( ?object $row ): void {
		self::show_transient_notice();

		$is_edit     = $row !== null;
		$grow_id     = $is_edit ? absint( $row->id ) : 0;
		$nonce_action = $is_edit ? 'bfmtl_edit_grow_' . $grow_id : 'bfmtl_add_grow';
		$form_action  = $is_edit ? 'edit' : 'add';
		$page_url     = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );

		$variety          = $is_edit ? $row->variety : '';
		$tray_size        = $is_edit ? $row->tray_size : '';
		$seeded_date      = $is_edit ? $row->seeded_date : '';
		$expected_harvest = $is_edit ? (string) $row->expected_harvest_date : '';
		$status           = $is_edit ? $row->status : 'active';
		?>
		<div class="wrap">
			<h1>
				<?php echo $is_edit
					? esc_html__( 'Edit Grow Record', 'bluefairy-microgreen-tracker-lite' )
					: esc_html__( 'Add New Grow', 'bluefairy-microgreen-tracker-lite' ); ?>
			</h1>
			<a href="<?php echo esc_url( $page_url ); ?>">
				&larr; <?php esc_html_e( 'Back to grow list', 'bluefairy-microgreen-tracker-lite' ); ?>
			</a>

			<form method="post" action="<?php echo esc_url( add_query_arg( 'action', $form_action, $page_url ) ); ?>" style="max-width:620px;margin-top:1.5em;">
				<?php wp_nonce_field( $nonce_action, 'bfmtl_nonce' ); ?>
				<?php if ( $is_edit ) : ?>
					<input type="hidden" name="grow_id" value="<?php echo absint( $grow_id ); ?>">
				<?php endif; ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="bfmtl-variety"><?php esc_html_e( 'Variety', 'bluefairy-microgreen-tracker-lite' ); ?> <span aria-hidden="true">*</span></label>
						</th>
						<td>
							<input
								type="text"
								id="bfmtl-variety"
								name="variety"
								value="<?php echo esc_attr( $variety ); ?>"
								class="regular-text"
								maxlength="100"
								required
							>
							<p class="description"><?php esc_html_e( 'e.g. Sunflower, Radish, Pea Shoots', 'bluefairy-microgreen-tracker-lite' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bfmtl-tray-size"><?php esc_html_e( 'Tray Size', 'bluefairy-microgreen-tracker-lite' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="bfmtl-tray-size"
								name="tray_size"
								value="<?php echo esc_attr( $tray_size ); ?>"
								class="regular-text"
								maxlength="50"
							>
							<p class="description"><?php esc_html_e( 'e.g. 10×20, Half tray, 1020 standard', 'bluefairy-microgreen-tracker-lite' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bfmtl-seeded-date"><?php esc_html_e( 'Seeded Date', 'bluefairy-microgreen-tracker-lite' ); ?> <span aria-hidden="true">*</span></label>
						</th>
						<td>
							<input
								type="date"
								id="bfmtl-seeded-date"
								name="seeded_date"
								value="<?php echo esc_attr( $seeded_date ); ?>"
								required
							>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bfmtl-harvest-date"><?php esc_html_e( 'Expected Harvest Date', 'bluefairy-microgreen-tracker-lite' ); ?></label>
						</th>
						<td>
							<input
								type="date"
								id="bfmtl-harvest-date"
								name="expected_harvest_date"
								value="<?php echo esc_attr( $expected_harvest ); ?>"
							>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bfmtl-status"><?php esc_html_e( 'Status', 'bluefairy-microgreen-tracker-lite' ); ?></label>
						</th>
						<td>
							<select id="bfmtl-status" name="status">
								<option value="active"    <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'bluefairy-microgreen-tracker-lite' ); ?></option>
								<option value="harvested" <?php selected( $status, 'harvested' ); ?>><?php esc_html_e( 'Harvested', 'bluefairy-microgreen-tracker-lite' ); ?></option>
								<option value="failed"    <?php selected( $status, 'failed' ); ?>><?php esc_html_e( 'Failed', 'bluefairy-microgreen-tracker-lite' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<?php submit_button( $is_edit
					? __( 'Update Grow Record', 'bluefairy-microgreen-tracker-lite' )
					: __( 'Add Grow Record', 'bluefairy-microgreen-tracker-lite' )
				); ?>
			</form>

			<?php self::render_upgrade_notice(); ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Upgrade notice
	// -------------------------------------------------------------------------

	private static function render_upgrade_notice(): void {
		?>
		<div style="margin-top:2em;padding:1em 1.25em;background:#eff6ff;border:1px solid #bfdbfe;border-radius:4px;max-width:700px;color:#1e3a5f;font-size:.9em;">
			<?php
			printf(
				/* translators: %s: upgrade link */
				esc_html__( '%s for yield tracking, CSV export, harvest planning, rack management, and more &rarr;', 'bluefairy-microgreen-tracker-lite' ),
				sprintf(
					'<a href="%s" target="_blank" rel="noopener noreferrer" style="font-weight:600;color:#1d4ed8;">%s</a>',
					esc_url( 'https://wordpress.bluefairydevelopment.com/product/bluefairy-microgreen-tracker/' ),
					esc_html__( 'Upgrade to Pro', 'bluefairy-microgreen-tracker-lite' )
				)
			);
			?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Notice helpers
	// -------------------------------------------------------------------------

	/**
	 * Show URL-param-based notices (after redirects).
	 */
	private static function show_query_notices(): void {
		if ( isset( $_GET['deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Grow record deleted.', 'bluefairy-microgreen-tracker-lite' ) . '</p></div>';
		}
		if ( isset( $_GET['bulk_deleted'] ) ) {
			$count = absint( $_GET['bulk_deleted'] );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				/* translators: %d: number deleted */
				esc_html( sprintf( _n( '%d grow record deleted.', '%d grow records deleted.', $count, 'bluefairy-microgreen-tracker-lite' ), $count ) )
			);
		}
	}

	/**
	 * Store a notice in a short-lived transient so it survives a redirect.
	 */
	private static function set_notice( string $type, string $message ): void {
		$key = 'bfmtl_notice_' . get_current_user_id();
		set_transient( $key, [ 'type' => $type, 'message' => $message ], 60 );
	}

	/**
	 * Display and clear the transient notice.
	 */
	private static function show_transient_notice(): void {
		$key  = 'bfmtl_notice_' . get_current_user_id();
		$data = get_transient( $key );
		if ( ! $data ) {
			return;
		}
		delete_transient( $key );
		$type    = in_array( $data['type'], [ 'success', 'error', 'warning', 'info' ], true ) ? $data['type'] : 'info';
		$message = (string) $data['message'];
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}
}
