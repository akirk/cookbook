<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- variables here are template-local, not actually global.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
use Cookbook\App;

if ( ! current_user_can( 'edit_posts' ) ) {
    wp_die( esc_html__( 'Not allowed.', 'cookbook' ), 403 );
}

$id = (int) get_query_var( 'id' );
$post = $id ? get_post( $id ) : null;
if ( ! $post || $post->post_type !== App::POST_TYPE ) {
    status_header( 404 );
    $page_title = __( 'Recipe not found', 'cookbook' );
    include __DIR__ . '/_header.php';
    echo '<h1>' . esc_html__( 'Not found', 'cookbook' ) . '</h1>';
    include __DIR__ . '/_footer.php';
    return;
}
$is_new = false;
$page_title = sprintf(
    /* translators: %s: recipe title */
    __( 'Edit %s', 'cookbook' ),
    get_the_title( $post )
);

include __DIR__ . '/_header.php';
?>
<div class="toolbar" style="margin-top:0">
    <a class="badge" href="<?php echo esc_url( home_url( '/cookbook/recipe/' . $id ) ); ?>"><?php esc_html_e( '← Back to recipe', 'cookbook' ); ?></a>
    <span class="spacer"></span>
    <button class="btn" type="submit" form="recipe-form"><?php esc_html_e( 'Save recipe', 'cookbook' ); ?></button>
</div>
<h1>
    <?php esc_html_e( 'Edit recipe', 'cookbook' ); ?>
    <?php if ( $post->post_status === 'draft' ) : ?>
        <span class="badge"><?php esc_html_e( 'draft', 'cookbook' ); ?></span>
    <?php endif; ?>
</h1>
<?php include __DIR__ . '/_form.php'; ?>
<?php include __DIR__ . '/_footer.php'; ?>
