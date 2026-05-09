<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- variables here are template-local, not actually global.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
use Cookbook\App;

if ( ! is_user_logged_in() ) {
    wp_die( esc_html__( 'Not allowed.', 'cookbook' ), 403 );
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only query params and flash flags.
$week_param = isset( $_GET['week'] ) ? sanitize_text_field( wp_unslash( $_GET['week'] ) ) : '';
$pending_recipe_id = isset( $_GET['recipe_id'] ) ? absint( $_GET['recipe_id'] ) : 0;
$saved = isset( $_GET['saved'] );
$shopping_status = isset( $_GET['shopping'] ) ? sanitize_text_field( wp_unslash( $_GET['shopping'] ) ) : '';
$shopping_items = isset( $_GET['items'] ) ? absint( $_GET['items'] ) : 0;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$week_start = App::normalize_week_start( $week_param );
$days       = App::week_days( $week_start );
$meal_slots = App::meal_slots();
$plan_id    = App::get_user_week_plan_id( $week_start, false );
$meals      = App::get_week_meals( $plan_id );

$recipes = get_posts( [
    'post_type'      => App::POST_TYPE,
    'post_status'    => [ 'publish', 'draft' ],
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
] );
$recipe_title_counts = [];
foreach ( $recipes as $recipe ) {
    $recipe_title = get_the_title( $recipe );
    if ( ! isset( $recipe_title_counts[ $recipe_title ] ) ) {
        $recipe_title_counts[ $recipe_title ] = 0;
    }
    $recipe_title_counts[ $recipe_title ]++;
}
$recipe_option_values = [];
$recipe_lookup = [];
foreach ( $recipes as $recipe ) {
    $recipe_title = get_the_title( $recipe );
    $recipe_value = $recipe_title_counts[ $recipe_title ] > 1
        ? sprintf(
            /* translators: 1: recipe title, 2: recipe ID */
            __( '%1$s (#%2$d)', 'cookbook' ),
            $recipe_title,
            (int) $recipe->ID
        )
        : $recipe_title;
    $recipe_option_values[ $recipe->ID ] = $recipe_value;
    $recipe_lookup[] = [
        'id'    => (int) $recipe->ID,
        'value' => $recipe_value,
    ];
}

$pending_recipe = $pending_recipe_id ? get_post( $pending_recipe_id ) : null;
if ( ! $pending_recipe || $pending_recipe->post_type !== App::POST_TYPE ) {
    $pending_recipe_id = 0;
    $pending_recipe = null;
}

$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
try {
    $start = new DateTimeImmutable( $week_start, $timezone );
} catch ( Exception $e ) {
    $start = new DateTimeImmutable( App::normalize_week_start(), $timezone );
}
$prev_week = $start->modify( '-7 days' )->format( 'Y-m-d' );
$next_week = $start->modify( '+7 days' )->format( 'Y-m-d' );

$planned = [];
foreach ( $days as $date => $day ) {
    foreach ( $meal_slots as $slot => $slot_label ) {
        $recipe_id = isset( $meals[ $date ][ $slot ] ) ? absint( $meals[ $date ][ $slot ] ) : 0;
        $recipe = $recipe_id ? get_post( $recipe_id ) : null;
        if ( $recipe && $recipe->post_type === App::POST_TYPE ) {
            $planned[] = [
                'date'       => $date,
                'day'        => $day,
                'slot_label' => $slot_label,
                'recipe'     => $recipe,
            ];
        }
    }
}

$page_title = __( 'Week planner', 'cookbook' );
include __DIR__ . '/_header.php';
?>
<a class="badge" href="<?php echo esc_url( home_url( '/cookbook/' ) ); ?>"><?php esc_html_e( '← All recipes', 'cookbook' ); ?></a>

<div class="page-head">
    <div>
        <h1><?php esc_html_e( 'Week planner', 'cookbook' ); ?></h1>
        <p class="subtitle"><?php echo esc_html( sprintf(
            /* translators: %s: formatted date */
            __( 'Planning week of %s.', 'cookbook' ),
            wp_date( get_option( 'date_format' ), $start->getTimestamp() )
        ) ); ?></p>
    </div>
    <div class="page-actions">
        <a class="btn secondary" href="<?php echo esc_url( home_url( '/cookbook/shopping-list' ) ); ?>"><?php esc_html_e( 'Shopping list', 'cookbook' ); ?></a>
        <a class="btn fresh" href="<?php echo esc_url( home_url( '/cookbook/new' ) ); ?>"><?php esc_html_e( '+ New recipe', 'cookbook' ); ?></a>
    </div>
</div>

<?php if ( $saved ) : ?>
    <div class="notice success"><?php esc_html_e( 'Week planner saved.', 'cookbook' ); ?></div>
<?php endif; ?>
<?php if ( $shopping_status === 'added' ) : ?>
    <div class="notice success">
        <?php
        echo esc_html( sprintf(
            /* translators: %d: shopping-list items */
            _n( '%d planned ingredient added to your shopping list.', '%d planned ingredients added to your shopping list.', $shopping_items, 'cookbook' ),
            $shopping_items
        ) );
        ?>
    </div>
<?php endif; ?>
<?php if ( $pending_recipe ) : ?>
    <div class="notice">
        <?php
        echo esc_html( sprintf(
            /* translators: %s: recipe title */
            __( 'Select a slot for %s, then save the week.', 'cookbook' ),
            get_the_title( $pending_recipe )
        ) );
        ?>
    </div>
<?php endif; ?>

<nav class="planner-nav" aria-label="<?php esc_attr_e( 'Planner week navigation', 'cookbook' ); ?>">
    <a class="btn secondary" href="<?php echo esc_url( add_query_arg( 'week', $prev_week, home_url( '/cookbook/planner' ) ) ); ?>"><?php esc_html_e( 'Previous week', 'cookbook' ); ?></a>
    <a class="badge" href="<?php echo esc_url( home_url( '/cookbook/planner' ) ); ?>"><?php esc_html_e( 'This week', 'cookbook' ); ?></a>
    <a class="btn secondary" href="<?php echo esc_url( add_query_arg( 'week', $next_week, home_url( '/cookbook/planner' ) ) ); ?>"><?php esc_html_e( 'Next week', 'cookbook' ); ?></a>
</nav>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="planner-form" data-pending-recipe="<?php echo (int) $pending_recipe_id; ?>">
    <?php wp_nonce_field( 'cookbook_save_planner' ); ?>
    <input type="hidden" name="action" value="cookbook_save_planner">
    <input type="hidden" name="plan_id" value="<?php echo (int) $plan_id; ?>">
    <input type="hidden" name="week_start" value="<?php echo esc_attr( $week_start ); ?>">

    <div class="planner-grid">
        <?php foreach ( $days as $date => $day ) : ?>
            <section class="planner-day">
                <h3>
                    <?php echo esc_html( $day['short'] ); ?>
                    <span><?php echo esc_html( $day['label'] ); ?></span>
                </h3>
                <?php foreach ( $meal_slots as $slot => $slot_label ) :
                    $selected = isset( $meals[ $date ][ $slot ] ) ? absint( $meals[ $date ][ $slot ] ) : 0;
                    $selected_value = $selected && isset( $recipe_option_values[ $selected ] ) ? $recipe_option_values[ $selected ] : '';
                    $field_id = 'meal-' . $date . '-' . $slot;
                    $hidden_id = 'meal-id-' . $date . '-' . $slot;
                    ?>
                    <div class="planner-slot">
                        <label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $slot_label ); ?></label>
                        <input
                            id="<?php echo esc_attr( $field_id ); ?>"
                            type="text"
                            list="planner-recipe-options"
                            name="meal_labels[<?php echo esc_attr( $date ); ?>][<?php echo esc_attr( $slot ); ?>]"
                            value="<?php echo esc_attr( $selected_value ); ?>"
                            placeholder="<?php esc_attr_e( 'Search recipes', 'cookbook' ); ?>"
                            data-meal-input
                            data-hidden-id="<?php echo esc_attr( $hidden_id ); ?>"
                            data-slot="<?php echo esc_attr( $slot ); ?>"
                            autocomplete="off"
                        >
                        <input id="<?php echo esc_attr( $hidden_id ); ?>" type="hidden" name="meals[<?php echo esc_attr( $date ); ?>][<?php echo esc_attr( $slot ); ?>]" value="<?php echo (int) $selected; ?>">
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </div>
    <datalist id="planner-recipe-options">
        <?php foreach ( $recipe_lookup as $recipe_option ) : ?>
            <option value="<?php echo esc_attr( $recipe_option['value'] ); ?>"></option>
        <?php endforeach; ?>
    </datalist>

    <div class="toolbar">
        <button class="btn fresh" type="submit"><?php esc_html_e( 'Save week', 'cookbook' ); ?></button>
    </div>
