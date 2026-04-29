<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- variables here are template-local, not actually global.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
use Recipes\App;

if ( ! current_user_can( 'edit_posts' ) ) {
    wp_die( esc_html__( 'Not allowed.', 'recipes' ), 403 );
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- redirect-back error code, harmless to read.
$error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';

include __DIR__ . '/_header.php';
?>
<a class="badge" href="<?php echo esc_url( home_url( '/recipes/' ) ); ?>"><?php esc_html_e( '← All recipes', 'recipes' ); ?></a>
<h1><?php esc_html_e( 'Import a recipe', 'recipes' ); ?></h1>
<p class="subtitle"><?php esc_html_e( 'Paste a URL from a recipe site, or paste the recipe text itself.', 'recipes' ); ?></p>

<?php if ( $error === 'parse' ) : ?>
    <div class="notice error"><?php esc_html_e( 'Could not detect a recipe. Try pasting the text below instead.', 'recipes' ); ?></div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( 'recipes_import' ); ?>
    <input type="hidden" name="action" value="recipes_import">

    <label for="source_url"><?php esc_html_e( 'Recipe URL', 'recipes' ); ?></label>
    <input id="source_url" type="url" name="source_url" placeholder="https://example.com/some-recipe" autofocus>
    <p class="help"><?php esc_html_e( 'We look for schema.org Recipe metadata, which most major recipe sites publish.', 'recipes' ); ?></p>

    <label for="paste"><?php esc_html_e( '…or paste the recipe text', 'recipes' ); ?></label>
    <textarea id="paste" name="paste" style="min-height:14rem" placeholder="<?php esc_attr_e( "Title\n\nIngredients\n2 cups flour\n1 tsp salt\n…\n\nInstructions\nMix everything…", 'recipes' ); ?>"></textarea>
    <p class="help"><?php esc_html_e( 'Use "Ingredients" / "Instructions" headers if you can — it makes parsing more reliable. The result will land in your drafts so you can review and tidy it up.', 'recipes' ); ?></p>

    <div class="toolbar">
        <button class="btn" type="submit"><?php esc_html_e( 'Import', 'recipes' ); ?></button>
        <a class="btn secondary" href="<?php echo esc_url( home_url( '/recipes/' ) ); ?>"><?php esc_html_e( 'Cancel', 'recipes' ); ?></a>
    </div>
</form>

<?php include __DIR__ . '/_footer.php'; ?>
