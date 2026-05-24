<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- variables here are template-local, not actually global.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
use Cookbook\App;

$entries = App::get_user_cooked_entries();
$entries_by_date = [];

foreach ( $entries as $entry ) {
    $recipe_id = (int) get_post_meta( $entry->ID, App::META_COOKED_RECIPE_ID, true );
    $date      = App::sanitize_cooked_date( (string) get_post_meta( $entry->ID, App::META_COOKED_DATE, true ) );
    $recipe    = $recipe_id ? get_post( $recipe_id ) : null;

    if ( ! isset( $entries_by_date[ $date ] ) ) {
        $entries_by_date[ $date ] = [];
    }

    $entries_by_date[ $date ][] = [
        'entry'     => $entry,
        'recipe'    => $recipe && $recipe->post_type === App::POST_TYPE ? $recipe : null,
        'recipe_id' => $recipe_id,
    ];
}

$page_title = __( 'Cooked', 'cookbook' );
include __DIR__ . '/_header.php';
?>
<div class="page-head">
    <div>
        <h1><?php esc_html_e( 'Cooked', 'cookbook' ); ?></h1>
        <p class="subtitle">
            <?php
            echo esc_html( sprintf(
                /* translators: %d: cooked-history entries */
                _n( '%d saved cook.', '%d saved cooks.', count( $entries ), 'cookbook' ),
                count( $entries )
            ) );
            ?>
        </p>
    </div>
    <div class="page-actions">
        <a class="btn secondary" href="<?php echo esc_url( home_url( '/cookbook/' ) ); ?>"><?php esc_html_e( 'All recipes', 'cookbook' ); ?></a>
    </div>
</div>

<?php if ( ! $entries_by_date ) : ?>
    <div class="notice"><?php esc_html_e( 'No cooked history yet. Save a cooked date from any recipe page.', 'cookbook' ); ?></div>
<?php else : ?>
    <?php foreach ( $entries_by_date as $date => $date_entries ) : ?>
        <section class="recipe-alpha-section">
            <h2>
                <?php echo esc_html( App::format_cooked_date( $date ) ); ?>
                <span class="cooked-count">
                    <?php
                    echo esc_html( sprintf(
                        /* translators: %d: cooked-history entries on this date */
                        _n( '%d recipe', '%d recipes', count( $date_entries ), 'cookbook' ),
                        count( $date_entries )
                    ) );
                    ?>
                </span>
            </h2>
            <ul class="cooked-day-list">
                <?php foreach ( $date_entries as $item ) :
                    $recipe = $item['recipe'];
                    ?>
                    <li>
                        <?php if ( $recipe ) : ?>
                            <a href="<?php echo esc_url( home_url( '/cookbook/recipe/' . $recipe->ID ) ); ?>"><?php echo esc_html( get_the_title( $recipe ) ); ?></a>
                        <?php else : ?>
                            <span><?php echo esc_html( get_the_title( $item['entry'] ) ); ?></span>
                        <?php endif; ?>
                        <time datetime="<?php echo esc_attr( $date ); ?>"><?php echo esc_html( App::format_cooked_date( $date ) ); ?></time>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>
