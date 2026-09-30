<?php
/**
 * The tray record: registration, fields, reads and writes.
 *
 * @package BlueFairy\MicrogreenTracker
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TrayTest extends TestCase {

	protected function setUp(): void {
		bfmt_test_reset();
		$GLOBALS['bfmt_types'][1] = 'mgtrack_tray';
		$GLOBALS['bfmt_types'][2] = 'post';
	}

	private function reset_extra_fields(): void {
		$prop = new ReflectionProperty( BFMT_Tray::class, 'extra_fields' );
		$prop->setValue( null, [] );
	}

	protected function tearDown(): void {
		$this->reset_extra_fields();
	}

	public function test_init_registers_on_init(): void {
		BFMT_Tray::init();

		$this->assertSame( [ [ 'init', [ BFMT_Tray::class, 'register' ], 10 ] ], bfmt_test_calls( 'add_action' ) );
	}

	/**
	 * The post type and taxonomy names are the stored data's names; the post
	 * type is never public.
	 */
	public function test_register_uses_the_inherited_names_and_keeps_trays_private(): void {
		BFMT_Tray::register();

		[ $type, $args ] = bfmt_test_calls( 'register_post_type' )[0];
		$this->assertSame( 'mgtrack_tray', $type );
		$this->assertFalse( $args['public'] );
		$this->assertFalse( $args['show_in_menu'] );
		$this->assertTrue( $args['map_meta_cap'] );

		[ $tax, $for, $tax_args ] = bfmt_test_calls( 'register_taxonomy' )[0];
		$this->assertSame( 'mgtrack_seed', $tax );
		$this->assertSame( 'mgtrack_tray', $for );
		$this->assertFalse( $tax_args['public'] );
		$this->assertFalse( $tax_args['rewrite'] );
	}

	public function test_the_core_fields_map_to_the_inherited_meta_keys(): void {
		$this->assertSame( [
			'seeding_date'          => '_mgtrack_seeding_date',
			'expected_harvest_date' => '_mgtrack_expected_harvest_date',
			'tray_size'             => '_mgtrack_tray_size',
		], BFMT_Tray::fields() );
	}

	public function test_an_add_on_field_joins_the_map(): void {
		BFMT_Tray::register_field( 'harvest_date', '_mgtrack_harvest_date' );

		$this->assertSame( '_mgtrack_harvest_date', BFMT_Tray::fields()['harvest_date'] );
		$this->assertTrue( BFMT_Tray::set( 1, 'harvest_date', '2026-09-01' ) );
		$this->assertSame( '2026-09-01', BFMT_Tray::get( 1, 'harvest_date' ) );
	}

	public function test_is_tray_checks_the_post_type(): void {
		$this->assertTrue( BFMT_Tray::is_tray( 1 ) );
		$this->assertFalse( BFMT_Tray::is_tray( 2 ), 'a post of another type' );
		$this->assertFalse( BFMT_Tray::is_tray( 3 ), 'no such post' );
		$this->assertFalse( BFMT_Tray::is_tray( 0 ) );
		$this->assertFalse( BFMT_Tray::is_tray( -1 ) );
	}

	public function test_get_reads_the_field_and_falls_back(): void {
		update_post_meta( 1, '_mgtrack_tray_size', '10x20' );

		$this->assertSame( '10x20', BFMT_Tray::get( 1, 'tray_size' ) );
		$this->assertSame( 'none', BFMT_Tray::get( 1, 'seeding_date', 'none' ), 'empty meta' );
		$this->assertSame( 'none', BFMT_Tray::get( 1, 'no_such_field', 'none' ), 'unknown field' );
		$this->assertSame( 'none', BFMT_Tray::get( 2, 'tray_size', 'none' ), 'not a tray' );
	}

	public function test_set_writes_only_known_fields_on_trays(): void {
		$this->assertTrue( BFMT_Tray::set( 1, 'tray_size', 'Half' ) );
		$this->assertSame( 'Half', $GLOBALS['bfmt_meta'][1]['_mgtrack_tray_size'] );

		$this->assertFalse( BFMT_Tray::set( 1, 'no_such_field', 'x' ) );
		$this->assertFalse( BFMT_Tray::set( 2, 'tray_size', 'x' ) );
		$this->assertArrayNotHasKey( 2, $GLOBALS['bfmt_meta'] );
	}

	public function test_variety_reads_the_first_term(): void {
		$GLOBALS['bfmt_terms'][1] = [ (object) [ 'name' => 'Kale', 'term_id' => 42 ] ];

		$this->assertSame( 'Kale', BFMT_Tray::variety( 1 ) );
		$this->assertSame( 42, BFMT_Tray::variety_term_id( 1 ) );
	}

	public function test_variety_is_empty_without_a_term_or_a_tray(): void {
		$GLOBALS['bfmt_terms'][2] = [ (object) [ 'name' => 'Kale', 'term_id' => 42 ] ];

		$this->assertSame( '', BFMT_Tray::variety( 1 ) );
		$this->assertSame( 0, BFMT_Tray::variety_term_id( 1 ) );
		$this->assertSame( '', BFMT_Tray::variety( 2 ), 'terms on a non-tray are not read' );
		$this->assertSame( 0, BFMT_Tray::variety_term_id( 2 ) );
	}

	public function test_create_refuses_a_missing_variety_or_date(): void {
		$no_variety = BFMT_Tray::create( [ 'variety' => '  ', 'seeding_date' => '2026-01-01' ] );
		$no_date    = BFMT_Tray::create( [ 'variety' => 'Kale', 'seeding_date' => '' ] );

		$this->assertSame( 'bfmt_variety', $no_variety->get_error_code() );
		$this->assertSame( 'bfmt_seeding_date', $no_date->get_error_code() );
		$this->assertSame( [], bfmt_test_calls( 'wp_insert_post' ) );
	}

	public function test_create_refuses_an_unreadable_date_and_quotes_it_back(): void {
		$result = BFMT_Tray::create( [ 'variety' => 'Kale', 'seeding_date' => '2026-01-01', 'expected_harvest_date' => '2026-02-30' ] );

		$this->assertSame( 'bfmt_date_format', $result->get_error_code() );
		$this->assertStringContainsString( '2026-02-30', $result->get_error_message() );
	}

	public function test_create_inserts_a_published_tray_with_its_fields_and_term(): void {
		$id = BFMT_Tray::create( [ 'variety' => ' Kale ', 'seeding_date' => '2026-01-01', 'tray_size' => '10x20' ] );

		$this->assertSame( 100, $id );

		$insert = bfmt_test_calls( 'wp_insert_post' )[0][0];
		$this->assertSame( 'mgtrack_tray', $insert['post_type'] );
		$this->assertSame( 'publish', $insert['post_status'] );
		$this->assertSame( 'Kale — 2026-01-01', $insert['post_title'] );

		$this->assertSame( [ 100, 'Kale', 'mgtrack_seed', false ], bfmt_test_calls( 'wp_set_object_terms' )[0] );
		$this->assertSame( '2026-01-01', $GLOBALS['bfmt_meta'][100]['_mgtrack_seeding_date'] );
		$this->assertSame( '10x20', $GLOBALS['bfmt_meta'][100]['_mgtrack_tray_size'] );
	}

	/**
	 * Pro fills a blank expected harvest date on this action; it has to fire
	 * after the fields are written, with the id and what was submitted.
	 */
	public function test_create_fires_the_created_action_after_writing(): void {
		BFMT_Tray::create( [ 'variety' => 'Kale', 'seeding_date' => '2026-01-01' ] );

		$sequence = bfmt_test_sequence();
		$created  = array_search( 'do_action:bfmt_tray_created', $sequence, true );
		$written  = array_search( 'update_post_meta:100', $sequence, true );

		$this->assertNotFalse( $created );
		$this->assertLessThan( $created, $written );
		$this->assertSame( [ 'bfmt_tray_created', 100, [ 'variety' => 'Kale', 'seeding_date' => '2026-01-01' ] ], bfmt_test_calls( 'do_action' )[0] );
	}

	public function test_create_passes_an_insert_error_through(): void {
		$GLOBALS['bfmt_insert_error'] = new WP_Error( 'db', 'nope' );

		$result = BFMT_Tray::create( [ 'variety' => 'Kale', 'seeding_date' => '2026-01-01' ] );

		$this->assertSame( 'db', $result->get_error_code() );
		$this->assertSame( [], bfmt_test_calls( 'do_action' ) );
	}

	public function test_update_refuses_something_that_is_not_a_tray(): void {
		$this->assertSame( 'bfmt_no_tray', BFMT_Tray::update( 2, [ 'tray_size' => 'x' ] )->get_error_code() );
		$this->assertSame( [], bfmt_test_calls( 'update_post_meta' ) );
	}

	public function test_update_refuses_a_bad_date_and_writes_nothing(): void {
		$result = BFMT_Tray::update( 1, [ 'seeding_date' => '03/04/2026' ] );

		$this->assertSame( 'bfmt_date_format', $result->get_error_code() );
		$this->assertSame( [], bfmt_test_calls( 'update_post_meta' ) );
	}

	/**
	 * Returns true, typed bool: PHP 8.1 has no `true` return type.
	 */
	public function test_update_writes_retitles_and_fires_the_updated_action(): void {
		$GLOBALS['bfmt_terms'][1] = [ (object) [ 'name' => 'Kale', 'term_id' => 7 ] ];

		$this->assertTrue( BFMT_Tray::update( 1, [ 'seeding_date' => '2026-03-04', 'tray_size' => 'Half' ] ) );
		$this->assertSame( '2026-03-04', $GLOBALS['bfmt_meta'][1]['_mgtrack_seeding_date'] );
		$this->assertSame( 'Kale — 2026-03-04', bfmt_test_calls( 'wp_update_post' )[0][0]['post_title'] );
		$this->assertSame( 'bfmt_tray_updated', bfmt_test_calls( 'do_action' )[0][0] );
	}

	public function test_update_leaves_the_variety_alone_when_it_is_not_submitted(): void {
		BFMT_Tray::update( 1, [ 'tray_size' => 'Half' ] );

		$this->assertSame( [], bfmt_test_calls( 'wp_set_object_terms' ) );
	}

	/**
	 * Trash, never force: the post goes to the trash where it can be restored.
	 */
	public function test_trash_moves_a_tray_to_the_trash_and_nothing_else(): void {
		$this->assertTrue( BFMT_Tray::trash( 1 ) );
		$this->assertSame( [ [ 1 ] ], bfmt_test_calls( 'wp_trash_post' ) );

		$this->assertFalse( BFMT_Tray::trash( 2 ) );
		$this->assertCount( 1, bfmt_test_calls( 'wp_trash_post' ), 'a non-tray is not trashed' );
	}

	/**
	 * PHP 8.1 compatibility, checked at the signature: no `true`, `false` or
	 * `null` standalone types, which need 8.2.
	 */
	public function test_no_signature_needs_php_8_2(): void {
		$root = dirname( __DIR__, 2 );
		$hits = [];

		foreach ( [ 'includes', 'admin' ] as $dir ) {
			foreach ( glob( "{$root}/{$dir}/*.php" ) as $file ) {
				if ( preg_match_all( '/\)\s*:\s*\??(?:[\w\\\\]+\|)*(true|false|null)(?:\|[\w\\\\]+)*\s*\{/', (string) file_get_contents( $file ), $m ) ) {
					$hits[] = basename( $file ) . ': ' . implode( ', ', $m[0] );
				}
			}
		}

		$this->assertSame( [], $hits );
	}

	/**
	 * The edit form marks Seeded as required; blanking it on edit is refused.
	 */
	public function test_update_refuses_to_erase_a_seeded_date(): void {
		$GLOBALS['bfmt_meta'][1]['_mgtrack_seeding_date'] = '2026-09-01';

		$result = BFMT_Tray::update( 1, [ 'seeding_date' => '  ', 'tray_size' => 'Half' ] );

		$this->assertSame( 'bfmt_seeding_date', $result->get_error_code() );
		$this->assertSame( [], bfmt_test_calls( 'update_post_meta' ) );
		$this->assertSame( '2026-09-01', $GLOBALS['bfmt_meta'][1]['_mgtrack_seeding_date'] );
	}

	/**
	 * A Pro tray started with a soak has no seeded date. Editing its variety
	 * or size must not force one on it — that would move Pro's milestones.
	 */
	public function test_a_tray_not_sown_yet_can_be_edited_without_a_seeded_date(): void {
		$this->assertTrue( BFMT_Tray::update( 1, [ 'seeding_date' => '', 'tray_size' => 'Half' ] ) );

		$this->assertSame( 'Half', $GLOBALS['bfmt_meta'][1]['_mgtrack_tray_size'] );
		$this->assertArrayNotHasKey( '_mgtrack_seeding_date', $GLOBALS['bfmt_meta'][1] );
	}
}
