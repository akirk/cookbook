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
$copy_source_param = isset( $_GET['copy-form'] ) ? sanitize_text_field( wp_unslash( $_GET['copy-form'] ) ) : '';
$shopping_status = isset( $_GET['shopping'] ) ? sanitize_text_field( wp_unslash( $_GET['shopping'] ) ) : '';
$shopping_items = isset( $_GET['items'] ) ? absint( $_GET['items'] ) : 0;
$shopping_household = isset( $_GET['household'] ) ? absint( $_GET['household'] ) : 0;
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

$copy_source_slots = [];
$copy_inserted_slots = [];
$copy_source_week_start = $copy_source_param !== '' ? App::normalize_week_start( $copy_source_param ) : '';
if ( $copy_source_week_start && $copy_source_week_start !== $week_start ) {
    $source_plan_id = App::get_user_week_plan_id( $copy_source_week_start, false );
    $source_meals = App::get_week_meals( $source_plan_id );
    if ( $source_meals ) {
        $source_dates = array_keys( App::week_days( $copy_source_week_start ) );
        $target_dates = array_keys( $days );
        foreach ( $target_dates as $day_index => $target_date ) {
            $source_date = $source_dates[ $day_index ] ?? '';
            if ( $source_date === '' ) {
                continue;
            }
            foreach ( array_keys( $meal_slots ) as $slot ) {
                $recipe_id = isset( $source_meals[ $source_date ][ $slot ] ) ? absint( $source_meals[ $source_date ][ $slot ] ) : 0;
                if ( $recipe_id && isset( $recipe_option_values[ $recipe_id ] ) ) {
                    $field_id = 'meal-' . $target_date . '-' . $slot;
                    $copy_source_slots[ $field_id ] = $recipe_id;
                    $target_recipe_id = isset( $meals[ $target_date ][ $slot ] ) ? absint( $meals[ $target_date ][ $slot ] ) : 0;
                    if ( ! $target_recipe_id ) {
                        $copy_inserted_slots[ $field_id ] = true;
                    }
                }
            }
        }
    }
}

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
            <a class="btn secondary" href="<?php echo esc_url( add_query_arg( [
                'week'      => $current_week_start,
                'copy-form' => $week_start,
            ], home_url( '/cookbook/planner' ) ) ); ?>"><?php esc_html_e( 'Copy to current week', 'cookbook' ); ?></a>
        <?php endif; ?>
        <?php if ( $is_current_week && $plan_id ) : ?>
            <a class="btn secondary" href="<?php echo esc_url( add_query_arg( [
                'week'      => $next_week,
                'copy-form' => $week_start,
            ], home_url( '/cookbook/planner' ) ) ); ?>"><?php esc_html_e( 'Copy to next week', 'cookbook' ); ?></a>
        <?php endif; ?>
        <button class="btn fresh" type="submit" form="planner-form"><?php esc_html_e( 'Save week', 'cookbook' ); ?></button>
    </div>
</div>

<?php if ( $saved ) : ?>
    <div class="notice success"><?php esc_html_e( 'Week planner saved.', 'cookbook' ); ?></div>
<?php endif; ?>
<?php if ( $copy_source_slots ) : ?>
    <div class="notice"><?php esc_html_e( 'Review the copied week, then save the week to keep it.', 'cookbook' ); ?></div>
