<?php
/**
 * The tray record. One definition, owned by the free plugin.
 *
 * The Pro add-on reads and writes trays through this class and registers its
 * own milestone fields with register_field(). It does not register the post
 * type, the taxonomy, or any meta key of its own accord: two plugins that each
 * know the storage layout drift apart at the first schema change.
 *
 * The post type, taxonomy and meta key names come from the paid plugin's 1.3.3
 * data model and MUST NOT change. bluedragonhollow.com has fifteen real trays
 * stored under exactly these names; renaming any of them orphans the lot.
 * _mgtrack_seeding_date in particular is not "seeded_date" — the free plugin's
 * old custom table used that name and the real data does not.
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

class BFMT_Tray {

	public const POST_TYPE = 'mgtrack_tray';
	public const TAXONOMY  = 'mgtrack_seed';

	/**
	 * Fields the free plugin owns.
	 *
	 * seeding_date reuses the key the paid plugin has always written, so an
	 * existing farm's trays are readable the moment this plugin is activated.
	 * tray_size and expected_harvest_date are new: nothing was storing them.
	 */
	private const CORE_FIELDS = [
		'seeding_date'          => '_mgtrack_seeding_date',
		'expected_harvest_date' => '_mgtrack_expected_harvest_date',
		'tray_size'             => '_mgtrack_tray_size',
	];

	/** @var array<string, string> Fields contributed by add-ons. */
	private static array $extra_fields = [];

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register' ] );
	}

	public static function register(): void {
		register_post_type( self::POST_TYPE, [
			'labels'          => [
				'name'          => __( 'Trays', 'bluefairy-microgreen-tracker' ),
				'singular_name' => __( 'Tray', 'bluefairy-microgreen-tracker' ),
				'add_new_item'  => __( 'New Tray', 'bluefairy-microgreen-tracker' ),
				'edit_item'     => __( 'Edit Tray', 'bluefairy-microgreen-tracker' ),
				'not_found'     => __( 'No trays yet', 'bluefairy-microgreen-tracker' ),
			],
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => false,
			'supports'        => [ 'title' ],
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		] );

		register_taxonomy( self::TAXONOMY, self::POST_TYPE, [
			'labels'            => [
				'name'          => __( 'Varieties', 'bluefairy-microgreen-tracker' ),
				'singular_name' => __( 'Variety', 'bluefairy-microgreen-tracker' ),
				'add_new_item'  => __( 'Add Variety', 'bluefairy-microgreen-tracker' ),
				'edit_item'     => __( 'Edit Variety', 'bluefairy-microgreen-tracker' ),
			],
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_in_menu'      => false,
			'show_admin_column' => true,
			'rewrite'           => false,
		] );
	}

	/**
	 * Let an add-on store a field on a tray without knowing the meta layout.
	 *
	 * Pro registers the milestone fields here — soak, weight lifted, darkness
	 * lifted, harvest, failure — so there is exactly one place that maps a field
	 * name to a meta key.
	 */
	public static function register_field( string $name, string $meta_key ): void {
		self::$extra_fields[ $name ] = $meta_key;
	}

	/**
	 * @return array<string, string>
	 */
	public static function fields(): array {
		return array_merge( self::CORE_FIELDS, self::$extra_fields );
	}

	public static function get( int $tray_id, string $field, mixed $fallback = '' ): mixed {
		$key = self::fields()[ $field ] ?? null;

		if ( null === $key || ! self::is_tray( $tray_id ) ) {
			return $fallback;
		}

		$value = get_post_meta( $tray_id, $key, true );

		return '' === $value ? $fallback : $value;
	}

	public static function set( int $tray_id, string $field, mixed $value ): bool {
		$key = self::fields()[ $field ] ?? null;

		if ( null === $key || ! self::is_tray( $tray_id ) ) {
			return false;
		}

		return false !== update_post_meta( $tray_id, $key, $value );
	}

	public static function is_tray( int $tray_id ): bool {
		return $tray_id > 0 && self::POST_TYPE === get_post_type( $tray_id );
	}

	/**
	 * The variety name, which lives in the taxonomy rather than in meta.
	 */
	public static function variety( int $tray_id ): string {
		if ( ! self::is_tray( $tray_id ) ) {
			return '';
		}

		$terms = get_the_terms( $tray_id, self::TAXONOMY );

		return ( is_array( $terms ) && $terms ) ? $terms[0]->name : '';
	}

	public static function variety_term_id( int $tray_id ): int {
		if ( ! self::is_tray( $tray_id ) ) {
			return 0;
		}

		$terms = get_the_terms( $tray_id, self::TAXONOMY );

		return ( is_array( $terms ) && $terms ) ? (int) $terms[0]->term_id : 0;
	}

	/**
	 * Create a tray.
	 *
	 * @param  array<string, mixed> $fields Must include 'variety' and 'seeding_date'.
	 * @return int|WP_Error
	 */
	public static function create( array $fields ): int|WP_Error {
		$check = self::validate( $fields, true );

		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$tray_id = wp_insert_post( [
			'post_type'   => self::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => self::title_for( $fields ),
		], true );

		if ( is_wp_error( $tray_id ) ) {
			return $tray_id;
		}

		self::apply( (int) $tray_id, $fields );

		do_action( 'bfmt_tray_created', (int) $tray_id, $fields );

		return (int) $tray_id;
	}

	/**
	 * @param  array<string, mixed> $fields
	 * @return true|WP_Error
	 */
	public static function update( int $tray_id, array $fields ): true|WP_Error {
		if ( ! self::is_tray( $tray_id ) ) {
			return new WP_Error( 'bfmt_no_tray', __( 'That tray no longer exists.', 'bluefairy-microgreen-tracker' ) );
		}

		$check = self::validate( $fields, false );

		if ( is_wp_error( $check ) ) {
			return $check;
		}

		self::apply( $tray_id, $fields );

		$title = wp_update_post( [
			'ID'         => $tray_id,
			'post_title' => self::title_for( [
				'variety'      => self::variety( $tray_id ),
				'seeding_date' => (string) self::get( $tray_id, 'seeding_date' ),
			] ),
		], true );

		if ( is_wp_error( $title ) ) {
			return $title;
		}

		do_action( 'bfmt_tray_updated', $tray_id, $fields );

		return true;
	}

	/**
	 * Move a tray to the trash.
	 *
	 * Trash, never force. A tray is a season of someone's records, and 1.3.3
	 * handed a permanent delete to the worker role from a public form.
	 */
	public static function trash( int $tray_id ): bool {
		return self::is_tray( $tray_id ) && (bool) wp_trash_post( $tray_id );
	}

	/**
	 * A real calendar date, not merely something shaped like one.
	 */
	public static function is_date( string $value ): bool {
		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			return false;
		}

		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}

	/**
	 * @param  array<string, mixed> $fields
	 * @return true|WP_Error
	 */
	private static function validate( array $fields, bool $creating ): true|WP_Error {
		if ( $creating && '' === trim( (string) ( $fields['variety'] ?? '' ) ) ) {
			return new WP_Error( 'bfmt_variety', __( 'A variety is required.', 'bluefairy-microgreen-tracker' ) );
		}

		if ( $creating && '' === trim( (string) ( $fields['seeding_date'] ?? '' ) ) ) {
			return new WP_Error( 'bfmt_seeding_date', __( 'A seeded date is required.', 'bluefairy-microgreen-tracker' ) );
		}

		foreach ( [ 'seeding_date', 'expected_harvest_date' ] as $field ) {
			if ( ! array_key_exists( $field, $fields ) ) {
				continue;
			}

			$value = trim( (string) $fields[ $field ] );

			// An unreadable date is refused, not silently blanked. The old free
			// plugin replaced it with an empty string AFTER the required-field
			// check, wrote that to a NOT NULL column, and reported success on an
			// insert the database had rejected.
			if ( '' !== $value && ! self::is_date( $value ) ) {
				return new WP_Error(
					'bfmt_date_format',
					sprintf(
						/* translators: %s: the date the user typed */
						__( '“%s” is not a date this plugin can read. Use year-month-day, for example 2026-03-14.', 'bluefairy-microgreen-tracker' ),
						$value
					)
				);
			}
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $fields
	 */
	private static function apply( int $tray_id, array $fields ): void {
		if ( array_key_exists( 'variety', $fields ) ) {
			$name = trim( (string) $fields['variety'] );

			if ( '' !== $name ) {
				wp_set_object_terms( $tray_id, $name, self::TAXONOMY, false );
			}
		}

		foreach ( self::fields() as $field => $key ) {
			if ( array_key_exists( $field, $fields ) ) {
				update_post_meta( $tray_id, $key, $fields[ $field ] );
			}
		}
	}

	/**
	 * @param array<string, mixed> $fields
	 */
	private static function title_for( array $fields ): string {
		$variety = trim( (string) ( $fields['variety'] ?? '' ) );
		$seeded  = trim( (string) ( $fields['seeding_date'] ?? '' ) );

		$title = trim( $variety . ( '' !== $seeded ? ' — ' . $seeded : '' ) );

		return '' !== $title ? $title : __( 'Tray', 'bluefairy-microgreen-tracker' );
	}
}
