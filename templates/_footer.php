<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are render-local state.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

wp_app_enqueue_script(
    'cookbook',
    plugins_url( 'assets/cookbook.js', dirname( __DIR__ ) . '/cookbook.php' ),
    array(),
    filemtime( dirname( __DIR__ ) . '/assets/cookbook.js' ),
    true,
    'cookbook'
);
?>
    </main>

    <?php wp_app_body_close(); ?>
</body>
</html>