<?php endif; ?>
<?php if ( $shopping_status === 'added' ) : ?>
    <div class="notice success">
        <?php
        $shopping_message = sprintf(
            /* translators: %d: shopping-list items */
            _n( '%d planned ingredient added to your shopping list.', '%d planned ingredients added to your shopping list.', $shopping_items, 'cookbook' ),
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
                    $copied_recipe_id = isset( $copy_source_slots[ $field_id ] ) ? absint( $copy_source_slots[ $field_id ] ) : 0;
                    $has_copied_change = $copied_recipe_id && $copied_recipe_id !== $selected;
                    $is_copy_inserted = isset( $copy_inserted_slots[ $field_id ] );
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
                            name="meal_labels[<?php echo esc_attr( $date ); ?>][<?php echo esc_attr( $slot ); ?>]"
                            value="<?php echo esc_attr( $selected_value ); ?>"
                            placeholder="<?php esc_attr_e( 'Search recipes', 'cookbook' ); ?>"
                            data-meal-input
                            data-hidden-id="<?php echo esc_attr( $hidden_id ); ?>"
                            data-slot="<?php echo esc_attr( $slot ); ?>"
                            <?php if ( $is_copy_inserted ) : ?>
                                data-copy-highlight
                            <?php endif; ?>
                            autocomplete="off"
                        >
                        <input id="<?php echo esc_attr( $hidden_id ); ?>" type="hidden" name="meals[<?php echo esc_attr( $date ); ?>][<?php echo esc_attr( $slot ); ?>]" value="<?php echo (int) $selected; ?>">
                        <?php if ( $has_copied_change && $selected && $selected_value ) : ?>
                            <div
                                class="planner-previous"
                                data-copy-previous
                                data-recipe-id="<?php echo (int) $selected; ?>"
                                data-recipe-value="<?php echo esc_attr( $selected_value ); ?>"
                            >
                                <span><?php echo esc_html( $selected_value ); ?></span>
                                <button class="planner-action" type="button" data-copy-previous-put-back data-target-id="<?php echo esc_attr( $field_id ); ?>"><?php esc_html_e( 'Put back', 'cookbook' ); ?></button>
                            </div>
                        <?php endif; ?>
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
    const copySourceSlots = <?php echo wp_json_encode( $copy_source_slots ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Encoded as JSON for local planner copy preview. ?>;
    const copyInsertedSlots = <?php echo wp_json_encode( $copy_inserted_slots ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Encoded as JSON for local planner copy preview. ?>;
    const valueToId = new Map(recipes.map(recipe => [recipe.value, String(recipe.id)]));
    const idToValue = new Map(recipes.map(recipe => [String(recipe.id), recipe.value]));
    const stashStorageKey = 'cookbook.plannerStash';
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

    const autocompleteLimit = 8;
    let activeAutocompleteInput = null;

    function autocompletePanel(input) {
        return document.getElementById(input.id + '-autocomplete');
    }

    function normalizeAutocompleteText(value) {
        return String(value || '').toLocaleLowerCase();
    }

    function matchingRecipes(value) {
        const needle = normalizeAutocompleteText(value.trim());
        if (!needle) {
            return recipes.slice(0, autocompleteLimit);
        }

        const starts = [];
        const contains = [];
        recipes.forEach(recipe => {
            const haystack = normalizeAutocompleteText(recipe.value);
            if (haystack.startsWith(needle)) {
                starts.push(recipe);
            } else if (haystack.includes(needle)) {
                contains.push(recipe);
            }
        });
        return starts.concat(contains).slice(0, autocompleteLimit);
    }

    function closeAutocomplete(input) {
        const panel = autocompletePanel(input);
        if (panel) {
            panel.hidden = true;
            panel.textContent = '';
        }
        input.dataset.autocompleteIndex = '-1';
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        if (activeAutocompleteInput === input) {
            activeAutocompleteInput = null;
        }
    }

    function autocompleteOptions(input) {
        const panel = autocompletePanel(input);
        return panel && !panel.hidden ? Array.from(panel.querySelectorAll('[data-autocomplete-option]')) : [];
    }

    function setAutocompleteActive(input, index) {
        const options = autocompleteOptions(input);
        if (!options.length) return;
        const next = (index + options.length) % options.length;
        options.forEach((option, optionIndex) => {
            option.setAttribute('aria-selected', optionIndex === next ? 'true' : 'false');
        });
        input.dataset.autocompleteIndex = String(next);
        input.setAttribute('aria-activedescendant', options[next].id);
    }

    function selectAutocompleteRecipe(input, recipe) {
        setSlot(input, {
            id: recipe.id,
            value: recipe.value
        });
        closeAutocomplete(input);
        updateSlotActions();
    }

    function renderAutocomplete(input) {
        const panel = autocompletePanel(input);
        if (!panel) return;

        if (activeAutocompleteInput && activeAutocompleteInput !== input) {
            closeAutocomplete(activeAutocompleteInput);
        }

        const matches = matchingRecipes(input.value);
        if (!matches.length) {
            closeAutocomplete(input);
            return;
        }

        panel.textContent = '';
        matches.forEach((recipe, index) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.id = input.id + '-autocomplete-option-' + index;
            option.className = 'planner-autocomplete-option';
            option.textContent = recipe.value;
            option.dataset.autocompleteOption = '1';
            option.dataset.recipeId = String(recipe.id);
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');
            option.addEventListener('pointerdown', event => {
                event.preventDefault();
                selectAutocompleteRecipe(input, recipe);
                input.focus();
            });
            option.addEventListener('click', event => {
                event.preventDefault();
                selectAutocompleteRecipe(input, recipe);
                input.focus();
            });
            option.addEventListener('mouseenter', () => setAutocompleteActive(input, index));
            panel.appendChild(option);
        });

        panel.hidden = false;
        activeAutocompleteInput = input;
        input.dataset.autocompleteIndex = '-1';
        input.setAttribute('aria-expanded', 'true');
        input.removeAttribute('aria-activedescendant');
    }

    function setupAutocomplete(input) {
        const panel = document.createElement('div');
        panel.id = input.id + '-autocomplete';
        panel.className = 'planner-autocomplete';
        panel.hidden = true;
        panel.setAttribute('role', 'listbox');
        input.insertAdjacentElement('afterend', panel);
        input.dataset.autocompleteIndex = '-1';
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', panel.id);
        input.setAttribute('aria-expanded', 'false');

        input.addEventListener('focus', () => renderAutocomplete(input));
        input.addEventListener('blur', () => {
            window.setTimeout(() => closeAutocomplete(input), 120);
        });
        input.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                closeAutocomplete(input);
                return;
            }
            if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp' && event.key !== 'Enter') {
                return;
            }

            const panel = autocompletePanel(input);
            if (!panel || panel.hidden) {
                renderAutocomplete(input);
            }
            const options = autocompleteOptions(input);
            if (!options.length) return;

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                const current = parseInt(input.dataset.autocompleteIndex, 10);
                const fallback = event.key === 'ArrowDown' ? -1 : 0;
                const base = Number.isNaN(current) ? fallback : current;
                setAutocompleteActive(input, base + (event.key === 'ArrowDown' ? 1 : -1));
                return;
            }

            const activeIndex = parseInt(input.dataset.autocompleteIndex, 10);
            if (!Number.isNaN(activeIndex) && activeIndex >= 0 && options[activeIndex]) {
                const recipe = itemFromId(options[activeIndex].dataset.recipeId);
                if (recipe) {
                    event.preventDefault();
                    selectAutocompleteRecipe(input, recipe);
                }
            }
        });
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

    function applyCopiedWeek() {
        if (!copySourceSlots || Object.keys(copySourceSlots).length === 0) return;
        inputs.forEach(input => {
            if (!Object.prototype.hasOwnProperty.call(copySourceSlots, input.id)) return;
            const incoming = itemFromId(copySourceSlots[input.id]);
            setSlot(input, incoming);
            if (Object.prototype.hasOwnProperty.call(copyInsertedSlots, input.id)) {
                input.dataset.copyHighlight = '1';
            } else {
                delete input.dataset.copyHighlight;
            }
        });
        renderStash();
        updateSlotActions();
    }

    const inputs = Array.from(form.querySelectorAll('[data-meal-input]'));
    inputs.forEach(input => {
        setupAutocomplete(input);
        input.addEventListener('input', () => {
            syncInput(input);
            renderAutocomplete(input);
            updateSlotActions();
        });
        input.addEventListener('change', () => {
            syncInput(input);
            updateSlotActions();
        });
    });
    document.addEventListener('pointerdown', event => {
        if (!activeAutocompleteInput) return;
        const panel = autocompletePanel(activeAutocompleteInput);
        if (event.target === activeAutocompleteInput || (panel && panel.contains(event.target))) {
            return;
        }
        closeAutocomplete(activeAutocompleteInput);
    });
    form.addEventListener('submit', () => inputs.forEach(syncInput));

    if (clearStash) {
        clearStash.addEventListener('click', clearWholeStash);
    }

    form.querySelectorAll('[data-copy-previous-put-back]').forEach(button => {
        button.addEventListener('click', () => {
            const previous = button.closest('[data-copy-previous]');
            const target = document.getElementById(button.dataset.targetId);
            if (!previous || !target) return;
            const displaced = slotItem(target);
            setSlot(target, {
                id: previous.dataset.recipeId,
                value: previous.dataset.recipeValue
            });
            if (displaced && displaced.id !== previous.dataset.recipeId) {
                addToStash(displaced, true);
            }
            previous.hidden = true;
            updateSlotActions();
            target.focus();
        });
    });

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
    applyCopiedWeek();

    const pending = parseInt(form.dataset.pendingRecipe, 10) || 0;
    const pendingItem = pending ? itemFromId(pending) : null;
    if (pendingItem && !stash.some(item => item.id === pendingItem.id)) {
        addToStash(pendingItem, true);
    }
    updateSlotActions();
})();
</script>

<?php include __DIR__ . '/_footer.php'; ?>
