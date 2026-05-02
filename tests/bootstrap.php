<?php
/**
 * PHPUnit bootstrap — stubs the small slice of WordPress that Importer + Units rely on,
 * defines ABSPATH so the plugin's direct-access guards don't exit, and loads the
 * classes under test directly from src/ (no autoloader needed for tests).
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
if ( ! function_exists( 'wp_remote_get' ) ) {
    function wp_remote_get( $url, $args = [] ) { return [ 'body' => '' ]; }
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
    function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? ( $r['body'] ?? '' ) : ''; }
}

require_once dirname( __DIR__ ) . '/src/Units.php';
require_once dirname( __DIR__ ) . '/src/Importer.php';
require_once dirname( __DIR__ ) . '/src/IngredientMatcher.php';
