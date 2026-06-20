<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- variables here are template-local, not actually global.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
use Cookbook\App;

if ( ! is_user_logged_in() ) {
    wp_die( esc_html__( 'Not allowed.', 'cookbook' ), 403 );
}

$list_id = App::get_current_user_shopping_list_id( false );
$items   = $list_id ? App::get_shopping_items( $list_id ) : [];
$household_reminders = $list_id ? App::get_shopping_household_reminders( $list_id ) : [];
$checked_count = 0;
foreach ( $items as $item ) {
    if ( ! empty( $item['checked'] ) ) {
        $checked_count++;
    }
}
$remaining_count = max( 0, count( $items ) - $checked_count );

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only flash flags.
$saved = isset( $_GET['saved'] );
$mode = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : 'shop';
// phpcs:enable WordPress.Security.NonceVerification.Recommended
$is_shop_mode = $mode !== 'edit';
$multiple_recipes_label = __( 'Multiple recipes', 'cookbook' );
$multiple_recipes_labels = array_unique( [ 'Multiple recipes', $multiple_recipes_label ] );
$is_multiple_recipes_label = function( string $title ) use ( $multiple_recipes_labels ): bool {
    $title = trim( $title );
    if ( $title === '' ) {
        return false;
    }
    return in_array( $title, $multiple_recipes_labels, true );
};
$shopping_item_source_titles = function( array $item ) use ( $is_multiple_recipes_label ): array {
    $titles = [];
    foreach ( $item['source_recipes'] ?? [] as $source ) {
        if ( is_array( $source ) && ! empty( $source['title'] ) && ! $is_multiple_recipes_label( (string) $source['title'] ) ) {
            $titles[] = (string) $source['title'];
        }
    }
    if ( ! $titles && ! empty( $item['source_recipe_title'] ) && ! $is_multiple_recipes_label( (string) $item['source_recipe_title'] ) ) {
        $titles[] = (string) $item['source_recipe_title'];
    }
    return array_values( array_unique( $titles ) );
};
$shopping_item_detail = function( array $item ): string {
    $quantity = trim( implode( ' ', array_filter( [ $item['amount'] ?? '', $item['unit'] ?? '' ] ) ) );
    return trim( $quantity . ( $quantity && ! empty( $item['notes'] ) ? ' - ' : '' ) . ( $item['notes'] ?? '' ) );
};

