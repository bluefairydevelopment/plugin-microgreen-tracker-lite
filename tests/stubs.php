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

if ( ! function_exists( 'sprintf_placeholder' ) ) {
	// Nothing: kept so the file has no trailing conditional.
}