</form>

<?php if ( $planned ) : ?>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="toolbar">
        <?php wp_nonce_field( 'cookbook_add_planner_to_shopping_list' ); ?>
        <input type="hidden" name="action" value="cookbook_add_planner_to_shopping_list">
        <input type="hidden" name="week_start" value="<?php echo esc_attr( $week_start ); ?>">
        <button class="btn" type="submit"><?php esc_html_e( 'Add planned ingredients to shopping list', 'cookbook' ); ?></button>
        <a class="btn secondary" href="<?php echo esc_url( home_url( '/cookbook/shopping-list' ) ); ?>"><?php esc_html_e( 'Open shopping list', 'cookbook' ); ?></a>
    </form>
<?php endif; ?>

<?php if ( $planned ) : ?>
    <h2><?php esc_html_e( 'Planned recipes', 'cookbook' ); ?></h2>
    <div class="planned-strip">
        <?php foreach ( $planned as $entry ) :
            $recipe = $entry['recipe'];
            ?>
            <a class="planned-card" href="<?php echo esc_url( home_url( '/cookbook/recipe/' . $recipe->ID ) ); ?>">
                <?php if ( has_post_thumbnail( $recipe->ID ) ) : ?>
                    <?php echo get_the_post_thumbnail( $recipe->ID, 'thumbnail', [ 'alt' => '' ] ); ?>
                <?php else : ?>
                    <span class="planned-thumb"><?php echo esc_html( mb_strtoupper( mb_substr( get_the_title( $recipe ), 0, 1 ) ) ); ?></span>
                <?php endif; ?>
                <span>
                    <strong><?php echo esc_html( get_the_title( $recipe ) ); ?></strong>
                    <span><?php echo esc_html( $entry['day']['short'] . ' - ' . $entry['slot_label'] ); ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
