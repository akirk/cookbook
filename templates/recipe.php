<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- variables here are template-local, not actually global.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
use Cookbook\App;
use Cookbook\Importer;
use Cookbook\Units;

$id = (int) get_query_var( 'id' );
$post = $id ? get_post( $id ) : null;
if ( ! $post || $post->post_type !== App::POST_TYPE ) {
    status_header( 404 );
    $page_title = __( 'Recipe not found', 'cookbook' );
    include __DIR__ . '/_header.php';
    echo '<h1>' . esc_html__( 'Not found', 'cookbook' ) . '</h1><p>' . esc_html__( 'That recipe does not exist.', 'cookbook' ) . '</p>';
    include __DIR__ . '/_footer.php';
    return;
}
$page_title = get_the_title( $post );

$servings_default = max( 1, (int) get_post_meta( $id, App::META_SERVINGS, true ) ?: 4 );
$prep             = (int) get_post_meta( $id, App::META_PREP, true );
$cook             = (int) get_post_meta( $id, App::META_COOK, true );
$ingredients      = (array) get_post_meta( $id, App::META_INGREDIENTS, true );
$instructions     = (array) get_post_meta( $id, App::META_INSTRUCTIONS, true );
$recipe_parts     = App::get_recipe_parts( $id );
$source_url       = (string) get_post_meta( $id, App::META_SOURCE_URL, true );
$notes            = (string) get_post_meta( $id, App::META_NOTES, true );

$clean_instructions = [];
foreach ( $instructions as $step ) {
    $step = Importer::clean_step( (string) $step );
    if ( $step !== '' ) {
        $clean_instructions[] = $step;
    }
}

