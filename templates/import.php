<?php
use Recipes\App;

if ( ! current_user_can( 'edit_posts' ) ) {
    wp_die( 'Not allowed.', 403 );
}

$error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';

include __DIR__ . '/_header.php';
?>
<a class="badge" href="<?php echo esc_url( home_url( '/recipes/' ) ); ?>">← All recipes</a>
<h1>Import a recipe</h1>
<p class="subtitle">Paste a URL from a recipe site, or paste the recipe text itself.</p>

<?php if ( $error === 'parse' ) : ?>
    <div class="notice error">Could not detect a recipe. Try pasting the text below instead.</div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( 'recipes_import' ); ?>
    <input type="hidden" name="action" value="recipes_import">

    <label for="source_url">Recipe URL</label>
    <input id="source_url" type="url" name="source_url" placeholder="https://example.com/some-recipe" autofocus>
    <p class="help">We look for schema.org Recipe metadata, which most major recipe sites publish.</p>

    <label for="paste">…or paste the recipe text</label>
    <textarea id="paste" name="paste" style="min-height:14rem" placeholder="Title&#10;&#10;Ingredients&#10;2 cups flour&#10;1 tsp salt&#10;…&#10;&#10;Instructions&#10;Mix everything…"></textarea>
    <p class="help">Use "Ingredients" / "Instructions" headers if you can — it makes parsing more reliable. The result will land in your drafts so you can review and tidy it up.</p>

    <div class="toolbar">
        <button class="btn" type="submit">Import</button>
        <a class="btn secondary" href="<?php echo esc_url( home_url( '/recipes/' ) ); ?>">Cancel</a>
    </div>
</form>

<?php include __DIR__ . '/_footer.php'; ?>
