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

// App init runs on init (not plugins_loaded) because BaseApp::init() calls
// setup_menu() which translates strings via __() — those need the textdomain
// loaded above, otherwise WP 6.7+ logs a "_load_textdomain_just_in_time was
// called incorrectly" notice. Both callbacks fire at init priority 10; the
// textdomain action above is registered first, so it runs first.
add_action( 'init', function() {
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
