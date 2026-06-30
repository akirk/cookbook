<?php
/**
 * PHPUnit bootstrap — stubs the small slice of WordPress that Importer + Units rely on,
 * defines ABSPATH so the plugin's direct-access guards don't exit, and loads the
 * classes under test that do not depend on App's autoloaded services.
 */

define( 'ABSPATH', __DIR__ . '/' );

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( $text ) {
        $text = preg_replace( '#<(script|style)[^>]*>.*?</\\1>#si', '', (string) $text );
        return trim( strip_tags( $text ) );
    }
}
if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) { return false; }
}
if ( ! function_exists( 'absint' ) ) {
    function absint( $value ) { return abs( (int) $value ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $text ) {
        return trim( wp_strip_all_tags( (string) $text ) );
    }
}
if ( ! function_exists( 'wp_kses_post' ) ) {
    function wp_kses_post( $text ) { return (string) $text; }
}
if ( ! function_exists( 'wp_remote_get' ) ) {
    function wp_remote_get( $url, $args = [] ) {
        $GLOBALS['wp_remote_get_calls'][] = [ $url, $args ];
        if ( isset( $GLOBALS['wp_remote_get_mock'] ) && is_callable( $GLOBALS['wp_remote_get_mock'] ) ) {
            return $GLOBALS['wp_remote_get_mock']( $url, $args );
        }
        return [ 'body' => '' ];
    }
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
    function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? ( $r['body'] ?? '' ) : ''; }
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
    function wp_remote_retrieve_response_code( $r ) {
        if ( ! is_array( $r ) ) return 0;
        if ( isset( $r['response']['code'] ) ) return (int) $r['response']['code'];
        if ( isset( $r['code'] ) ) return (int) $r['code'];
        return 200;
    }
}
if ( ! function_exists( 'wp_remote_retrieve_header' ) ) {
    function wp_remote_retrieve_header( $r, $name ) {
        if ( ! is_array( $r ) || empty( $r['headers'] ) || ! is_array( $r['headers'] ) ) {
            return '';
        }
        $name = strtolower( (string) $name );
        foreach ( $r['headers'] as $header => $value ) {
            if ( strtolower( (string) $header ) === $name ) {
                return $value;
            }
        }
        return '';
    }
}

require_once dirname( __DIR__ ) . '/src/Units.php';
require_once dirname( __DIR__ ) . '/src/Importer.php';
require_once dirname( __DIR__ ) . '/src/AbstractService.php';
require_once dirname( __DIR__ ) . '/src/RecipeService.php';