(function () {
    const form = document.getElementById('planner-form');
    if (!form) return;
    const recipes = <?php echo wp_json_encode( $recipe_lookup ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Encoded as JSON for local planner autocomplete. ?>;
    const valueToId = new Map(recipes.map(recipe => [recipe.value, String(recipe.id)]));
    const idToValue = new Map(recipes.map(recipe => [String(recipe.id), recipe.value]));

    function syncInput(input) {
        const hidden = document.getElementById(input.dataset.hiddenId);
        if (!hidden) return;
        hidden.value = valueToId.get(input.value.trim()) || '0';
    }

    const inputs = Array.from(form.querySelectorAll('[data-meal-input]'));
    inputs.forEach(input => {
        input.addEventListener('input', () => syncInput(input));
        input.addEventListener('change', () => syncInput(input));
    });
    form.addEventListener('submit', () => inputs.forEach(syncInput));

    const pending = parseInt(form.dataset.pendingRecipe, 10) || 0;
    if (!pending) return;
    const pendingValue = idToValue.get(String(pending));
    if (!pendingValue) return;
    const target = inputs.find(input => input.dataset.slot === 'dinner' && !input.value.trim()) || inputs.find(input => !input.value.trim()) || inputs[0];
    if (target) {
        target.value = pendingValue;
        syncInput(target);
    }
})();
</script>

<?php include __DIR__ . '/_footer.php'; ?>