$page_title = __( 'Shopping list', 'cookbook' );
$has_shopping_list_content = ! empty( $items ) || ! empty( $household_reminders );
$clear_list_confirm = __( 'Clear the whole shopping list?', 'cookbook' );
include __DIR__ . '/_header.php';
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="shopping-list-form">
    <?php wp_nonce_field( 'cookbook_update_shopping_list' ); ?>
    <input type="hidden" name="action" value="cookbook_update_shopping_list">
    <input type="hidden" name="list_id" value="<?php echo (int) $list_id; ?>">
    <input type="hidden" name="return_mode" value="<?php echo $is_shop_mode ? 'shop' : 'edit'; ?>">

    <?php
    ob_start();
    ?>
    <?php if ( $is_shop_mode ) : ?>
        <a class="btn secondary" href="<?php echo esc_url( add_query_arg( 'mode', 'edit', home_url( '/cookbook/shopping-list' ) ) ); ?>"><?php esc_html_e( 'Edit list', 'cookbook' ); ?></a>
    <?php else : ?>
        <a class="btn fresh" href="<?php echo esc_url( home_url( '/cookbook/shopping-list' ) ); ?>"><?php esc_html_e( 'Shop mode', 'cookbook' ); ?></a>
        <button class="btn fresh" type="submit" name="list_command" value="save"><?php esc_html_e( 'Save list', 'cookbook' ); ?></button>
        <button class="btn secondary" type="submit" name="list_command" value="clear_checked"><?php esc_html_e( 'Clear checked', 'cookbook' ); ?></button>
    <?php endif; ?>
    <?php
    $shopping_actions = ob_get_clean();
    cookbook_page_head( __( 'Shopping list', 'cookbook' ), [
        'current_section' => 'shopping',
        'subtitle'        => sprintf(
            /* translators: 1: total items, 2: checked items */
            __( '%1$d items, %2$d checked off.', 'cookbook' ),
            count( $items ),
            $checked_count
        ),
        'actions_html'    => $shopping_actions,
    ] );
    ?>

    <?php if ( $saved ) : ?>
        <div class="notice success"><?php esc_html_e( 'Shopping list saved.', 'cookbook' ); ?></div>
    <?php endif; ?>

    <?php if ( ! $items && ! $household_reminders ) : ?>
        <div class="notice"><?php esc_html_e( 'Your shopping list is empty. Add a recipe from its recipe page, build it from the week planner, or add items manually below.', 'cookbook' ); ?></div>
        <div class="shop-add soft-panel">
            <input type="text" name="new_items[0][name]" placeholder="<?php esc_attr_e( 'Add item', 'cookbook' ); ?>">
            <button class="btn fresh" type="submit" name="list_command" value="save"><?php esc_html_e( 'Add', 'cookbook' ); ?></button>
        </div>
    <?php elseif ( $is_shop_mode ) : ?>
        <?php if ( $items ) : ?>
            <div class="shop-bar">
                <div class="shop-bar-main">
                    <strong>
                        <span id="shop-remaining-count"><?php echo (int) $remaining_count; ?></span>
                        <?php esc_html_e( 'remaining', 'cookbook' ); ?>
                    </strong>
                    <?php if ( $household_reminders ) : ?>
                        <span class="shop-household-summary">
                            <?php
                            echo esc_html( sprintf(
                                /* translators: %s: comma-separated household shopping items */
                                __( 'At home: %s', 'cookbook' ),
                                implode( ', ', array_map( function( array $reminder ): string {
                                    return (string) ( $reminder['name'] ?? '' );
                                }, $household_reminders ) )
                            ) );
                            ?>
                        </span>
                    <?php endif; ?>
                </div>
                <button class="btn secondary" type="button" id="undo-shop-check" hidden><?php esc_html_e( 'Undo', 'cookbook' ); ?></button>
                <button class="btn fresh" type="submit" name="list_command" value="save"><?php esc_html_e( 'Save', 'cookbook' ); ?></button>
                <button class="btn secondary" type="submit" name="list_command" value="clear_checked"><?php esc_html_e( 'Clear checked', 'cookbook' ); ?></button>
            </div>

            <ul class="shop-list" id="shop-list">
            <?php
            $shop_items = $items;
            foreach ( $shop_items as $item ) :
                $item_id = $item['id'];
                $is_checked = ! empty( $item['checked'] );
                $detail = $shopping_item_detail( $item );
                $source_titles = $shopping_item_source_titles( $item );
                ?>
                <li class="shop-item<?php echo $is_checked ? ' is-checked' : ''; ?>">
                    <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][id]" value="<?php echo esc_attr( $item_id ); ?>">
                    <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][source_recipe_id]" value="<?php echo (int) $item['source_recipe_id']; ?>">
                    <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][source_recipe_title]" value="<?php echo esc_attr( $item['source_recipe_title'] ); ?>">
                    <?php foreach ( $item['source_recipes'] ?? [] as $source_index => $source_recipe ) : ?>
                        <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][source_recipes][<?php echo (int) $source_index; ?>][id]" value="<?php echo isset( $source_recipe['id'] ) ? (int) $source_recipe['id'] : 0; ?>">
                        <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][source_recipes][<?php echo (int) $source_index; ?>][title]" value="<?php echo esc_attr( isset( $source_recipe['title'] ) ? $source_recipe['title'] : '' ); ?>">
                    <?php endforeach; ?>
                    <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][amount]" value="<?php echo esc_attr( $item['amount'] ); ?>">
                    <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][unit]" value="<?php echo esc_attr( $item['unit'] ); ?>">
                    <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][name]" value="<?php echo esc_attr( $item['name'] ); ?>">
                    <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][notes]" value="<?php echo esc_attr( $item['notes'] ); ?>">
                    <?php foreach ( $item['term_ids'] ?? [] as $term_index => $term_id ) : ?>
                        <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][term_ids][<?php echo (int) $term_index; ?>]" value="<?php echo (int) $term_id; ?>">
                    <?php endforeach; ?>
                    <label>
                        <input class="shop-check shopping-check" type="checkbox" name="items[<?php echo esc_attr( $item_id ); ?>][checked]" value="1" <?php checked( $is_checked ); ?>>
                        <span>
                            <strong><?php echo esc_html( $item['name'] ); ?></strong>
                            <?php if ( $detail ) : ?>
                                <small>
                                    <?php echo esc_html( $detail ); ?>
                                </small>
                            <?php endif; ?>
                            <?php if ( $source_titles ) : ?>
                                <small class="shop-source"><?php
                                echo esc_html( sprintf(
                                    /* translators: %s: comma-separated recipe titles */
                                    __( 'For %s', 'cookbook' ),
                                    implode( ', ', $source_titles )
                                ) );
                                ?></small>
                            <?php endif; ?>
                        </span>
                    </label>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ( $household_reminders ) : ?>
            <section class="household-reminders soft-panel">
                <h2><?php esc_html_e( 'At home', 'cookbook' ); ?></h2>
                <ul class="household-list">
                    <?php foreach ( $household_reminders as $reminder ) :
                        $detail = $shopping_item_detail( $reminder );
                        $source_titles = $shopping_item_source_titles( $reminder );
                        ?>
                        <li>
                            <span>
                                <strong><?php echo esc_html( $reminder['name'] ); ?></strong>
                                <?php if ( $detail ) : ?>
                                    <small><?php echo esc_html( $detail ); ?></small>
                            <?php endif; ?>
                            <?php if ( $source_titles ) : ?>
                                <small class="shop-source"><?php
                                echo esc_html( sprintf(
                                    /* translators: %s: comma-separated recipe titles */
                                    __( 'For %s', 'cookbook' ),
                                    implode( ', ', $source_titles )
                                ) );
                                ?></small>
                            <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <div class="shop-add soft-panel">
            <input type="text" name="new_items[0][name]" placeholder="<?php esc_attr_e( 'Add item', 'cookbook' ); ?>">
            <button class="btn fresh" type="submit" name="list_command" value="save"><?php esc_html_e( 'Add', 'cookbook' ); ?></button>
        </div>
    <?php else : ?>
        <div class="shopping-bulk-bar" id="shopping-bulk-bar" hidden>
            <strong><span id="shopping-selected-count">0</span> <?php esc_html_e( 'selected', 'cookbook' ); ?></strong>
            <input type="text" id="bulk-item-name" placeholder="<?php esc_attr_e( 'Item name', 'cookbook' ); ?>">
            <button class="btn" type="button" id="bulk-merge-selected"><?php esc_html_e( 'Merge selected', 'cookbook' ); ?></button>
            <button class="btn household" type="submit" name="list_command" value="mark_household"><?php esc_html_e( 'Move to At home', 'cookbook' ); ?></button>
            <button class="btn danger" type="button" id="bulk-remove-selected"><?php esc_html_e( 'Remove selected', 'cookbook' ); ?></button>
        </div>

        <?php if ( $items ) : ?>
            <ul class="shopping-list">
            <?php foreach ( $items as $item ) :
                $item_id = $item['id'];
                $recipe_id = ! empty( $item['source_recipe_id'] ) ? (int) $item['source_recipe_id'] : 0;
                $recipe = $recipe_id ? get_post( $recipe_id ) : null;
                $is_checked = ! empty( $item['checked'] );
                $source_recipes = $item['source_recipes'] ?? [];
                $source_titles = $shopping_item_source_titles( $item );
                ?>
                <li class="shopping-row">
                    <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][id]" value="<?php echo esc_attr( $item_id ); ?>">
                    <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][source_recipe_id]" value="<?php echo (int) $recipe_id; ?>">
                    <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][source_recipe_title]" value="<?php echo esc_attr( $item['source_recipe_title'] ); ?>">
                    <?php foreach ( $source_recipes as $source_index => $source_recipe ) : ?>
                        <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][source_recipes][<?php echo (int) $source_index; ?>][id]" value="<?php echo isset( $source_recipe['id'] ) ? (int) $source_recipe['id'] : 0; ?>">
                        <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][source_recipes][<?php echo (int) $source_index; ?>][title]" value="<?php echo esc_attr( isset( $source_recipe['title'] ) ? $source_recipe['title'] : '' ); ?>">
                    <?php endforeach; ?>
                    <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][checked]" value="<?php echo $is_checked ? '1' : ''; ?>">
                    <?php foreach ( $item['term_ids'] ?? [] as $term_index => $term_id ) : ?>
                        <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][term_ids][<?php echo (int) $term_index; ?>]" value="<?php echo (int) $term_id; ?>">
                    <?php endforeach; ?>
                    <input class="shopping-row-select" type="checkbox" name="selected_items[]" value="<?php echo esc_attr( $item_id ); ?>" aria-label="<?php esc_attr_e( 'Select item', 'cookbook' ); ?>">
                    <div>
                        <div class="shopping-fields">
                            <input type="text" name="items[<?php echo esc_attr( $item_id ); ?>][amount]" value="<?php echo esc_attr( $item['amount'] ); ?>" placeholder="<?php esc_attr_e( '2', 'cookbook' ); ?>">
                            <input type="text" name="items[<?php echo esc_attr( $item_id ); ?>][unit]" value="<?php echo esc_attr( $item['unit'] ); ?>" placeholder="<?php esc_attr_e( 'g', 'cookbook' ); ?>">
                            <input type="text" name="items[<?php echo esc_attr( $item_id ); ?>][name]" value="<?php echo esc_attr( $item['name'] ); ?>" placeholder="<?php esc_attr_e( 'ingredient', 'cookbook' ); ?>" required>
                            <input type="text" name="items[<?php echo esc_attr( $item_id ); ?>][notes]" value="<?php echo esc_attr( $item['notes'] ); ?>" placeholder="<?php esc_attr_e( 'notes', 'cookbook' ); ?>">
                            <button type="button" class="remove" aria-label="<?php esc_attr_e( 'Remove', 'cookbook' ); ?>">×</button>
                        </div>
                        <?php if ( $source_titles && $source_recipes ) : ?>
                            <div class="shopping-source">
                                <?php esc_html_e( 'From', 'cookbook' ); ?>
                                <?php $rendered_source_count = 0; ?>
                                <?php foreach ( $source_recipes as $source_index => $source_recipe ) :
                                    $source_recipe_id = isset( $source_recipe['id'] ) ? (int) $source_recipe['id'] : 0;
                                    $source_recipe_post = $source_recipe_id ? get_post( $source_recipe_id ) : null;
                                    $source_recipe_title = isset( $source_recipe['title'] ) ? (string) $source_recipe['title'] : '';
                                    if ( $is_multiple_recipes_label( $source_recipe_title ) ) {
                                        continue;
                                    }
                                    if ( $rendered_source_count > 0 ) {
                                        echo esc_html( ', ' );
                                    }
                                    $rendered_source_count++;
                                    if ( $source_recipe_post && $source_recipe_post->post_type === App::POST_TYPE ) : ?>
                                        <a href="<?php echo esc_url( home_url( '/cookbook/recipe/' . $source_recipe_id ) ); ?>"><?php echo esc_html( get_the_title( $source_recipe_post ) ); ?></a>
                                    <?php else : ?>
                                        <?php echo esc_html( $source_recipe_title ); ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ( $recipe && $recipe->post_type === App::POST_TYPE ) : ?>
                            <div class="shopping-source">
                                <?php esc_html_e( 'From', 'cookbook' ); ?>
                                <a href="<?php echo esc_url( home_url( '/cookbook/recipe/' . $recipe_id ) ); ?>"><?php echo esc_html( get_the_title( $recipe ) ); ?></a>
                            </div>
                        <?php elseif ( ! empty( $item['source_recipe_title'] ) && ! $is_multiple_recipes_label( (string) $item['source_recipe_title'] ) ) : ?>
                            <div class="shopping-source"><?php echo esc_html( $item['source_recipe_title'] ); ?></div>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ( $household_reminders ) : ?>
            <section class="household-reminders soft-panel">
                <h2><?php esc_html_e( 'At home', 'cookbook' ); ?></h2>
                <ul class="household-list">
                    <?php foreach ( $household_reminders as $reminder_index => $reminder ) :
                        $detail = $shopping_item_detail( $reminder );
                        $source_titles = $shopping_item_source_titles( $reminder );
                        ?>
                        <li>
                            <span>
                                <strong><?php echo esc_html( $reminder['name'] ); ?></strong>
                                <?php if ( $detail ) : ?>
                                    <small><?php echo esc_html( $detail ); ?></small>
                                <?php endif; ?>
                                <?php if ( $source_titles ) : ?>
                                    <small class="shop-source"><?php
                                    echo esc_html( sprintf(
                                        /* translators: %s: comma-separated recipe titles */
                                        __( 'For %s', 'cookbook' ),
                                        implode( ', ', $source_titles )
                                    ) );
                                    ?></small>
                                <?php endif; ?>
                            </span>
                            <button class="btn secondary" type="submit" name="list_command" value="restore_household:<?php echo (int) $reminder_index; ?>"><?php esc_html_e( 'Need to buy', 'cookbook' ); ?></button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <h2><?php esc_html_e( 'Add items', 'cookbook' ); ?></h2>
        <div class="soft-panel">
            <div id="manual-items">
                <div class="manual-item-row">
                    <input type="text" name="new_items[0][amount]" placeholder="<?php esc_attr_e( '2', 'cookbook' ); ?>">
                    <input type="text" name="new_items[0][unit]" placeholder="<?php esc_attr_e( 'g', 'cookbook' ); ?>">
                    <input type="text" name="new_items[0][name]" placeholder="<?php esc_attr_e( 'ingredient', 'cookbook' ); ?>">
                    <input type="text" name="new_items[0][notes]" placeholder="<?php esc_attr_e( 'notes', 'cookbook' ); ?>">
                    <button type="button" class="remove" aria-label="<?php esc_attr_e( 'Remove', 'cookbook' ); ?>">×</button>
                </div>
            </div>
            <div class="toolbar" style="margin-bottom:0">
                <button type="button" class="btn secondary" id="add-manual-item"><?php esc_html_e( '+ Add item', 'cookbook' ); ?></button>
                <span class="spacer"></span>
                <button class="btn fresh" type="submit" name="list_command" value="save"><?php esc_html_e( 'Save list', 'cookbook' ); ?></button>
            </div>
        </div>
    <?php endif; ?>

