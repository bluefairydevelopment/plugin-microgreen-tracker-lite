<?php
/**
 * Just enough WordPress for the logic under test.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ );

$GLOBALS['bfmt_meta']    = [];
$GLOBALS['bfmt_types']   = [];
$GLOBALS['bfmt_filters'] = [];
$GLOBALS['bfmt_options'] = [];

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) { return $text; }
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $tag, $cb, $priority = 10, $args = 1 ) { return true; }
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $tag, ...$args ) {}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) {
		$cb = $GLOBALS['bfmt_filters'][ $tag ] ?? null;

		return $cb ? $cb( $value, ...$args ) : $value;
	}
}

if ( ! function_exists( 'get_post_type' ) ) {
	function get_post_type( $id ) { return $GLOBALS['bfmt_types'][ $id ] ?? false; }
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['bfmt_meta'][ $id ][ $key ] ?? ''; }
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $id, $key, $value ) {
		$GLOBALS['bfmt_meta'][ $id ][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) { return $GLOBALS['bfmt_options'][ $name ] ?? $default; }
}

if ( ! function_exists( 'get_the_terms' ) ) {
	function get_the_terms( $id, $taxonomy ) { return $GLOBALS['bfmt_terms'][ $id ] ?? false; }
}

// ---------------------------------------------------------------------------
// Admin-side stubs. Each records what it was asked to do in $GLOBALS['bfmt_calls']
// so a test can assert on the call, not merely on the absence of an error.
// ---------------------------------------------------------------------------

$GLOBALS['bfmt_calls']    = [];
$GLOBALS['bfmt_is_admin'] = false;

defined( 'BFMT_VERSION' )  || define( 'BFMT_VERSION', '9.9.9-test' );
defined( 'BFMT_URL' )      || define( 'BFMT_URL', 'https://example.test/wp-content/plugins/bluefairy-microgreen-tracker/' );
defined( 'BFMT_BASENAME' ) || define( 'BFMT_BASENAME', 'bluefairy-microgreen-tracker/bluefairy-microgreen-tracker.php' );

function bfmt_test_record( string $fn, array $args ): void {
	$GLOBALS['bfmt_calls'][] = [ $fn, $args ];
}

/**
 * @return list<array> the argument lists of every recorded call to $fn
 */
function bfmt_test_calls( string $fn ): array {
	return array_values( array_map(
		static fn( array $call ): array => $call[1],
		array_filter( $GLOBALS['bfmt_calls'], static fn( array $call ): bool => $call[0] === $fn )
	) );
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = null ) { return esc_html( $text ); }
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = null ) { return esc_attr( $text ); }
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) { return htmlspecialchars( (string) $url, ENT_QUOTES ); }
}

if ( ! function_exists( 'esc_js' ) ) {
	function esc_js( $text ) { return addslashes( (string) $text ); }
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) { return abs( (int) $value ); }
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $args, $url ) {
		return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $args );
	}
}

if ( ! function_exists( 'wp_nonce_url' ) ) {
	function wp_nonce_url( $url, $action = -1 ) { return $url . '&_wpnonce=test-' . $action; }
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $cb, $priority = 10, $args = 1 ) {
		bfmt_test_record( 'add_filter', [ $tag, $cb ] );

		return true;
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() { return (bool) $GLOBALS['bfmt_is_admin']; }
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( ...$args ) {
		bfmt_test_record( 'load_plugin_textdomain', $args );

		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( ...$args ) { bfmt_test_record( 'wp_enqueue_style', $args ); }
}

if ( ! function_exists( 'wp_add_inline_style' ) ) {
	function wp_add_inline_style( ...$args ) {
		bfmt_test_record( 'wp_add_inline_style', $args );

		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( ...$args ) { bfmt_test_record( 'wp_enqueue_script', $args ); }
}

if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args ) { return $GLOBALS['bfmt_posts_result'] ?? []; }
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['bfmt_options'][ $name ] = $value;

		return true;
	}
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	/**
	 * Enough of core's list table for a subclass to be constructed and its
	 * column renderers called directly.
	 */
	class WP_List_Table {
		public function __construct( $args = [] ) {}
	}
}
