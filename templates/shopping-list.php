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
$checked_count = 0;
foreach ( $items as $item ) {
    if ( ! empty( $item['checked'] ) ) {
        $checked_count++;
    }
}
$remaining_count = max( 0, count( $items ) - $checked_count );

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only flash flags.
$saved = isset( $_GET['saved'] );
$mode = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended
$is_shop_mode = $mode === 'shop';

$page_title = __( 'Shopping list', 'cookbook' );
include __DIR__ . '/_header.php';
?>
<a class="badge" href="<?php echo esc_url( home_url( '/cookbook/' ) ); ?>"><?php esc_html_e( '← All recipes', 'cookbook' ); ?></a>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="shopping-list-form">
    <?php wp_nonce_field( 'cookbook_update_shopping_list' ); ?>
    <input type="hidden" name="action" value="cookbook_update_shopping_list">
    <input type="hidden" name="list_id" value="<?php echo (int) $list_id; ?>">
    <input type="hidden" name="return_mode" value="<?php echo $is_shop_mode ? 'shop' : 'edit'; ?>">

    <div class="page-head">
        <div>
            <h1><?php esc_html_e( 'Shopping list', 'cookbook' ); ?></h1>
            <p class="subtitle">
                <?php
                echo esc_html( sprintf(
                    /* translators: 1: total items, 2: checked items */
                    __( '%1$d items, %2$d checked off.', 'cookbook' ),
                    count( $items ),
                    $checked_count
                ) );
                ?>
            </p>
        </div>
        <div class="page-actions">
            <?php if ( $is_shop_mode ) : ?>
                <a class="btn secondary" href="<?php echo esc_url( home_url( '/cookbook/shopping-list' ) ); ?>"><?php esc_html_e( 'Edit list', 'cookbook' ); ?></a>
            <?php else : ?>
                <a class="btn fresh" href="<?php echo esc_url( add_query_arg( 'mode', 'shop', home_url( '/cookbook/shopping-list' ) ) ); ?>"><?php esc_html_e( 'Shop mode', 'cookbook' ); ?></a>
                <button class="btn fresh" type="submit" name="list_command" value="save"><?php esc_html_e( 'Save list', 'cookbook' ); ?></button>
                <button class="btn secondary" type="submit" name="list_command" value="clear_checked"><?php esc_html_e( 'Clear checked', 'cookbook' ); ?></button>
                <button class="btn danger" type="submit" name="list_command" value="clear_all" onclick="return confirm('<?php echo esc_js( __( 'Clear the whole shopping list?', 'cookbook' ) ); ?>')"><?php esc_html_e( 'Clear list', 'cookbook' ); ?></button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ( $saved ) : ?>
        <div class="notice success"><?php esc_html_e( 'Shopping list saved.', 'cookbook' ); ?></div>
    <?php endif; ?>

    <?php if ( ! $items ) : ?>
        <div class="notice"><?php esc_html_e( 'Your shopping list is empty. Add a recipe from its recipe page, build it from the week planner, or add items manually below.', 'cookbook' ); ?></div>
        <div class="shop-add soft-panel">
            <input type="text" name="new_items[0][name]" placeholder="<?php esc_attr_e( 'Add item', 'cookbook' ); ?>">
            <button class="btn fresh" type="submit" name="list_command" value="save"><?php esc_html_e( 'Add', 'cookbook' ); ?></button>
        </div>
    <?php elseif ( $is_shop_mode ) : ?>
        <div class="shop-bar">
            <strong>
                <span id="shop-remaining-count"><?php echo (int) $remaining_count; ?></span>
                <?php esc_html_e( 'remaining', 'cookbook' ); ?>
            </strong>
            <label class="shop-toggle">
                <input type="checkbox" id="hide-checked-items">
                <?php esc_html_e( 'Hide checked', 'cookbook' ); ?>
            </label>
            <button class="btn secondary" type="button" id="undo-shop-check" hidden><?php esc_html_e( 'Undo', 'cookbook' ); ?></button>
            <button class="btn fresh" type="submit" name="list_command" value="save"><?php esc_html_e( 'Save', 'cookbook' ); ?></button>
            <button class="btn secondary" type="submit" name="list_command" value="clear_checked"><?php esc_html_e( 'Clear checked', 'cookbook' ); ?></button>
        </div>

        <ul class="shop-list" id="shop-list">
            <?php
            $shop_items = $items;
            usort( $shop_items, function( $a, $b ) {
                return (int) ! empty( $a['checked'] ) <=> (int) ! empty( $b['checked'] );
            } );
            foreach ( $shop_items as $item ) :
                $item_id = $item['id'];
                $is_checked = ! empty( $item['checked'] );
                $quantity = trim( implode( ' ', array_filter( [ $item['amount'], $item['unit'] ] ) ) );
                ?>
                <li class="shop-item<?php echo $is_checked ? ' is-checked' : ''; ?>">
                    <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][id]" value="<?php echo esc_attr( $item_id ); ?>">
                    <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][source_recipe_id]" value="<?php echo (int) $item['source_recipe_id']; ?>">
                    <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][source_recipe_title]" value="<?php echo esc_attr( $item['source_recipe_title'] ); ?>">
                    <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][amount]" value="<?php echo esc_attr( $item['amount'] ); ?>">
                    <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][unit]" value="<?php echo esc_attr( $item['unit'] ); ?>">
                    <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][name]" value="<?php echo esc_attr( $item['name'] ); ?>">
                    <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][notes]" value="<?php echo esc_attr( $item['notes'] ); ?>">
                    <label>
                        <input class="shop-check shopping-check" type="checkbox" name="items[<?php echo esc_attr( $item_id ); ?>][checked]" value="1" <?php checked( $is_checked ); ?>>
                        <span>
                            <strong><?php echo esc_html( $item['name'] ); ?></strong>
                            <?php if ( $quantity || $item['notes'] ) : ?>
                                <small>
                                    <?php echo esc_html( trim( $quantity . ( $quantity && $item['notes'] ? ' - ' : '' ) . $item['notes'] ) ); ?>
                                </small>
                            <?php endif; ?>
                        </span>
                    </label>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="shop-add soft-panel">
            <input type="text" name="new_items[0][name]" placeholder="<?php esc_attr_e( 'Add item', 'cookbook' ); ?>">
            <button class="btn fresh" type="submit" name="list_command" value="save"><?php esc_html_e( 'Add', 'cookbook' ); ?></button>
        </div>
    <?php else : ?>
        <ul class="shopping-list">
            <?php foreach ( $items as $item ) :
                $item_id = $item['id'];
                $recipe_id = ! empty( $item['source_recipe_id'] ) ? (int) $item['source_recipe_id'] : 0;
                $recipe = $recipe_id ? get_post( $recipe_id ) : null;
                $is_checked = ! empty( $item['checked'] );
                ?>
                <li class="shopping-row<?php echo $is_checked ? ' is-checked' : ''; ?>">
                    <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][id]" value="<?php echo esc_attr( $item_id ); ?>">
                    <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][source_recipe_id]" value="<?php echo (int) $recipe_id; ?>">
                    <input type="hidden" name="items[<?php echo esc_attr( $item_id ); ?>][source_recipe_title]" value="<?php echo esc_attr( $item['source_recipe_title'] ); ?>">
                    <input class="shopping-check" type="checkbox" name="items[<?php echo esc_attr( $item_id ); ?>][checked]" value="1" <?php checked( $is_checked ); ?> aria-label="<?php esc_attr_e( 'Checked', 'cookbook' ); ?>">
                    <div>
                        <div class="shopping-fields">
                            <input type="text" name="items[<?php echo esc_attr( $item_id ); ?>][amount]" value="<?php echo esc_attr( $item['amount'] ); ?>" placeholder="<?php esc_attr_e( '2', 'cookbook' ); ?>">
                            <input type="text" name="items[<?php echo esc_attr( $item_id ); ?>][unit]" value="<?php echo esc_attr( $item['unit'] ); ?>" placeholder="<?php esc_attr_e( 'g', 'cookbook' ); ?>">
                            <input type="text" name="items[<?php echo esc_attr( $item_id ); ?>][name]" value="<?php echo esc_attr( $item['name'] ); ?>" placeholder="<?php esc_attr_e( 'ingredient', 'cookbook' ); ?>" required>
                            <input type="text" name="items[<?php echo esc_attr( $item_id ); ?>][notes]" value="<?php echo esc_attr( $item['notes'] ); ?>" placeholder="<?php esc_attr_e( 'notes', 'cookbook' ); ?>">
                            <button type="button" class="remove" aria-label="<?php esc_attr_e( 'Remove', 'cookbook' ); ?>">×</button>
                        </div>
                        <?php if ( $recipe && $recipe->post_type === App::POST_TYPE ) : ?>
                            <div class="shopping-source">
                                <?php esc_html_e( 'From', 'cookbook' ); ?>
                                <a href="<?php echo esc_url( home_url( '/cookbook/recipe/' . $recipe_id ) ); ?>"><?php echo esc_html( get_the_title( $recipe ) ); ?></a>
                            </div>
                        <?php elseif ( ! empty( $item['source_recipe_title'] ) ) : ?>
                            <div class="shopping-source"><?php echo esc_html( $item['source_recipe_title'] ); ?></div>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

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

<script>
(function () {
    document.querySelectorAll('.shopping-check').forEach(cb => {
        cb.addEventListener('change', () => {
            const row = cb.closest('.shopping-row');
            if (row) row.classList.toggle('is-checked', cb.checked);
        });
    });

    const form = document.getElementById('shopping-list-form');
    if (form) {
        form.addEventListener('click', e => {
            if (!e.target.classList || !e.target.classList.contains('remove')) return;
            const row = e.target.closest('.shopping-row');
            if (row) row.remove();
        });
    }

    const shopList = document.getElementById('shop-list');
    if (shopList) {
        const remaining = document.getElementById('shop-remaining-count');
        const hideChecked = document.getElementById('hide-checked-items');
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

        if (hideChecked) {
            hideChecked.addEventListener('change', () => {
                shopList.classList.toggle('hide-checked', hideChecked.checked);
            });
        }

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
