<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- variables here are template-local, not actually global.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
use Cookbook\App;

if ( ! is_user_logged_in() ) {
    wp_die( esc_html__( 'Not allowed.', 'cookbook' ), 403 );
}

$pref = App::get_user_unit_preference();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- redirect-back flag, harmless to read.
$saved = isset( $_GET['saved'] );

$page_title = __( 'Settings', 'cookbook' );
include __DIR__ . '/_header.php';
?>
<a class="badge" href="<?php echo esc_url( home_url( '/cookbook/' ) ); ?>"><?php esc_html_e( '← All recipes', 'cookbook' ); ?></a>
<h1><?php esc_html_e( 'Settings', 'cookbook' ); ?></h1>

<?php if ( $saved ) : ?>
    <div class="notice success"><?php esc_html_e( 'Settings saved.', 'cookbook' ); ?></div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( 'cookbook_settings' ); ?>
    <input type="hidden" name="action" value="cookbook_settings">

    <label><?php esc_html_e( 'Preferred unit system', 'cookbook' ); ?></label>
    <p class="help"><?php esc_html_e( 'Recipes are stored in their original units. We convert on display when the system differs from your preference.', 'cookbook' ); ?></p>
    <p>
        <label style="display:inline-block;font-weight:normal;margin-right:1rem">
            <input type="radio" name="unit_preference" value="metric" <?php checked( $pref, 'metric' ); ?>>
            <?php esc_html_e( 'Metric (g, kg, ml, l)', 'cookbook' ); ?>
        </label>
        <label style="display:inline-block;font-weight:normal">
            <input type="radio" name="unit_preference" value="imperial" <?php checked( $pref, 'imperial' ); ?>>
            <?php esc_html_e( 'Imperial (oz, lb, tsp, tbsp, cup)', 'cookbook' ); ?>
        </label>
    </p>

    <div class="toolbar">
        <button class="btn" type="submit"><?php esc_html_e( 'Save settings', 'cookbook' ); ?></button>
    </div>
</form>

<?php include __DIR__ . '/_footer.php'; ?>
