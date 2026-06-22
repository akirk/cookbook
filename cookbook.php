<?php
/**
 * Plugin Name: Cookbook
 * Description: A personal cookbook: store, categorize, scale and import recipes from the web.
 * Version: 1.0.0
 * Author: Alex Kirk
 * Author URI: https://alex.kirk.at/
 * Text Domain: cookbook
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace Cookbook;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

// Autoloader for plugin classes.
spl_autoload_register( function( $class ) {
    $prefix = 'Cookbook\\';
    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class, $len ) ) . '.php';
    if ( file_exists( $file ) ) {
        require $file;
    }
} );

add_action( 'init', function() {
    $app = new App();
    load_plugin_textdomain( 'cookbook', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    $app->init();
} );

register_activation_hook( __FILE__, function() {
    $app = new App();
    $app->activate();
} );

register_deactivation_hook( __FILE__, function() {
    flush_rewrite_rules();
} );
