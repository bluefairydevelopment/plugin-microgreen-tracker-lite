<?php
/**
 * The checks that need a real WordPress.
 *
 *     wp eval-file wp-content/plugins/bluefairy-microgreen-tracker/tests/integration-checks.php
 *
 * Run with both halves active. Every check here guards something the review
 * found, so a change that reintroduces one is recognisable rather than merely
 * red.
 *
 * No declare(strict_types=1): wp eval-file evaluates this rather than including
 * it, and a declare must be a script's first statement.
 *
 * @package BlueFairy\MicrogreenTracker
 */

$bfmt_failures = 0;

$check = static function ( string $label, bool $passed, string $detail = '' ) use ( &$bfmt_failures ): void {
	if ( ! $passed ) {
		++$bfmt_failures;
	}

	printf( "  [%s] %s%s\n", $passed ? 'ok  ' : 'FAIL', $label, '' !== $detail ? "  ({$detail})" : '' );
};

echo "\n== one owner for the tray record ==\n";
$check( 'the base plugin is present', function_exists( 'bfmt_plugin' ) );
$check( 'the tray post type is registered', post_type_exists( BFMT_Tray::POST_TYPE ) );
$check( 'the variety taxonomy is registered', taxonomy_exists( BFMT_Tray::TAXONOMY ) );

$bfmt_pro = class_exists( 'MGTrack_Post_Types' );

if ( $bfmt_pro ) {
	$bfmt_reflection = new ReflectionClass( 'MGTrack_Post_Types' );
	$check( 'the add-on does not also register the tray', ! $bfmt_reflection->hasMethod( 'register_tray_cpt' ) );
	$check( 'the add-on does not also register the taxonomy', ! $bfmt_reflection->hasMethod( 'register_seed_taxonomy' ) );
	$check( 'the add-on contributed its milestone fields', count( BFMT_Tray::fields() ) > 3, count( BFMT_Tray::fields() ) . ' fields' );
	$check( 'the add-on answers the status filter', false !== has_filter( 'bfmt_tray_status' ) );
}

echo "\n== a date the database would reject is refused first ==\n";
foreach ( [ '2026-02-30', '2026-13-01', '03/14/2026', 'yesterday' ] as $bfmt_bad ) {
	$bfmt_result = BFMT_Tray::create( [ 'variety' => 'Check', 'seeding_date' => $bfmt_bad ] );
	$check( "\"{$bfmt_bad}\" is refused", is_wp_error( $bfmt_result ) );

	if ( ! is_wp_error( $bfmt_result ) ) {
		wp_delete_post( $bfmt_result, true );
	}
}

echo "\n== a good tray round-trips ==\n";
$bfmt_id = BFMT_Tray::create( [
	'variety'      => 'Integration Check Variety',
	'tray_size'    => '10x20',
	'seeding_date' => '2026-08-01',
] );

$check( 'a valid tray is created', ! is_wp_error( $bfmt_id ) );

if ( ! is_wp_error( $bfmt_id ) ) {
	$check( 'the variety reads back from the taxonomy', 'Integration Check Variety' === BFMT_Tray::variety( $bfmt_id ) );
	$check( 'the seeded date reads back', '2026-08-01' === BFMT_Tray::get( $bfmt_id, 'seeding_date' ) );
	$check( 'a new tray is not harvested', 'harvested' !== BFMT_Status::get( $bfmt_id ) );

	BFMT_Tray::set( $bfmt_id, 'harvest_date', '2026-08-11' );
	$check( 'recording a harvest changes the status', 'harvested' === BFMT_Status::get( $bfmt_id ), BFMT_Status::get( $bfmt_id ) );

	echo "\n== deleting a tray is recoverable ==\n";
	BFMT_Tray::trash( $bfmt_id );
	$check( 'trash, not a permanent delete', 'trash' === get_post_status( $bfmt_id ), (string) get_post_status( $bfmt_id ) );

	wp_delete_post( $bfmt_id, true );
}

echo "\n== the list pages in the database, not in PHP ==\n";

// Seeded on purpose: on an empty site a paging assertion returns zero rows and
// passes without having tested anything. The old list loaded every row and
// sliced 25 out of it in PHP, so the only meaningful check is that more trays
// exist than one page holds.
$bfmt_seeded = [];

for ( $bfmt_n = 0; $bfmt_n < 30; $bfmt_n++ ) {
	$bfmt_new = BFMT_Tray::create( [
		'variety'      => 'Paging Check',
		'seeding_date' => gmdate( 'Y-m-d', strtotime( '2026-01-01' ) + ( $bfmt_n * DAY_IN_SECONDS ) ),
	] );

	if ( ! is_wp_error( $bfmt_new ) ) {
		$bfmt_seeded[] = $bfmt_new;
	}
}

$bfmt_query = new WP_Query( [
	'post_type'      => BFMT_Tray::POST_TYPE,
	'post_status'    => 'publish',
	'posts_per_page' => 25,
	'fields'         => 'ids',
] );

$check( 'more trays exist than one page holds', (int) $bfmt_query->found_posts > 25, (int) $bfmt_query->found_posts . ' found' );
$check( 'the query returns one page, not all of them', 25 === count( $bfmt_query->posts ), count( $bfmt_query->posts ) . ' returned' );
$check( 'the total is still reported for pagination', (int) $bfmt_query->max_num_pages > 1, $bfmt_query->max_num_pages . ' pages' );

foreach ( $bfmt_seeded as $bfmt_temp ) {
	wp_delete_post( $bfmt_temp, true );
}

printf( "\n%s\n\n", 0 === $bfmt_failures ? 'All checks passed.' : "{$bfmt_failures} check(s) failed." );
