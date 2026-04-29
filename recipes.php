<?php
/**
 * Plugin Name: Recipes
 * Description: A personal cookbook: store, categorize, scale and import recipes from the web.
 * Version: 1.0.0
 * Author: Alex Kirk
 * Text Domain: recipes
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace Recipes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';


// Autoloader for plugin classes.
spl_autoload_register( function( $class ) {
    $prefix = 'Recipes\\';
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
    // Self-hosted plugin: we still need to load translations explicitly. WordPress 4.6+
    // auto-loads only for plugins distributed through WordPress.org.
    load_plugin_textdomain( 'recipes', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
} );

add_action( 'plugins_loaded', function() {
    $app = new App();
    $app->init();
} );

register_activation_hook( __FILE__, function() {
    $app = new App();
    $app->activate();
} );

register_deactivation_hook( __FILE__, function() {
    flush_rewrite_rules();
} );
