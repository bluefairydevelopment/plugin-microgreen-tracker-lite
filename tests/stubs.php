<?php
/**
 * Just enough WordPress for the code under test.
 *
 * Every stub that stands in for a side effect RECORDS the call in
 * $GLOBALS['bfmt_calls'], so a test asserts the call was made — with which
 * arguments — rather than merely that nothing errored. A stub that silently
 * returns true cannot tell a wired hook from a deleted one.
 *
 * State the stubs read from, reset by bfmt_test_reset():
 *   bfmt_meta[id][key]      post meta
 *   bfmt_types[id]          post type of an id
 *   bfmt_terms[id]          get_the_terms() answer
 *   bfmt_filters[tag]       ONE callback apply_filters() runs for that tag
 *   bfmt_options[name]      options
 *   bfmt_transients[name]   transients
 *   bfmt_caps[cap]          current_user_can() answers (default false)
 *   bfmt_request            copied into $_GET/$_POST/$_REQUEST by a test
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/' );
defined( 'DAY_IN_SECONDS' ) || define( 'DAY_IN_SECONDS', 86400 );

/**
 * wp_die() and the redirect/exit path throw this, so a handler that would end
 * the request returns control to the test instead.
 */
final class BFMT_Test_Halt extends RuntimeException {
	public function __construct( public readonly string $kind, public readonly mixed $payload = null ) {
		parent::__construct( $kind );
	}
}

/**
 * The default $wpdb: a site where Lite's table does not exist. Tests of the
 * importer replace it with a double that answers.
 */
final class BFMT_Test_No_Legacy_Wpdb {
	public string $prefix = 'wp_';
	public function esc_like( $text ) { return $text; }
	public function prepare( $query, ...$args ) { return $query; }
	public function get_var( $query ) { return null; }
	public function get_results( $query ) { return []; }
}

function bfmt_test_reset(): void {
	$GLOBALS['bfmt_calls']        = [];
	$GLOBALS['bfmt_meta']         = [];
	$GLOBALS['bfmt_types']        = [];
	$GLOBALS['bfmt_terms']        = [];
	$GLOBALS['bfmt_filters']      = [];
	$GLOBALS['bfmt_options']      = [];
	$GLOBALS['bfmt_transients']   = [];
	$GLOBALS['bfmt_caps']         = [];
	$GLOBALS['bfmt_is_admin']     = false;
	$GLOBALS['bfmt_posts_result'] = [];
	$GLOBALS['bfmt_next_post_id'] = 100;
	$GLOBALS['bfmt_insert_error'] = null;
	$GLOBALS['bfmt_query_args']   = null;
	$GLOBALS['bfmt_query_result'] = [ 'posts' => [], 'found_posts' => 0, 'max_num_pages' => 0 ];
	$GLOBALS['bfmt_statuses']     = [];
	$GLOBALS['bfmt_nonce_ok']     = true;
	$GLOBALS['wpdb']              = new BFMT_Test_No_Legacy_Wpdb();
	$_GET = $_POST = $_REQUEST = [];
}

bfmt_test_reset();

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

/**
 * The order in which calls happened, as "fn:first-arg" strings.
 *
 * @return list<string>
 */
function bfmt_test_sequence(): array {
	return array_map(
		static fn( array $call ): string => $call[0] . ':' . ( is_scalar( $call[1][0] ?? null ) ? (string) $call[1][0] : '' ),
		$GLOBALS['bfmt_calls']
	);
}

function bfmt_test_request( array $get = [], array $post = [] ): void {
	$_GET     = $get;
	$_POST    = $post;
	$_REQUEST = array_merge( $get, $post );
}

// ---------------------------------------------------------------------------
// i18n and escaping
// ---------------------------------------------------------------------------

/**
 * Runs a 'gettext' callback if a test set one, so a test can hand the code a
 * hostile translation and assert it comes out escaped.
 */
