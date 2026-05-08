<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- variables here are template-local, not actually global.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
use Cookbook\App;

if ( ! current_user_can( 'edit_posts' ) ) {
    wp_die( esc_html__( 'Not allowed.', 'cookbook' ), 403 );
}

$id     = 0;
$post   = null;
$is_new = true;

$page_title = __( 'New recipe', 'cookbook' );
include __DIR__ . '/_header.php';
?>
<a class="badge" href="<?php echo esc_url( home_url( '/cookbook/' ) ); ?>"><?php esc_html_e( '← All recipes', 'cookbook' ); ?></a>
<h1><?php esc_html_e( 'New recipe', 'cookbook' ); ?></h1>
<?php include __DIR__ . '/_form.php'; ?>
<?php include __DIR__ . '/_footer.php'; ?>
