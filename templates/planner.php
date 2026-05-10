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
$copied = isset( $_GET['copied'] );
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
$current_week_start = App::normalize_week_start();
$is_current_week = $week_start === $current_week_start;

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
        <?php if ( ! $is_current_week && $plan_id ) : ?>
            <form
                method="post"
                action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                onsubmit="return confirm('<?php echo esc_js( __( 'Copy this week to the current week? This replaces the current week planner.', 'cookbook' ) ); ?>')"
            >
                <?php wp_nonce_field( 'cookbook_copy_planner_to_current_week' ); ?>
                <input type="hidden" name="action" value="cookbook_copy_planner_to_current_week">
                <input type="hidden" name="source_week_start" value="<?php echo esc_attr( $week_start ); ?>">
                <button class="btn secondary" type="submit"><?php esc_html_e( 'Copy to current week', 'cookbook' ); ?></button>
            </form>
        <?php endif; ?>
        <button class="btn fresh" type="submit" form="planner-form"><?php esc_html_e( 'Save week', 'cookbook' ); ?></button>
    </div>
</div>

<?php if ( $saved ) : ?>
    <div class="notice success"><?php esc_html_e( 'Week planner saved.', 'cookbook' ); ?></div>
<?php endif; ?>
<?php if ( $copied ) : ?>
    <div class="notice success"><?php esc_html_e( 'Week copied to the current week.', 'cookbook' ); ?></div>
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

<form
    method="post"
    action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
    id="planner-form"
    data-pending-recipe="<?php echo (int) $pending_recipe_id; ?>"
    data-stash-key="cookbook.plannerStash"