$ingredient_parts = [];
$clean_instruction_parts = [];
foreach ( $recipe_parts as $part ) {
    if ( ! empty( $part['ingredients'] ) ) {
        $ingredient_parts[] = $part;
    }

    $part_steps = [];
    foreach ( (array) ( $part['instructions'] ?? [] ) as $step ) {
        $step = Importer::clean_step( (string) $step );
        if ( $step !== '' ) {
            $part_steps[] = $step;
        }
    }
    if ( $part_steps ) {
        $clean_instruction_parts[] = [
            'title' => (string) ( $part['title'] ?? '' ),
            'instructions' => $part_steps,
        ];
    }
}
if ( $clean_instruction_parts ) {
    $clean_instructions = [];
    foreach ( $clean_instruction_parts as $part ) {
        $clean_instructions = array_merge( $clean_instructions, $part['instructions'] );
    }
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display preference, validated against an allow-list below.
$units_param = isset( $_GET['units'] ) ? sanitize_text_field( wp_unslash( $_GET['units'] ) ) : '';
$preference  = in_array( $units_param, [ 'metric', 'imperial' ], true )
    ? $units_param
    : App::get_user_unit_preference();

$cats     = wp_get_object_terms( $id, App::TAX_CATEGORY );
$cuisines = wp_get_object_terms( $id, App::TAX_CUISINE );
$tags     = wp_get_object_terms( $id, App::TAX_TAG );
$variation_parent = App::get_recipe_variation_parent( $id );
$variation_root_id = App::get_recipe_variation_root_id( $id );
$variation_family = App::get_recipe_variation_family( $id );

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flash code.
$refetch_status = isset( $_GET['refetch'] ) ? sanitize_text_field( wp_unslash( $_GET['refetch'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flash code.
$shopping_status = isset( $_GET['shopping'] ) ? sanitize_text_field( wp_unslash( $_GET['shopping'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flash code.
$shopping_items = isset( $_GET['items'] ) ? absint( $_GET['items'] ) : 0;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flash code.
$shopping_household = isset( $_GET['household'] ) ? absint( $_GET['household'] ) : 0;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flash code.
$replaced = isset( $_GET['replaced'] );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flash code.
$cooked_status = isset( $_GET['cooked'] ) ? sanitize_text_field( wp_unslash( $_GET['cooked'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flash code.
$cooked_flash_date = isset( $_GET['cooked_date'] ) ? App::sanitize_cooked_date( sanitize_text_field( wp_unslash( $_GET['cooked_date'] ) ) ) : '';

$today_date      = wp_date( 'Y-m-d' );
$recipe_url      = home_url( '/cookbook/recipe/' . $id );
$cooked_entries  = App::get_recipe_cooked_entries( $id, -1 );
$cooked_count    = count( $cooked_entries );
$last_cooked_date = $cooked_entries
    ? (string) get_post_meta( $cooked_entries[0]->ID, App::META_COOKED_DATE, true )
    : '';
$recent_cooked_entries = array_slice( $cooked_entries, 0, 5 );

include __DIR__ . '/_header.php';
?>
<h1><?php echo esc_html( get_the_title( $post ) ); ?></h1>

<?php if ( has_post_thumbnail( $id ) ) : ?>
    <?php echo get_the_post_thumbnail( $id, 'large', [
        'style' => 'max-width:100%;height:auto;border-radius:8px;margin:0.5rem 0 1rem',
        'alt'   => esc_attr( get_the_title( $post ) ),
    ] ); ?>
<?php endif; ?>

<div class="meta">
    <?php if ( $prep ) : ?>
        <span>
            <?php
            /* translators: %d: prep time in minutes */
            echo esc_html( sprintf( __( 'Prep: %d min', 'cookbook' ), $prep ) );
            ?>
        </span>
    <?php endif; ?>
    <?php if ( $cook ) : ?>
        <span>
            <?php
            /* translators: %d: cook time in minutes */
            echo esc_html( sprintf( __( 'Cook: %d min', 'cookbook' ), $cook ) );
            ?>
        </span>
    <?php endif; ?>
    <?php if ( $source_url ) : ?>
        <span>
            <?php esc_html_e( 'Source:', 'cookbook' ); ?>
            <a href="<?php echo esc_url( $source_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $source_url, PHP_URL_HOST ) ?: $source_url ); ?></a>
        </span>
    <?php endif; ?>
    <?php if ( $variation_parent && count( $variation_family ) <= 1 ) : ?>
        <span>
            <?php esc_html_e( 'Variation of:', 'cookbook' ); ?>
            <a href="<?php echo esc_url( home_url( '/cookbook/recipe/' . $variation_parent->ID ) ); ?>"><?php echo esc_html( get_the_title( $variation_parent ) ); ?></a>
        </span>
    <?php endif; ?>
</div>

<?php if ( ( ! is_wp_error( $cats ) && $cats ) || ( ! is_wp_error( $cuisines ) && $cuisines ) || ( ! is_wp_error( $tags ) && $tags ) ) : ?>
<p style="margin-top:0.75rem">
    <?php foreach ( (array) $cats as $t ) : ?>
        <a class="badge" href="<?php echo esc_url( home_url( '/cookbook/category/' . $t->slug ) ); ?>"><?php echo esc_html( $t->name ); ?></a>
    <?php endforeach; ?>
    <?php foreach ( (array) $cuisines as $t ) : ?>
        <span class="badge"><?php echo esc_html( $t->name ); ?></span>
    <?php endforeach; ?>
    <?php foreach ( (array) $tags as $t ) : ?>
        <a class="badge" href="<?php echo esc_url( home_url( '/cookbook/tag/' . $t->slug ) ); ?>">#<?php echo esc_html( $t->name ); ?></a>
    <?php endforeach; ?>
</p>
<?php endif; ?>

<?php if ( count( $variation_family ) > 1 ) : ?>
    <section class="variation-panel" aria-labelledby="recipe-variations-title">
        <div class="variation-panel-title">
            <strong id="recipe-variations-title"><?php esc_html_e( 'Recipe variations', 'cookbook' ); ?></strong>
        </div>
        <ul class="variation-list">
            <?php foreach ( $variation_family as $variation_item ) :
                $variation_post = $variation_item['post'];
                $variation_depth = min( 4, max( 0, (int) $variation_item['depth'] ) );
                $variation_indent = number_format( $variation_depth * 1.1, 1, '.', '' );
                $is_current_variation = (int) $variation_post->ID === $id;
                ?>
                <li style="margin-left:<?php echo esc_attr( $variation_indent ); ?>rem">
                    <?php if ( $is_current_variation ) : ?>
                        <strong><?php echo esc_html( get_the_title( $variation_post ) ); ?></strong>
                    <?php else : ?>
                        <a href="<?php echo esc_url( home_url( '/cookbook/recipe/' . $variation_post->ID ) ); ?>"><?php echo esc_html( get_the_title( $variation_post ) ); ?></a>
                    <?php endif; ?>
                    <?php if ( (int) $variation_post->ID === $variation_root_id ) : ?>
                        <span class="badge"><?php esc_html_e( 'base', 'cookbook' ); ?></span>
                    <?php endif; ?>
                    <?php if ( $is_current_variation ) : ?>
                        <span class="badge"><?php esc_html_e( 'current', 'cookbook' ); ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<div class="recipe-toolbar" role="group" aria-label="<?php esc_attr_e( 'Recipe controls', 'cookbook' ); ?>">
    <div class="recipe-toolbar-settings">
        <div class="portion-control">
            <label for="servings" style="margin:0"><?php esc_html_e( 'Servings:', 'cookbook' ); ?></label>
            <input id="servings" type="number" min="1" step="1" value="<?php echo (int) $servings_default; ?>" data-default="<?php echo (int) $servings_default; ?>">
        </div>
        <div class="unit-toggle" role="tablist" aria-label="<?php esc_attr_e( 'Unit system', 'cookbook' ); ?>">
            <button type="button" class="<?php echo $preference === 'metric' ? 'active' : ''; ?>" data-units="metric"><?php esc_html_e( 'Metric', 'cookbook' ); ?></button>
            <button type="button" class="<?php echo $preference === 'imperial' ? 'active' : ''; ?>" data-units="imperial"><?php esc_html_e( 'Imperial', 'cookbook' ); ?></button>
        </div>
    </div>

    <div class="recipe-cooked-status">
        <span><?php esc_html_e( 'Last cooked:', 'cookbook' ); ?></span>
        <?php if ( $last_cooked_date ) : ?>
            <a href="#cooked-history"><time datetime="<?php echo esc_attr( $last_cooked_date ); ?>"><?php echo esc_html( App::format_cooked_date( $last_cooked_date ) ); ?></time></a>
            <small>
                (
                <?php
                echo esc_html( sprintf(
                    /* translators: %d: number of times the recipe was cooked */
                    _n( '%d time', '%d times', $cooked_count, 'cookbook' ),
                    $cooked_count
                ) );
                ?>
                )
            </small>
        <?php else : ?>
            <strong><?php esc_html_e( 'Not yet', 'cookbook' ); ?></strong>
        <?php endif; ?>
    </div>

    <div class="recipe-primary-actions">
        <?php if ( $clean_instructions ) : ?>
            <button class="btn" type="button" id="cook-mode-open"><?php esc_html_e( 'Cook mode', 'cookbook' ); ?></button>
        <?php endif; ?>
        <?php if ( $ingredients ) : ?>
            <form class="recipe-inline-action" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'cookbook_add_to_shopping_list' ); ?>
                <input type="hidden" name="action" value="cookbook_add_to_shopping_list">
                <input type="hidden" name="recipe_id" value="<?php echo (int) $id; ?>">
                <input type="hidden" id="shopping-servings" name="servings" value="<?php echo (int) $servings_default; ?>">
                <button class="btn fresh" type="submit"><?php esc_html_e( 'Add to shopping list', 'cookbook' ); ?></button>
            </form>
        <?php endif; ?>

        <details class="recipe-action-menu">
            <summary class="btn secondary"><?php esc_html_e( 'More actions', 'cookbook' ); ?></summary>
            <div class="recipe-action-menu-panel">
                <?php if ( $ingredients ) : ?>
                    <a class="recipe-menu-action" href="<?php echo esc_url( add_query_arg( 'recipe_id', $id, home_url( '/cookbook/planner' ) ) ); ?>"><?php esc_html_e( 'Plan recipe', 'cookbook' ); ?></a>
                <?php endif; ?>

                <form class="recipe-menu-form cooked-log-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'cookbook_log_cooked' ); ?>
                    <input type="hidden" name="action" value="cookbook_log_cooked">
                    <input type="hidden" name="recipe_id" value="<?php echo (int) $id; ?>">
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url( $recipe_url ); ?>">
                    <label for="recipe-cooked-date"><?php esc_html_e( 'Cooked on', 'cookbook' ); ?></label>
                    <input id="recipe-cooked-date" type="date" name="cooked_date" value="<?php echo esc_attr( $today_date ); ?>" max="<?php echo esc_attr( $today_date ); ?>">
                    <button class="btn secondary" type="submit"><?php esc_html_e( 'Log cooked date', 'cookbook' ); ?></button>
                </form>

                <?php if ( $source_url ) : ?>
                    <form class="recipe-menu-action-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Re-fetch this recipe from its source URL? Ingredients, instructions, times and image will be replaced with the latest parsed data. Notes and tags are kept.', 'cookbook' ) ); ?>')">
                        <?php wp_nonce_field( 'cookbook_refetch' ); ?>
                        <input type="hidden" name="action" value="cookbook_refetch">
                        <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
                        <button class="recipe-menu-action" type="submit" title="<?php esc_attr_e( 'Re-import from source URL', 'cookbook' ); ?>"><?php esc_html_e( 'Refetch from source', 'cookbook' ); ?></button>
                    </form>
                <?php endif; ?>

                <a class="recipe-menu-action" href="<?php echo esc_url( home_url( '/cookbook/recipe/' . $id . '/edit' ) ); ?>"><?php esc_html_e( 'Edit recipe', 'cookbook' ); ?></a>
                <?php if ( current_user_can( 'edit_posts' ) ) : ?>
                    <a class="recipe-menu-action" href="<?php echo esc_url( add_query_arg( 'variation_of', $id, home_url( '/cookbook/new' ) ) ); ?>"><?php esc_html_e( 'Edit as variation', 'cookbook' ); ?></a>
                <?php endif; ?>
            </div>
        </details>
    </div>
</div>

<?php if ( $refetch_status === 'ok' ) : ?>
    <div class="notice success"><?php esc_html_e( 'Refetched from source.', 'cookbook' ); ?></div>
<?php elseif ( $refetch_status === 'parse_error' ) : ?>
    <div class="notice error"><?php esc_html_e( 'Could not re-parse the source URL — recipe left unchanged.', 'cookbook' ); ?></div>
<?php elseif ( $refetch_status === 'no_url' ) : ?>
    <div class="notice error"><?php esc_html_e( 'No source URL stored on this recipe.', 'cookbook' ); ?></div>
<?php endif; ?>
<?php if ( $shopping_status === 'added' ) : ?>
    <div class="notice success">
        <?php
        $shopping_message = sprintf(
            /* translators: %d: shopping-list items */
            _n( '%d ingredient added to your shopping list.', '%d ingredients added to your shopping list.', $shopping_items, 'cookbook' ),
            $shopping_items
        );
        if ( $shopping_household ) {
            $shopping_message .= ' ' . sprintf(
                /* translators: %d: household ingredients */
                _n( '%d household ingredient listed as at home.', '%d household ingredients listed as at home.', $shopping_household, 'cookbook' ),
                $shopping_household
            );
        }
        echo esc_html( $shopping_message );
        ?>
    </div>
<?php endif; ?>
<?php if ( $replaced ) : ?>
    <div class="notice success"><?php esc_html_e( 'Ingredient replaced.', 'cookbook' ); ?></div>
<?php endif; ?>
<?php if ( $cooked_status === 'logged' && $cooked_flash_date ) : ?>
    <div class="notice success">
        <?php
        echo esc_html( sprintf(
            /* translators: %s: cooked date */
            __( 'Saved that you cooked this on %s.', 'cookbook' ),
            App::format_cooked_date( $cooked_flash_date )
        ) );
        ?>
    </div>
<?php elseif ( $cooked_status === 'exists' && $cooked_flash_date ) : ?>
    <div class="notice">
        <?php
        echo esc_html( sprintf(
            /* translators: %s: cooked date */
            __( 'This recipe was already saved for %s.', 'cookbook' ),
            App::format_cooked_date( $cooked_flash_date )
        ) );
        ?>
    </div>
<?php endif; ?>

<?php if ( $post->post_content ) : ?>
    <div class="description"><?php echo wp_kses_post( wpautop( $post->post_content ) ); ?></div>
<?php endif; ?>

<?php
$render_ingredient_row = function( array $ing, int $i ) use ( $preference, $id ) {
    $rendered = Units::render_ingredient( $ing, 1.0, $preference );
    $raw_amount = isset( $ing['amount'] ) ? $ing['amount'] : '';
    $parsed_amount = Units::parse_amount( $raw_amount );
    ?>
    <li
        data-amount="<?php echo esc_attr( $parsed_amount ?? '' ); ?>"
        data-amount-raw="<?php echo esc_attr( $raw_amount ); ?>"
        data-unit="<?php echo esc_attr( Units::normalize_unit( $ing['unit'] ?? '' ) ); ?>"
        class="ingredient-row"
    >
        <div class="ingredient-line">
            <span class="amt"><?php
                echo esc_html( trim( $rendered['amount'] . ' ' . $rendered['unit'] ) );
            ?></span>
            <span class="ingredient-name">
                <?php
                $ing_term_id = isset( $ing['term_id'] ) ? (int) $ing['term_id'] : 0;
                $ing_term    = $ing_term_id ? get_term( $ing_term_id, App::TAX_INGREDIENT ) : null;
                if ( $ing_term && ! is_wp_error( $ing_term ) ) :
                    ?>
                    <a href="<?php echo esc_url( home_url( '/cookbook/ingredient/' . $ing_term->slug ) ); ?>"><?php echo esc_html( $rendered['name'] ); ?></a>
                <?php else : ?>
                    <?php echo esc_html( $rendered['name'] ); ?>
                <?php endif; ?>
                <?php if ( ! empty( $rendered['notes'] ) ) : ?>
                    <span style="color:var(--muted)"> - <?php echo esc_html( $rendered['notes'] ); ?></span>
                <?php endif; ?>
            </span>
            <?php if ( current_user_can( 'edit_post', $id ) ) : ?>
                <span class="ingredient-actions">
                    <button type="button" class="ingredient-replace-toggle" data-replace-target="replace-ingredient-<?php echo (int) $i; ?>"><?php esc_html_e( 'Replace', 'cookbook' ); ?></button>
                </span>
            <?php endif; ?>
        </div>
        <?php if ( current_user_can( 'edit_post', $id ) ) : ?>
            <form id="replace-ingredient-<?php echo (int) $i; ?>" class="ingredient-replace-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" hidden>
                <?php wp_nonce_field( 'cookbook_replace_ingredient_' . $id . '_' . $i ); ?>
                <input type="hidden" name="action" value="cookbook_replace_ingredient">
                <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
                <input type="hidden" name="ingredient_index" value="<?php echo (int) $i; ?>">
                <input type="text" name="amount" value="<?php echo esc_attr( $ing['amount'] ?? '' ); ?>" placeholder="<?php esc_attr_e( '2', 'cookbook' ); ?>">
                <input type="text" name="unit" value="<?php echo esc_attr( $ing['unit'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'g', 'cookbook' ); ?>">
                <input type="text" name="name" value="" placeholder="<?php echo esc_attr( sprintf(
                    /* translators: %s: ingredient name */
                    __( 'Replace %s with...', 'cookbook' ),
                    $ing['name'] ?? ''
                ) ); ?>" required>
                <input type="text" name="notes" value="<?php echo esc_attr( $ing['notes'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'notes', 'cookbook' ); ?>">
                <button class="btn fresh" type="submit"><?php esc_html_e( 'Save', 'cookbook' ); ?></button>
                <button class="btn secondary ingredient-replace-cancel" type="button"><?php esc_html_e( 'Cancel', 'cookbook' ); ?></button>
            </form>
        <?php endif; ?>
    </li>
    <?php
};
?>

<h2><?php esc_html_e( 'Ingredients', 'cookbook' ); ?></h2>
<?php if ( ! $ingredients ) : ?>
    <p class="help">
        <?php
        printf(
            /* translators: %s: link to the recipe edit page */
            esc_html__( 'No ingredients yet. %s.', 'cookbook' ),
            '<a href="' . esc_url( home_url( '/cookbook/recipe/' . $id . '/edit' ) ) . '">' . esc_html__( 'Add some', 'cookbook' ) . '</a>'
        );
        ?>
    </p>
<?php else : ?>
<div id="ingredients" class="<?php echo $ingredient_parts ? 'ingredient-sections' : ''; ?>">
    <?php if ( $ingredient_parts ) : ?>
        <?php $ingredient_index = 0; ?>
        <?php foreach ( $ingredient_parts as $part ) : ?>
            <section class="recipe-part">
                <?php if ( ! empty( $part['title'] ) ) : ?>
                    <h3 class="recipe-part-title"><?php echo esc_html( $part['title'] ); ?></h3>
                <?php endif; ?>
                <ul class="ingredient-list">
                    <?php foreach ( (array) $part['ingredients'] as $ing ) : ?>
                        <?php $render_ingredient_row( $ing, $ingredient_index++ ); ?>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    <?php else : ?>
        <ul class="ingredient-list">
            <?php foreach ( $ingredients as $i => $ing ) : ?>
                <?php $render_ingredient_row( $ing, (int) $i ); ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php endif; ?>

<h2><?php esc_html_e( 'Instructions', 'cookbook' ); ?></h2>
<?php if ( ! $clean_instructions ) : ?>
    <p class="help"><?php esc_html_e( 'No instructions yet.', 'cookbook' ); ?></p>
<?php elseif ( $clean_instruction_parts ) : ?>
    <div class="instruction-sections">
        <?php foreach ( $clean_instruction_parts as $part ) : ?>
            <section class="recipe-part">
                <?php if ( ! empty( $part['title'] ) ) : ?>
                    <h3 class="recipe-part-title"><?php echo esc_html( $part['title'] ); ?></h3>
                <?php endif; ?>
                <ol class="instruction-list">
                    <?php foreach ( (array) $part['instructions'] as $step ) : ?>
                        <li><?php echo wp_kses_post( $step ); ?></li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php endforeach; ?>
    </div>
<?php else : ?>
<ol class="instruction-list">
    <?php foreach ( $clean_instructions as $step ) : ?>
        <li><?php echo wp_kses_post( $step ); ?></li>
    <?php endforeach; ?>
</ol>
<?php endif; ?>

<?php if ( $notes ) : ?>
    <h2><?php esc_html_e( 'Notes', 'cookbook' ); ?></h2>
    <div><?php echo wp_kses_post( wpautop( $notes ) ); ?></div>
<?php endif; ?>

<?php if ( $cooked_entries ) : ?>
    <h2 id="cooked-history"><?php esc_html_e( 'Cooking history', 'cookbook' ); ?></h2>
    <p class="subtitle">
        <?php
        echo esc_html( sprintf(
            /* translators: 1: number of times, 2: last cooked date */
            _n( 'Cooked %1$d time. Last: %2$s.', 'Cooked %1$d times. Last: %2$s.', $cooked_count, 'cookbook' ),
            $cooked_count,
            App::format_cooked_date( $last_cooked_date )
        ) );
        ?>
    </p>
    <ul class="cooked-history-list">
        <?php foreach ( $recent_cooked_entries as $entry ) :
            $entry_date = (string) get_post_meta( $entry->ID, App::META_COOKED_DATE, true );
            ?>
            <li>
                <span><?php esc_html_e( 'Cooked', 'cookbook' ); ?></span>
                <time datetime="<?php echo esc_attr( $entry_date ); ?>"><?php echo esc_html( App::format_cooked_date( $entry_date ) ); ?></time>
            </li>
        <?php endforeach; ?>
    </ul>
    <p>
        <a class="badge" href="<?php echo esc_url( home_url( '/cookbook/cooked' ) ); ?>"><?php esc_html_e( 'Cooking history', 'cookbook' ); ?></a>
    </p>
<?php endif; ?>

<?php if ( $clean_instructions ) : ?>
<div class="cook-mode" id="cook-mode" role="dialog" aria-modal="true" aria-labelledby="cook-mode-title" hidden>
    <div class="cook-mode-shell">
        <header class="cook-mode-topbar">
            <div class="cook-mode-title">
                <p class="cook-mode-kicker"><?php esc_html_e( 'Cook mode', 'cookbook' ); ?></p>
                <h2 id="cook-mode-title"><?php echo esc_html( get_the_title( $post ) ); ?></h2>
            </div>
            <button class="btn secondary" type="button" id="cook-mode-close"><?php esc_html_e( 'Exit', 'cookbook' ); ?></button>
        </header>

        <div class="cook-mode-layout">
            <?php if ( $ingredients ) : ?>
                <aside class="cook-mode-panel cook-mode-ingredients">
                    <h3><?php esc_html_e( 'Ingredients', 'cookbook' ); ?></h3>
                    <ul class="cook-ingredient-list">
                        <?php foreach ( $ingredients as $i => $ing ) :
                            $rendered = Units::render_ingredient( $ing, 1.0, $preference );
                            $quantity = trim( $rendered['amount'] . ' ' . $rendered['unit'] );
                            ?>
                            <li class="cook-ingredient" data-cook-ingredient-index="<?php echo (int) $i; ?>">
                                <label for="cook-ingredient-<?php echo (int) $i; ?>">
                                    <input id="cook-ingredient-<?php echo (int) $i; ?>" type="checkbox" data-cook-ingredient-check>
                                    <span class="cook-ingredient-amount"><?php echo esc_html( $quantity ); ?></span>
                                    <span class="cook-ingredient-name">
                                        <?php echo esc_html( $rendered['name'] ); ?>
                                        <?php if ( ! empty( $rendered['notes'] ) ) : ?>
                                            <span class="cook-ingredient-note"> - <?php echo esc_html( $rendered['notes'] ); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </aside>
            <?php endif; ?>

            <section class="cook-mode-main">
                <div class="cook-mode-panel cook-mode-progress">
                    <div class="cook-mode-progress-row">
                        <strong id="cook-step-count"></strong>
                        <span id="cook-step-done-count"></span>
                    </div>
                    <progress id="cook-step-progress" value="1" max="<?php echo (int) count( $clean_instructions ); ?>"></progress>
                    <p class="help" style="margin:0"><?php esc_html_e( 'Shortcuts: Space or Right arrow for next, Left arrow for previous, Escape to exit.', 'cookbook' ); ?></p>
                </div>

                <div class="cook-active-step" id="cook-active-step" tabindex="-1"></div>

                <div class="cook-mode-nav">
                    <label class="cook-active-check" for="cook-active-check">
                        <input id="cook-active-check" type="checkbox">
                        <?php esc_html_e( 'Step done', 'cookbook' ); ?>
                    </label>
                    <div class="cook-mode-nav-group">
                        <button class="btn secondary" type="button" id="cook-prev-step" aria-keyshortcuts="ArrowLeft" title="<?php esc_attr_e( 'Previous step (Left arrow)', 'cookbook' ); ?>"><?php esc_html_e( 'Previous', 'cookbook' ); ?></button>
                        <button class="btn" type="button" id="cook-next-step" aria-keyshortcuts="ArrowRight Space" title="<?php esc_attr_e( 'Next step (Space or Right arrow)', 'cookbook' ); ?>"><?php esc_html_e( 'Next', 'cookbook' ); ?></button>
                        <button class="btn secondary" type="button" id="cook-reset"><?php esc_html_e( 'Reset', 'cookbook' ); ?></button>
                    </div>
                </div>

                <div class="cook-mode-panel cook-finish" id="cook-finish" hidden>
                    <strong><?php esc_html_e( 'Save that you cooked this?', 'cookbook' ); ?></strong>
                    <p class="help"><?php esc_html_e( 'Add it to your cooking history.', 'cookbook' ); ?></p>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'cookbook_log_cooked' ); ?>
                        <input type="hidden" name="action" value="cookbook_log_cooked">
                        <input type="hidden" name="recipe_id" value="<?php echo (int) $id; ?>">
                        <input type="hidden" name="redirect_to" value="<?php echo esc_url( $recipe_url . '#cooked-history' ); ?>">
                        <label for="cook-finish-date"><?php esc_html_e( 'Cooked on', 'cookbook' ); ?></label>
                        <input id="cook-finish-date" type="date" name="cooked_date" value="<?php echo esc_attr( $today_date ); ?>" max="<?php echo esc_attr( $today_date ); ?>">
                        <button class="btn fresh" type="submit"><?php esc_html_e( 'Save cooked date', 'cookbook' ); ?></button>
                        <button class="btn secondary" type="button" id="cook-finish-dismiss"><?php esc_html_e( 'Not now', 'cookbook' ); ?></button>
                    </form>
                </div>

                <ol class="cook-step-list">
                    <?php foreach ( $clean_instructions as $i => $step ) : ?>
                        <li class="cook-step-row" data-cook-step-index="<?php echo (int) $i; ?>">
                            <div class="cook-step-full" hidden><?php echo wp_kses_post( $step ); ?></div>
                            <div class="cook-step-list-row">
                                <input
                                    id="cook-step-check-<?php echo (int) $i; ?>"
                                    class="cook-step-check"
                                    type="checkbox"
                                    data-cook-step-check
                                    aria-label="<?php echo esc_attr( sprintf(
                                        /* translators: %d: step number */
                                        __( 'Step %d done', 'cookbook' ),
                                        (int) $i + 1
                                    ) ); ?>"
                                >
                                <button class="cook-step-jump" type="button" data-cook-step-jump="<?php echo (int) $i; ?>">
                                    <span class="cook-step-list-index">
                                        <?php
                                        echo esc_html( sprintf(
                                            /* translators: %d: step number */
                                            __( 'Step %d', 'cookbook' ),
                                            (int) $i + 1
                                        ) );
                                        ?>
                                    </span>
                                    <span class="cook-step-list-text"><?php echo esc_html( wp_strip_all_tags( $step ) ); ?></span>
                                </button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
        </div>
    </div>
</div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:2rem" onsubmit="return confirm('<?php echo esc_js( __( 'Move this recipe to trash?', 'cookbook' ) ); ?>')">
    <?php wp_nonce_field( 'cookbook_delete' ); ?>
    <input type="hidden" name="action" value="cookbook_delete">
    <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
    <button class="btn danger" type="submit"><?php esc_html_e( 'Delete recipe', 'cookbook' ); ?></button>
</form>

<script>
(function () {
    const ingredients = document.getElementById('ingredients');
    const servingsInput = document.getElementById('servings');
    const shoppingServings = document.getElementById('shopping-servings');
    const unitButtons = document.querySelectorAll('.unit-toggle button');
    const editUrl = '<?php echo esc_js( home_url( '/cookbook/recipe/' . $id . '/edit' ) ); ?>';
    const cookMode = document.getElementById('cook-mode');
    const cookOpen = document.getElementById('cook-mode-open');
    const cookClose = document.getElementById('cook-mode-close');
    const cookActiveStep = document.getElementById('cook-active-step');
    const cookActiveCheck = document.getElementById('cook-active-check');
    const cookPrev = document.getElementById('cook-prev-step');
    const cookNext = document.getElementById('cook-next-step');
    const cookReset = document.getElementById('cook-reset');
    const cookProgress = document.getElementById('cook-step-progress');
    const cookStepCount = document.getElementById('cook-step-count');
    const cookDoneCount = document.getElementById('cook-step-done-count');
    const cookFinish = document.getElementById('cook-finish');
    const cookFinishDismiss = document.getElementById('cook-finish-dismiss');
    const cookStepRows = cookMode ? Array.from(cookMode.querySelectorAll('[data-cook-step-index]')) : [];
    const cookStepChecks = cookMode ? Array.from(cookMode.querySelectorAll('[data-cook-step-check]')) : [];
    const cookIngredientRows = cookMode ? Array.from(cookMode.querySelectorAll('[data-cook-ingredient-index]')) : [];
    const cookIngredientChecks = cookMode ? Array.from(cookMode.querySelectorAll('[data-cook-ingredient-check]')) : [];
    const cookStrings = {
        stepOf: <?php echo wp_json_encode( __( 'Step %1$d of %2$d', 'cookbook' ) ); ?>,
        doneCount: <?php echo wp_json_encode( __( '%1$d of %2$d done', 'cookbook' ) ); ?>,
        next: <?php echo wp_json_encode( __( 'Next', 'cookbook' ) ); ?>,
        finish: <?php echo wp_json_encode( __( 'Finish', 'cookbook' ) ); ?>
    };
    const cookStateKey = 'cookbook:cook-mode:<?php echo (int) $id; ?>';
    let activeCookStep = 0;
    let cookFinishDismissed = false;
    let wakeLock = null;

    document.addEventListener('keydown', (e) => {
        if (isCookModeOpen()) {
            if (e.key === 'Escape') {
                e.preventDefault();
                closeCookMode();
                return;
            }
            if (e.target && e.target.closest && e.target.closest('input, textarea, select, button, [contenteditable="true"]')) return;
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                setCookStep(activeCookStep - 1, true);
                return;
            }
            if (e.key === 'ArrowRight' || e.key === ' ' || e.code === 'Space') {
                e.preventDefault();
                advanceCookStep(true);
                return;
            }
        }
        if (e.defaultPrevented || e.key.toLowerCase() !== 'e' || e.metaKey || e.ctrlKey || e.altKey) return;
        const target = e.target;
        if (
            target.closest &&
            target.closest('input, textarea, select, button, [contenteditable="true"]')
        ) {
            return;
        }
        e.preventDefault();
        window.location.href = editUrl;
    });

    let preference = '<?php echo esc_js( $preference ); ?>';
    const baseServings = servingsInput ? (parseInt(servingsInput.dataset.default, 10) || 1) : 1;

    if (ingredients) {
        ingredients.addEventListener('click', (e) => {
            const toggle = e.target.closest('.ingredient-replace-toggle');
            if (toggle) {
                const form = document.getElementById(toggle.dataset.replaceTarget);
                if (!form) return;
                form.hidden = !form.hidden;
                if (!form.hidden) {
                    const input = form.querySelector('input[name="name"]');
                    if (input) input.focus();
                }
                return;
            }

            if (e.target.classList && e.target.classList.contains('ingredient-replace-cancel')) {
                const form = e.target.closest('.ingredient-replace-form');
                if (form) form.hidden = true;
            }
        });
    }

    // Conversion tables (kept in sync with src/Units.php).
    const MASS = { g:1, kg:1000, oz:28.3495, lb:453.592 };
    const VOLUME = { ml:1, l:1000, tsp:4.92892, tbsp:14.7868, floz:29.5735, cup:236.588, pt:473.176, qt:946.353, gal:3785.41 };
    // tsp/tbsp pass through both modes — see Units::system_of() in PHP.
    const IMPERIAL = ['oz','lb','cup','floz','pt','qt','gal'];
    const UNIT_LABEL = { g:'g', kg:'kg', ml:'ml', l:'l', oz:'oz', lb:'lb', tsp:'tsp', tbsp:'tbsp', floz:'fl oz', cup:'cup', pt:'pt', qt:'qt', gal:'gal' };

    function fmt(n, max) {
        if (Math.abs(n - Math.round(n)) < 0.05) return String(Math.round(n));
        return parseFloat(n.toFixed(max ?? 2)).toString();
    }

    function kindOf(unit) {
        if (unit in MASS) return 'mass';
        if (unit in VOLUME) return 'volume';
        return null;
    }

    function systemOf(unit) { return IMPERIAL.indexOf(unit) >= 0 ? 'imperial' : 'metric'; }

    function convert(amount, unit, pref) {
        const kind = kindOf(unit);
        if (kind === null) return { amount: fmt(amount), unit: unit ? (UNIT_LABEL[unit] || unit) : '' };
        if (systemOf(unit) === pref) {
            if (pref === 'metric') {
                if (unit === 'g'  && amount >= 1000) return { amount: fmt(amount/1000, 2), unit: 'kg' };
                if (unit === 'ml' && amount >= 1000) return { amount: fmt(amount/1000, 2), unit: 'l' };
            }
            return { amount: fmt(amount, amount < 10 ? 2 : 0), unit: UNIT_LABEL[unit] };
        }
        const canonical = (kind === 'mass' ? MASS[unit] : VOLUME[unit]) * amount;
        if (kind === 'mass') {
            if (pref === 'metric') {
                return canonical >= 1000
                    ? { amount: fmt(canonical/1000, 2), unit: 'kg' }
                    : { amount: fmt(canonical, 0), unit: 'g' };
            }
            const oz = canonical / MASS.oz;
            return oz >= 16
                ? { amount: fmt(canonical / MASS.lb, 2), unit: 'lb' }
                : { amount: fmt(oz, 1), unit: 'oz' };
        }
        if (pref === 'metric') {
            return canonical >= 1000
                ? { amount: fmt(canonical/1000, 2), unit: 'l' }
                : { amount: fmt(canonical, 0), unit: 'ml' };
        }
        const cups = canonical / VOLUME.cup;
        if (cups >= 0.25) return { amount: fmt(cups, 2), unit: 'cup' };
        const tbsp = canonical / VOLUME.tbsp;
        if (tbsp >= 1) return { amount: fmt(tbsp, 1), unit: 'tbsp' };
        return { amount: fmt(canonical / VOLUME.tsp, 1), unit: 'tsp' };
    }

    function syncCookIngredientAmounts() {
        if (!ingredients || !cookMode) return;
        ingredients.querySelectorAll('.ingredient-row').forEach((li, index) => {
            const amount = li.querySelector('.amt');
            const target = cookMode.querySelector('[data-cook-ingredient-index="' + index + '"] .cook-ingredient-amount');
            if (amount && target) target.textContent = amount.textContent;
        });
    }

    function rerender() {
        if (!ingredients || !servingsInput) {
            syncCookIngredientAmounts();
            return;
        }
        const wanted = Math.max(1, parseInt(servingsInput.value, 10) || baseServings);
        if (shoppingServings) shoppingServings.value = wanted;
        const scale = wanted / baseServings;
        ingredients.querySelectorAll('.ingredient-row').forEach(li => {
            const amt = li.querySelector('.amt');
            const raw = li.dataset.amount;
            const unit = li.dataset.unit;
            if (raw === '' || raw === undefined || isNaN(parseFloat(raw))) {
                amt.textContent = (li.dataset.amountRaw || '') + (unit ? ' ' + (UNIT_LABEL[unit] || unit) : '');
                return;
            }
            const value = parseFloat(raw) * scale;
            const out = convert(value, unit, preference);
            amt.textContent = (out.amount + ' ' + (out.unit || '')).trim();
        });
        syncCookIngredientAmounts();
    }

    function formatCookString(template, first, second) {
        return template.replace('%1$d', first).replace('%2$d', second);
    }

    function isCookModeOpen() {
        return cookMode && !cookMode.hidden;
    }

    function loadCookState() {
        if (!cookMode) return;
        try {
            const state = JSON.parse(window.localStorage.getItem(cookStateKey) || '{}');
            activeCookStep = Math.max(0, Math.min(cookStepRows.length - 1, parseInt(state.activeStep, 10) || 0));
            const checkedSteps = Array.isArray(state.checkedSteps) ? state.checkedSteps : [];
            const checkedIngredients = Array.isArray(state.checkedIngredients) ? state.checkedIngredients : [];
            cookFinishDismissed = !!state.finishDismissed;
            cookStepChecks.forEach((check, index) => {
                check.checked = checkedSteps.indexOf(index) >= 0;
            });
            cookIngredientChecks.forEach((check, index) => {
                check.checked = checkedIngredients.indexOf(index) >= 0;
            });
        } catch (e) {
            activeCookStep = 0;
            cookFinishDismissed = false;
        }
    }

    function saveCookState() {
        if (!cookMode) return;
        try {
            window.localStorage.setItem(cookStateKey, JSON.stringify({
                activeStep: activeCookStep,
                checkedSteps: cookStepChecks.reduce((out, check, index) => {
                    if (check.checked) out.push(index);
                    return out;
                }, []),
                checkedIngredients: cookIngredientChecks.reduce((out, check, index) => {
                    if (check.checked) out.push(index);
                    return out;
                }, []),
                finishDismissed: cookFinishDismissed
            }));
        } catch (e) {}
    }

    function updateCookState() {
        if (!cookMode || !cookStepRows.length) return;
        const completed = cookStepChecks.filter(check => check.checked).length;
        if (completed < cookStepRows.length) {
            cookFinishDismissed = false;
        }
        cookStepRows.forEach((row, index) => {
            row.classList.toggle('is-active', index === activeCookStep);
            row.classList.toggle('is-checked', !!(cookStepChecks[index] && cookStepChecks[index].checked));
        });
        cookIngredientRows.forEach((row, index) => {
            row.classList.toggle('is-checked', !!(cookIngredientChecks[index] && cookIngredientChecks[index].checked));
        });
        if (cookActiveCheck) {
            cookActiveCheck.checked = !!(cookStepChecks[activeCookStep] && cookStepChecks[activeCookStep].checked);
        }
        if (cookPrev) cookPrev.disabled = activeCookStep <= 0;
        if (cookNext) {
            const onLastStep = activeCookStep >= cookStepRows.length - 1;
            const activeStepDone = !!(cookStepChecks[activeCookStep] && cookStepChecks[activeCookStep].checked);
            cookNext.disabled = onLastStep && activeStepDone;
            cookNext.textContent = onLastStep ? cookStrings.finish : cookStrings.next;
        }
        if (cookFinish) {
            cookFinish.hidden = completed < cookStepRows.length || cookFinishDismissed;
        }
        if (cookProgress) {
            cookProgress.max = cookStepRows.length;
            cookProgress.value = activeCookStep + 1;
        }
        if (cookStepCount) {
            cookStepCount.textContent = formatCookString(cookStrings.stepOf, activeCookStep + 1, cookStepRows.length);
        }
        if (cookDoneCount) {
            cookDoneCount.textContent = formatCookString(cookStrings.doneCount, completed, cookStepRows.length);
        }
        saveCookState();
    }

    function setCookStep(index, focusStep) {
        if (!cookMode || !cookStepRows.length) return;
        activeCookStep = Math.max(0, Math.min(cookStepRows.length - 1, index));
        const text = cookStepRows[activeCookStep].querySelector('.cook-step-full');
        if (cookActiveStep && text) {
            cookActiveStep.innerHTML = text.innerHTML;
            if (focusStep) cookActiveStep.focus({ preventScroll: true });
        }
        updateCookState();
    }

    function advanceCookStep(focusStep) {
        if (!cookMode || !cookStepRows.length) return;
        if (cookStepChecks[activeCookStep]) {
            cookStepChecks[activeCookStep].checked = true;
        }
        setCookStep(activeCookStep + 1, focusStep);
    }

    async function requestWakeLock() {
        if (!('wakeLock' in navigator) || wakeLock) return;
        try {
            wakeLock = await navigator.wakeLock.request('screen');
            wakeLock.addEventListener('release', () => {
                wakeLock = null;
            });
        } catch (e) {}
    }

    function releaseWakeLock() {
        if (!wakeLock) return;
        wakeLock.release().catch(() => {});
        wakeLock = null;
    }

    function openCookMode() {
        if (!cookMode) return;
        loadCookState();
        syncCookIngredientAmounts();
        cookMode.hidden = false;
        document.body.classList.add('cook-mode-active');
        setCookStep(activeCookStep, true);
        requestWakeLock();
    }

    function closeCookMode() {
        if (!cookMode) return;
        cookMode.hidden = true;
        document.body.classList.remove('cook-mode-active');
        releaseWakeLock();
        if (cookOpen) cookOpen.focus();
    }

    if (servingsInput) servingsInput.addEventListener('input', rerender);
    unitButtons.forEach(btn => btn.addEventListener('click', () => {
        preference = btn.dataset.units;
        unitButtons.forEach(b => b.classList.toggle('active', b === btn));
        rerender();
    }));

    if (cookOpen) cookOpen.addEventListener('click', openCookMode);
    if (cookClose) cookClose.addEventListener('click', closeCookMode);
    if (cookPrev) cookPrev.addEventListener('click', () => setCookStep(activeCookStep - 1, true));
    if (cookNext) cookNext.addEventListener('click', () => advanceCookStep(true));
    if (cookReset) {
        cookReset.addEventListener('click', () => {
            cookStepChecks.forEach(check => { check.checked = false; });
            cookIngredientChecks.forEach(check => { check.checked = false; });
            cookFinishDismissed = false;
            setCookStep(0, true);
        });
    }
    if (cookFinishDismiss) {
        cookFinishDismiss.addEventListener('click', () => {
            cookFinishDismissed = true;
            updateCookState();
        });
    }
    if (cookActiveCheck) {
        cookActiveCheck.addEventListener('change', () => {
            if (cookStepChecks[activeCookStep]) {
                cookStepChecks[activeCookStep].checked = cookActiveCheck.checked;
            }
            updateCookState();
        });
    }
    cookStepChecks.forEach((check, index) => {
        check.addEventListener('change', () => {
            if (index === activeCookStep && cookActiveCheck) {
                cookActiveCheck.checked = check.checked;
            }
            updateCookState();
        });
    });
    cookIngredientChecks.forEach(check => {
        check.addEventListener('change', updateCookState);
    });
    if (cookMode) {
        cookMode.querySelectorAll('[data-cook-step-jump]').forEach(button => {
            button.addEventListener('click', () => {
                setCookStep(parseInt(button.dataset.cookStepJump, 10) || 0, true);
            });
        });
    }
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && isCookModeOpen()) requestWakeLock();
    });

    rerender();
})();
</script>

<?php include __DIR__ . '/_footer.php'; ?>