</form>

<?php if ( $has_shopping_list_content ) : ?>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="toolbar">
        <?php wp_nonce_field( 'cookbook_update_shopping_list' ); ?>
        <input type="hidden" name="action" value="cookbook_update_shopping_list">
        <input type="hidden" name="list_id" value="<?php echo (int) $list_id; ?>">
        <input type="hidden" name="return_mode" value="<?php echo $is_shop_mode ? 'shop' : 'edit'; ?>">
        <input type="hidden" name="list_command" value="clear_all">
        <span class="spacer"></span>
        <button class="btn danger" type="submit" onclick="return confirm('<?php echo esc_js( $clear_list_confirm ); ?>')"><?php esc_html_e( 'Clear list', 'cookbook' ); ?></button>
    </form>
<?php endif; ?>

<script>
(function () {
    let updateBulkState = () => {};

    const form = document.getElementById('shopping-list-form');
    if (form) {
        form.addEventListener('click', e => {
            if (!e.target.classList || !e.target.classList.contains('remove')) return;
            const row = e.target.closest('.shopping-row');
            if (row) {
                row.remove();
                updateBulkState();
            }
        });
    }

    const shopList = document.getElementById('shop-list');
    if (shopList) {
        const remaining = document.getElementById('shop-remaining-count');
        const undo = document.getElementById('undo-shop-check');
        let lastChange = null;

        function checkedRows() {
            return Array.from(shopList.querySelectorAll('.shop-item')).filter(row => row.querySelector('.shop-check').checked);
        }

        function updateShopState() {
            const rows = Array.from(shopList.querySelectorAll('.shop-item'));
            let remainingCount = 0;
            rows.forEach(row => {
                const isChecked = row.querySelector('.shop-check').checked;
                row.classList.toggle('is-checked', isChecked);
                if (!isChecked) remainingCount++;
            });
            if (remaining) remaining.textContent = String(remainingCount);
            checkedRows().forEach(row => shopList.appendChild(row));
        }

        shopList.querySelectorAll('.shop-check').forEach(cb => {
            cb.addEventListener('change', () => {
                lastChange = { checkbox: cb, checked: !cb.checked };
                if (undo) undo.hidden = false;
                updateShopState();
            });
        });

        if (undo) {
            undo.addEventListener('click', () => {
                if (!lastChange) return;
                lastChange.checkbox.checked = lastChange.checked;
                lastChange = null;
                undo.hidden = true;
                updateShopState();
            });
        }

        updateShopState();
    }

    const shoppingList = document.querySelector('.shopping-list');
    const bulkBar = document.getElementById('shopping-bulk-bar');
    const selectedCount = document.getElementById('shopping-selected-count');
    const bulkName = document.getElementById('bulk-item-name');
    const bulkMerge = document.getElementById('bulk-merge-selected');
    const bulkRemove = document.getElementById('bulk-remove-selected');
    const multipleRecipesLabels = <?php echo wp_json_encode( array_values( $multiple_recipes_labels ) ); ?>;

    function shoppingRows() {
        return shoppingList ? Array.from(shoppingList.querySelectorAll('.shopping-row')) : [];
    }

    function rowNameInput(row) {
        return row ? row.querySelector('input[name$="[name]"]') : null;
    }

    function selectedRows() {
        return shoppingRows().filter(row => {
            const cb = row.querySelector('.shopping-row-select');
            return cb && cb.checked;
        });
    }

    function allInputs(row) {
        return Array.from(row.querySelectorAll('input'));
    }

    function inputByName(row, name) {
        return allInputs(row).find(input => input.name === name) || null;
    }

    function itemPrefix(row) {
        const idInput = row.querySelector('input[name$="[id]"]');
        return idInput ? idInput.name.replace(/\[id\]$/, '') : '';
    }

    function itemInput(row, field) {
        return row.querySelector('input[name$="[' + field + ']"]');
    }

    function inputValue(row, field) {
        const input = itemInput(row, field);
        return input ? input.value.trim() : '';
    }

    function isMultipleRecipesLabel(value) {
        const title = String(value || '').trim();
        return multipleRecipesLabels.includes(title);
    }

    function setInputValue(row, field, value) {
        const input = itemInput(row, field);
        if (input) input.value = value;
    }

    function appendHidden(row, name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        row.insertBefore(input, row.querySelector('.shopping-row-select'));
    }

    function replaceGroupedInputs(row, groupName, values, fields) {
        const prefix = itemPrefix(row);
        if (!prefix) return;
        row.querySelectorAll('input[name*="[' + groupName + ']"]').forEach(input => input.remove());
        values.forEach((value, index) => {
            if (fields) {
                fields.forEach(field => appendHidden(row, prefix + '[' + groupName + '][' + index + '][' + field + ']', value[field] || ''));
            } else {
                appendHidden(row, prefix + '[' + groupName + '][' + index + ']', value);
            }
        });
    }

    function collectTermIds(rows) {
        const ids = new Set();
        rows.forEach(row => {
            row.querySelectorAll('input[name*="[term_ids]"]').forEach(input => {
                if (input.value) ids.add(input.value);
            });
        });
        return Array.from(ids);
    }

    function collectSourceRecipes(rows) {
        const sources = new Map();
        rows.forEach(row => {
            let hasSourceRecipes = false;
            row.querySelectorAll('input[name*="[source_recipes]"][name$="[id]"]').forEach(idInput => {
                const titleInput = inputByName(row, idInput.name.replace(/\[id\]$/, '[title]'));
                const source = {
                    id: idInput.value.trim(),
                    title: titleInput ? titleInput.value.trim() : ''
                };
                if (!source.id && isMultipleRecipesLabel(source.title)) return;
                const key = source.id ? 'id:' + source.id : 'title:' + source.title;
                if (source.id || source.title) {
                    sources.set(key, source);
                    hasSourceRecipes = true;
                }
            });

            const fallback = {
                id: inputValue(row, 'source_recipe_id'),
                title: inputValue(row, 'source_recipe_title')
            };
            const key = fallback.id ? 'id:' + fallback.id : 'title:' + fallback.title;
            if (!hasSourceRecipes && (fallback.id || (fallback.title && !isMultipleRecipesLabel(fallback.title)))) sources.set(key, fallback);
        });
        return Array.from(sources.values());
    }

    function formatNumber(value) {
        if (Math.abs(value - Math.round(value)) < 0.0001) return String(Math.round(value));
        return value.toFixed(2).replace(/\.?0+$/, '');
    }

    function parseAmount(value) {
        const normalized = String(value || '').trim().replace(',', '.');
        if (!normalized) return null;
        const number = Number(normalized);
        return Number.isFinite(number) ? number : null;
    }

    function mergeQuantity(primary, rows) {
        const units = rows.map(row => inputValue(row, 'unit')).filter(Boolean);
        const uniqueUnits = Array.from(new Set(units.map(unit => unit.toLowerCase())));
        if (uniqueUnits.length > 1) return;

        let sum = 0;
        let hasNumber = false;
        for (const row of rows) {
            const amount = inputValue(row, 'amount');
            if (!amount) continue;
            const parsed = parseAmount(amount);
            if (parsed === null) return;
            sum += parsed;
            hasNumber = true;
        }
        if (hasNumber) setInputValue(primary, 'amount', formatNumber(sum));
        if (units.length) setInputValue(primary, 'unit', units[0]);
    }

    function mergeSelectedRows() {
        const rows = selectedRows();
        if (!rows.length) return;
        const primary = rows[0];
        const target = bulkName ? bulkName.value.trim() : '';
        if (target) setInputValue(primary, 'name', target);

        mergeQuantity(primary, rows);
        const notes = Array.from(new Set(rows.map(row => inputValue(row, 'notes')).filter(Boolean)));
        setInputValue(primary, 'notes', notes.join('; '));
        setInputValue(primary, 'checked', rows.every(row => inputValue(row, 'checked')) ? '1' : '');
        replaceGroupedInputs(primary, 'term_ids', collectTermIds(rows));
        replaceGroupedInputs(primary, 'source_recipes', collectSourceRecipes(rows), ['id', 'title']);

        rows.slice(1).forEach(row => row.remove());
        const cb = primary.querySelector('.shopping-row-select');
        if (cb) cb.checked = false;
        updateBulkState();
    }

    function preferredName(rows) {
        const counts = new Map();
        rows.forEach(row => {
            const input = rowNameInput(row);
            if (!input || !input.value.trim()) return;
            const label = input.value.trim();
            const current = counts.get(label) || { label, count: 0 };
            current.count++;
            counts.set(label, current);
        });
        return Array.from(counts.values()).sort((a, b) => {
            if (a.count !== b.count) return b.count - a.count;
            return a.label.localeCompare(b.label);
        })[0]?.label || '';
    }

    updateBulkState = function () {
        if (!bulkBar) return;
        const rows = selectedRows();
        bulkBar.hidden = rows.length === 0;
        if (form) form.classList.toggle('has-shopping-bulk-bar', rows.length > 0);
        if (selectedCount) selectedCount.textContent = String(rows.length);
        if (bulkName && rows.length) bulkName.value = preferredName(rows);
        shoppingRows().forEach(row => {
            const cb = row.querySelector('.shopping-row-select');
            row.classList.toggle('is-selected', !!cb && cb.checked);
        });
    };

    if (shoppingList) {
        shoppingList.addEventListener('change', e => {
            if (!e.target.matches('.shopping-row-select')) return;
            updateBulkState();
        });
        updateBulkState();
    }

    if (bulkMerge) {
        bulkMerge.addEventListener('click', mergeSelectedRows);
    }

    if (bulkRemove) {
        bulkRemove.addEventListener('click', () => {
            selectedRows().forEach(row => row.remove());
            updateBulkState();
        });
    }

    const root = document.getElementById('manual-items');
    const add = document.getElementById('add-manual-item');
    if (!root || !add) return;

    function renumber() {
        root.querySelectorAll('.manual-item-row').forEach((row, index) => {
            row.querySelectorAll('input').forEach(input => {
                input.name = input.name.replace(/new_items\[\d+\]/, 'new_items[' + index + ']');
            });
        });
    }

    add.addEventListener('click', () => {
        const row = root.querySelector('.manual-item-row').cloneNode(true);
        row.querySelectorAll('input').forEach(input => input.value = '');
        root.appendChild(row);
        renumber();
        row.querySelector('input[name$="[name]"]').focus();
    });

    root.addEventListener('click', e => {
        if (!e.target.classList || !e.target.classList.contains('remove')) return;
        const rows = root.querySelectorAll('.manual-item-row');
        const row = e.target.closest('.manual-item-row');
        if (rows.length > 1) {
            row.remove();
            renumber();
        } else {
            row.querySelectorAll('input').forEach(input => input.value = '');
        }
    });
})();
</script>

<?php include __DIR__ . '/_footer.php'; ?>