>
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
                        <div class="planner-slot-label">
                            <label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $slot_label ); ?></label>
                            <button
                                class="planner-action"
                                type="button"
                                data-planner-here
                                data-target-id="<?php echo esc_attr( $field_id ); ?>"
                                data-day-label="<?php echo esc_attr( $day['short'] ); ?>"
                                data-slot-label="<?php echo esc_attr( $slot_label ); ?>"
                                hidden
                            ><?php esc_html_e( 'Here', 'cookbook' ); ?></button>
                            <button
                                class="planner-action"
                                type="button"
                                data-planner-lift
                                data-target-id="<?php echo esc_attr( $field_id ); ?>"
                                aria-label="<?php echo esc_attr( sprintf(
                                    /* translators: 1: day label, 2: meal slot label */
                                    __( 'Lift recipe from %1$s %2$s', 'cookbook' ),
                                    $day['short'],
                                    $slot_label
                                ) ); ?>"
                                hidden
                            ><?php esc_html_e( 'Lift', 'cookbook' ); ?></button>
                        </div>
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
        <section class="planner-day planner-stash" data-planner-stash hidden>
            <h3>
                <?php esc_html_e( 'Stash', 'cookbook' ); ?>
                <button class="planner-action" type="button" data-planner-clear-stash><?php esc_html_e( 'Clear', 'cookbook' ); ?></button>
            </h3>
            <div class="planner-stash-items" data-planner-stash-items></div>
        </section>
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
    const stashStorageKey = form.dataset.stashKey || '';
    const stashPanel = form.querySelector('[data-planner-stash]');
    const stashItems = form.querySelector('[data-planner-stash-items]');
    const clearStash = form.querySelector('[data-planner-clear-stash]');
    const stash = [];
    let selectedStashKey = '';
    let stashCounter = 0;

    function hiddenFor(input) {
        const hidden = document.getElementById(input.dataset.hiddenId);
        return hidden || null;
    }

    function syncInput(input) {
        const hidden = hiddenFor(input);
        if (!hidden) return;
        hidden.value = valueToId.get(input.value.trim()) || '0';
    }

    function slotItem(input) {
        const hidden = hiddenFor(input);
        if (!hidden || hidden.value === '0' || !input.value.trim()) return null;
        return {
            id: hidden.value,
            value: input.value.trim()
        };
    }

    function itemFromId(id) {
        const recipeId = String(id || '0');
        const value = idToValue.get(recipeId);
        return value ? { id: recipeId, value } : null;
    }

    function selectedStashItem() {
        return stash.find(item => item.key === selectedStashKey) || null;
    }

    function addToStash(item, select = false) {
        if (!item || !item.id || item.id === '0' || !item.value) return;
        const stashItem = {
            key: String(++stashCounter),
            id: String(item.id),
            value: item.value
        };
        stash.push(stashItem);
        if (select || !selectedStashKey) {
            selectedStashKey = stashItem.key;
        }
        renderStash();
        updateSlotActions();
    }

    function removeFromStash(key) {
        const index = stash.findIndex(item => item.key === key);
        if (index === -1) return;
        stash.splice(index, 1);
        if (selectedStashKey === key) {
            selectedStashKey = stash.length ? stash[Math.min(index, stash.length - 1)].key : '';
        }
    }

    function deleteFromStash(key) {
        removeFromStash(key);
        renderStash();
        updateSlotActions();
    }

    function clearWholeStash() {
        stash.splice(0, stash.length);
        selectedStashKey = '';
        renderStash();
        updateSlotActions();
    }

    function setSlot(input, item) {
        const hidden = hiddenFor(input);
        if (!hidden) return;
        input.value = item ? item.value : '';
        hidden.value = item ? String(item.id) : '0';
    }

    function plannerStorage() {
        try {
            return window.localStorage;
        } catch (error) {
            return null;
        }
    }

    function saveStash() {
        const storage = plannerStorage();
        if (!stashStorageKey || !storage) return;
        try {
            if (!stash.length) {
                storage.removeItem(stashStorageKey);
                return;
            }
            storage.setItem(stashStorageKey, JSON.stringify({
                counter: stashCounter,
                selected: selectedStashKey,
                items: stash
            }));
        } catch (error) {
            // Browsers can block storage; the planner still works for this page view.
        }
    }

    function loadStash() {
        const storage = plannerStorage();
        if (!stashStorageKey || !storage) return;
        try {
            const raw = storage.getItem(stashStorageKey);
            if (!raw) return;
            const saved = JSON.parse(raw);
            const savedItems = Array.isArray(saved.items) ? saved.items : [];
            savedItems.forEach(item => {
                const recipe = itemFromId(item.id);
                if (!recipe) return;
                const key = item.key ? String(item.key) : String(++stashCounter);
                stash.push({
                    key,
                    id: recipe.id,
                    value: recipe.value
                });
                stashCounter = Math.max(stashCounter, parseInt(key, 10) || 0);
            });
            selectedStashKey = stash.some(item => item.key === String(saved.selected)) ? String(saved.selected) : (stash[0] ? stash[0].key : '');
            stashCounter = Math.max(stashCounter, parseInt(saved.counter, 10) || 0);
            renderStash();
        } catch (error) {
            storage.removeItem(stashStorageKey);
        }
    }

    function updateSlotActions() {
        const selected = selectedStashItem();
        inputs.forEach(input => {
            const here = form.querySelector('[data-planner-here][data-target-id="' + input.id + '"]');
            const lift = form.querySelector('[data-planner-lift][data-target-id="' + input.id + '"]');
            const item = slotItem(input);
            if (here) {
                here.hidden = !selected;
                if (selected) {
                    here.setAttribute('aria-label', '<?php echo esc_js( __( 'Place selected recipe here', 'cookbook' ) ); ?>');
                    here.title = selected.value;
                }
            }
            if (lift) {
                lift.hidden = !item;
            }
        });
    }

    function renderStash() {
        if (!stashPanel || !stashItems) return;
        stashPanel.hidden = stash.length === 0;
        stashItems.textContent = '';
        stash.forEach(item => {
            const entry = document.createElement('span');
            entry.className = 'planner-stash-item' + (item.key === selectedStashKey ? ' is-selected' : '');

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'planner-stash-select';
            button.textContent = item.value;
            button.setAttribute('aria-pressed', item.key === selectedStashKey ? 'true' : 'false');
            button.addEventListener('click', () => {
                selectedStashKey = item.key;
                renderStash();
                updateSlotActions();
            });
            entry.appendChild(button);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'planner-stash-remove';
            remove.textContent = '×';
            remove.setAttribute('aria-label', '<?php echo esc_js( __( 'Remove from stash', 'cookbook' ) ); ?>');
            remove.addEventListener('click', () => deleteFromStash(item.key));
            entry.appendChild(remove);

            stashItems.appendChild(entry);
        });
        saveStash();
    }

    const inputs = Array.from(form.querySelectorAll('[data-meal-input]'));
    inputs.forEach(input => {
        input.addEventListener('input', () => {
            syncInput(input);
            updateSlotActions();
        });
        input.addEventListener('change', () => {
            syncInput(input);
            updateSlotActions();
        });
    });
    form.addEventListener('submit', () => inputs.forEach(syncInput));

    if (clearStash) {
        clearStash.addEventListener('click', clearWholeStash);
    }

    form.querySelectorAll('[data-planner-lift]').forEach(button => {
        button.addEventListener('click', () => {
            const target = document.getElementById(button.dataset.targetId);
            if (!target) return;
            const item = slotItem(target);
            if (!item) return;
            setSlot(target, null);
            addToStash(item, true);
            target.focus();
        });
    });

    form.querySelectorAll('[data-planner-here]').forEach(button => {
        button.addEventListener('click', () => {
            const target = document.getElementById(button.dataset.targetId);
            const selected = selectedStashItem();
            if (!target || !selected) return;
            const displaced = slotItem(target);
            setSlot(target, selected);
            removeFromStash(selected.key);
            if (displaced) {
                addToStash(displaced, true);
            } else {
                renderStash();
                updateSlotActions();
            }
            target.focus();
        });
    });

    loadStash();

    const pending = parseInt(form.dataset.pendingRecipe, 10) || 0;
    const pendingItem = pending ? itemFromId(pending) : null;
    if (pendingItem && !stash.some(item => item.id === pendingItem.id)) {
        addToStash(pendingItem, true);
    }
    updateSlotActions();
})();
</script>

<?php include __DIR__ . '/_footer.php'; ?>