function __( $text, $domain = null ) {
	$cb = $GLOBALS['bfmt_filters']['gettext'] ?? null;

	return $cb ? $cb( $text ) : $text;
}
function _n( $single, $plural, $number, $domain = null ) { return 1 === (int) $number ? $single : $plural; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_html__( $text, $domain = null ) { return esc_html( __( $text, $domain ) ); }
function esc_attr__( $text, $domain = null ) { return esc_attr( __( $text, $domain ) ); }
function esc_html_e( $text, $domain = null ) { echo esc_html__( $text, $domain ); }
function esc_url( $url ) { return htmlspecialchars( (string) $url, ENT_QUOTES ); }
function esc_js( $text ) { return addslashes( (string) $text ); }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function wp_unslash( $value ) { return $value; }

// ---------------------------------------------------------------------------
// Hooks
// ---------------------------------------------------------------------------

function add_action( $tag, $cb, $priority = 10, $args = 1 ) {
	bfmt_test_record( 'add_action', [ $tag, $cb, $priority ] );

	return true;
}

function add_filter( $tag, $cb, $priority = 10, $args = 1 ) {
	bfmt_test_record( 'add_filter', [ $tag, $cb, $priority ] );

	return true;
}

function do_action( $tag, ...$args ) {
	bfmt_test_record( 'do_action', array_merge( [ $tag ], $args ) );
}

function apply_filters( $tag, $value, ...$args ) {
	$cb = $GLOBALS['bfmt_filters'][ $tag ] ?? null;

	return $cb ? $cb( $value, ...$args ) : $value;
}

function register_activation_hook( $file, $cb ) { bfmt_test_record( 'register_activation_hook', [ $file, $cb ] ); }

// ---------------------------------------------------------------------------
// Plugin paths
// ---------------------------------------------------------------------------

function plugin_dir_path( $file ) { return dirname( (string) $file ) . '/'; }
function plugin_dir_url( $file ) { return 'https://example.test/wp-content/plugins/' . basename( dirname( (string) $file ) ) . '/'; }
function plugin_basename( $file ) { return basename( dirname( (string) $file ) ) . '/' . basename( (string) $file ); }

// ---------------------------------------------------------------------------
// Posts, meta, terms
// ---------------------------------------------------------------------------

function get_post_type( $id ) { return $GLOBALS['bfmt_types'][ $id ] ?? false; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['bfmt_meta'][ $id ][ $key ] ?? ''; }

/**
 * Like core: false when the stored value is already $value, so code that treats
 * false as failure is caught doing so.
 */
function update_post_meta( $id, $key, $value ) {
	bfmt_test_record( 'update_post_meta', [ $id, $key, $value ] );

	if ( array_key_exists( $key, $GLOBALS['bfmt_meta'][ $id ] ?? [] ) && $GLOBALS['bfmt_meta'][ $id ][ $key ] === $value ) {
		return false;
	}

	$GLOBALS['bfmt_meta'][ $id ][ $key ] = $value;

	return true;
}

function get_post_status( $id ) {
	return isset( $GLOBALS['bfmt_types'][ $id ] ) ? ( $GLOBALS['bfmt_statuses'][ $id ] ?? 'publish' ) : false;
}

function delete_post_meta( $id, $key ) {
	bfmt_test_record( 'delete_post_meta', [ $id, $key ] );
	unset( $GLOBALS['bfmt_meta'][ $id ][ $key ] );

	return true;
}

function metadata_exists( $type, $id, $key ) { return array_key_exists( $key, $GLOBALS['bfmt_meta'][ $id ] ?? [] ); }

function get_the_terms( $id, $taxonomy ) { return $GLOBALS['bfmt_terms'][ $id ] ?? false; }

function wp_set_object_terms( $id, $terms, $taxonomy, $append = false ) {
	bfmt_test_record( 'wp_set_object_terms', [ $id, $terms, $taxonomy, $append ] );
	$GLOBALS['bfmt_terms'][ $id ] = [ (object) [ 'name' => $terms, 'term_id' => 7 ] ];

	return [ 7 ];
}

function wp_insert_post( $args, $wp_error = false ) {
	bfmt_test_record( 'wp_insert_post', [ $args, $wp_error ] );

	if ( $GLOBALS['bfmt_insert_error'] ) {
		return $GLOBALS['bfmt_insert_error'];
	}

	$id = $GLOBALS['bfmt_next_post_id']++;
	$GLOBALS['bfmt_types'][ $id ] = $args['post_type'];

	return $id;
}

function wp_update_post( $args, $wp_error = false ) {
	bfmt_test_record( 'wp_update_post', [ $args, $wp_error ] );

	return $args['ID'];
}

function wp_trash_post( $id ) {
	bfmt_test_record( 'wp_trash_post', [ $id ] );

	return (object) [ 'ID' => $id ];
}

function get_posts( $args ) {
	bfmt_test_record( 'get_posts', [ $args ] );

	return $GLOBALS['bfmt_posts_result'];
}

function register_post_type( $type, $args ) { bfmt_test_record( 'register_post_type', [ $type, $args ] ); }
function register_taxonomy( $tax, $type, $args ) { bfmt_test_record( 'register_taxonomy', [ $tax, $type, $args ] ); }

final class WP_Error {
	public function __construct( private string $code = '', private string $message = '' ) {}
	public function get_error_code(): string { return $this->code; }
	public function get_error_message(): string { return $this->message; }
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

// ---------------------------------------------------------------------------
// Options and transients
// ---------------------------------------------------------------------------

function get_option( $name, $default = false ) { return $GLOBALS['bfmt_options'][ $name ] ?? $default; }

function update_option( $name, $value, $autoload = null ) {
	bfmt_test_record( 'update_option', [ $name, $value, $autoload ] );
	$GLOBALS['bfmt_options'][ $name ] = $value;

	return true;
}

function delete_option( $name ) {
	bfmt_test_record( 'delete_option', [ $name ] );
	unset( $GLOBALS['bfmt_options'][ $name ] );

	return true;
}

function set_transient( $name, $value, $ttl = 0 ) {
	bfmt_test_record( 'set_transient', [ $name, $value, $ttl ] );
	$GLOBALS['bfmt_transients'][ $name ] = $value;

	return true;
}

function get_transient( $name ) { return $GLOBALS['bfmt_transients'][ $name ] ?? false; }

function delete_transient( $name ) {
	bfmt_test_record( 'delete_transient', [ $name ] );
	unset( $GLOBALS['bfmt_transients'][ $name ] );

	return true;
}

// ---------------------------------------------------------------------------
// Users, nonces, requests
// ---------------------------------------------------------------------------

function get_current_user_id() { return 5; }
/**
 * Honours the format, so code asking for the wrong one gets a value that fails
 * the date check instead of a date the stub made up.
 */
function current_time( $type ) {
	return match ( $type ) {
		'mysql'     => '2026-09-30 12:00:00',
		'timestamp' => 1790769600,
		default     => date( (string) $type, 1790769600 ),
	};
}
function is_admin() { return (bool) $GLOBALS['bfmt_is_admin']; }

function current_user_can( $cap, ...$args ) {
	bfmt_test_record( 'current_user_can', array_merge( [ $cap ], $args ) );

	return (bool) ( $GLOBALS['bfmt_caps'][ $cap ] ?? false );
}

/**
 * Passes unless a test sets bfmt_nonce_ok = false; then it ends the request the
 * way core does (wp_nonce_ays), so a handler that writes before checking is
 * caught.
 */
function check_admin_referer( $action = -1, $name = '_wpnonce' ) {
	bfmt_test_record( 'check_admin_referer', [ $action ] );

	if ( ! $GLOBALS['bfmt_nonce_ok'] ) {
		throw new BFMT_Test_Halt( 'nonce', $action );
	}

	return 1;
}

function wp_die( $message = '' ) { throw new BFMT_Test_Halt( 'die', $message ); }

function wp_safe_redirect( $location, $status = 302 ) {
	throw new BFMT_Test_Halt( 'redirect', $location );
}

function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }

function add_query_arg( ...$params ) {
	// Both core forms: ( array $args, $url ) and ( $key, $value, $url ).
	[ $args, $url ] = is_array( $params[0] ) ? [ $params[0], $params[1] ] : [ [ $params[0] => $params[1] ], $params[2] ];

	return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $args );
}

