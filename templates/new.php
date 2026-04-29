<?php
use Recipes\App;

if ( ! current_user_can( 'edit_posts' ) ) {
    wp_die( 'Not allowed.', 403 );
}

$id     = 0;
$post   = null;
$is_new = true;

include __DIR__ . '/_header.php';
?>
<a class="badge" href="<?php echo esc_url( home_url( '/recipes/' ) ); ?>">← All recipes</a>
<h1>New recipe</h1>
<?php include __DIR__ . '/_form.php'; ?>
<?php include __DIR__ . '/_footer.php'; ?>
