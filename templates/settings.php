<?php
use Recipes\App;

if ( ! is_user_logged_in() ) {
    wp_die( 'Not allowed.', 403 );
}

$pref  = App::get_user_unit_preference();
$saved = isset( $_GET['saved'] );

include __DIR__ . '/_header.php';
?>
<a class="badge" href="<?php echo esc_url( home_url( '/recipes/' ) ); ?>">← All recipes</a>
<h1>Settings</h1>

<?php if ( $saved ) : ?>
    <div class="notice success">Settings saved.</div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( 'recipes_settings' ); ?>
    <input type="hidden" name="action" value="recipes_settings">

    <label>Preferred unit system</label>
    <p class="help">Recipes are stored in their original units. We convert on display when the system differs from your preference.</p>
    <p>
        <label style="display:inline-block;font-weight:normal;margin-right:1rem">
            <input type="radio" name="unit_preference" value="metric" <?php checked( $pref, 'metric' ); ?>>
            Metric (g, kg, ml, l)
        </label>
        <label style="display:inline-block;font-weight:normal">
            <input type="radio" name="unit_preference" value="imperial" <?php checked( $pref, 'imperial' ); ?>>
            Imperial (oz, lb, tsp, tbsp, cup)
        </label>
    </p>

    <div class="toolbar">
        <button class="btn" type="submit">Save settings</button>
    </div>
</form>

<?php include __DIR__ . '/_footer.php'; ?>
