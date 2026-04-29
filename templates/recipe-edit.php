<?php
use Recipes\App;

if ( ! current_user_can( 'edit_posts' ) ) {
    wp_die( 'Not allowed.', 403 );
}

$id = (int) get_query_var( 'id' );
$post = $id ? get_post( $id ) : null;
if ( ! $post || $post->post_type !== App::POST_TYPE ) {
    status_header( 404 );
    include __DIR__ . '/_header.php';
    echo '<h1>Not found</h1>';
    include __DIR__ . '/_footer.php';
    return;
}
$is_new = false;

include __DIR__ . '/_header.php';
?>
<a class="badge" href="<?php echo esc_url( home_url( '/recipes/recipe/' . $id ) ); ?>">← Back to recipe</a>
<h1>Edit recipe</h1>
<?php include __DIR__ . '/_form.php'; ?>
<?php include __DIR__ . '/_footer.php'; ?>