function wp_nonce_url( $url, $action = -1 ) { return $url . '&_wpnonce=test-' . $action; }

function wp_nonce_field( $action = -1 ) {
	bfmt_test_record( 'wp_nonce_field', [ $action ] );
	echo '<input type="hidden" name="_wpnonce" value="test-' . esc_attr( (string) $action ) . '">';
}

function submit_button( $text = '' ) { echo '<button type="submit">' . esc_html( $text ) . '</button>'; }

// ---------------------------------------------------------------------------
// Admin
// ---------------------------------------------------------------------------

function add_menu_page( ...$args ) {
	bfmt_test_record( 'add_menu_page', $args );

	return 'toplevel_page_' . $args[3];
}

function load_plugin_textdomain( ...$args ) {
	bfmt_test_record( 'load_plugin_textdomain', $args );

	return true;
}

function wp_enqueue_style( ...$args ) { bfmt_test_record( 'wp_enqueue_style', $args ); }

function wp_add_inline_style( ...$args ) {
	bfmt_test_record( 'wp_add_inline_style', $args );

	return true;
}

function wp_enqueue_script( ...$args ) { bfmt_test_record( 'wp_enqueue_script', $args ); }

/**
 * Enough of core's list table for the subclass to run its own methods.
 */
class WP_List_Table {
	public array $items = [];
	public array $_column_headers = [];
	public array $pagination_args = [];
	public int $pagenum = 1;

	public function __construct( $args = [] ) {}
	public function get_pagenum() { return $this->pagenum; }
	public function set_pagination_args( $args ) { $this->pagination_args = $args; }
	public function display() { echo '<table class="wp-list-table"></table>'; }
}

/**
 * Records the query it was given and answers from bfmt_query_result.
 */
class WP_Query {
	public array $posts;
	public int $found_posts;
	public int $max_num_pages;

	public function __construct( array $args ) {
		$GLOBALS['bfmt_query_args'] = $args;
		$this->posts                = $GLOBALS['bfmt_query_result']['posts'];
		$this->found_posts          = $GLOBALS['bfmt_query_result']['found_posts'];
		$this->max_num_pages        = $GLOBALS['bfmt_query_result']['max_num_pages'];
	}
}
