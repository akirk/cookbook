<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- variables here are template-local, not actually global.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
use Recipes\App;

if ( ! current_user_can( 'edit_posts' ) ) {
    wp_die( esc_html__( 'Not allowed.', 'recipes' ), 403 );
}

$id     = 0;
$post   = null;
$is_new = true;

include __DIR__ . '/_header.php';
?>
<a class="badge" href="<?php echo esc_url( home_url( '/recipes/' ) ); ?>"><?php esc_html_e( '← All recipes', 'recipes' ); ?></a>
<h1><?php esc_html_e( 'New recipe', 'recipes' ); ?></h1>
<?php include __DIR__ . '/_form.php'; ?>
<?php include __DIR__ . '/_footer.php'; ?>
