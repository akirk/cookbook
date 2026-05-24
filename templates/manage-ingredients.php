<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- variables here are template-local, not actually global.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
use Cookbook\App;

if ( ! is_user_logged_in() || ! current_user_can( 'manage_categories' ) ) {
    status_header( 403 );
    $page_title = __( 'Not allowed', 'cookbook' );
    include __DIR__ . '/_header.php';
    echo '<h1>' . esc_html__( 'Not allowed.', 'cookbook' ) . '</h1>';
    include __DIR__ . '/_footer.php';
    return;
}

$all_terms = get_terms( [
    'taxonomy'   => App::TAX_INGREDIENT,
    'hide_empty' => false,
    'orderby'    => 'count',
    'order'      => 'DESC',
] );
if ( is_wp_error( $all_terms ) ) {
    $all_terms = [];
}

// Build a name→term lookup so we can show "child of X" inline without extra queries.
$by_id = [];
foreach ( $all_terms as $t ) { $by_id[ (int) $t->term_id ] = $t; }

// Notices from POST handlers.
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only flash messages.
$merged_count  = isset( $_GET['merged'] ) ? max( 0, (int) $_GET['merged'] ) : -1;
$grouped_count = isset( $_GET['grouped'] ) ? max( 0, (int) $_GET['grouped'] ) : -1;
$renamed_count = isset( $_GET['renamed'] ) ? max( 0, (int) $_GET['renamed'] ) : -1;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$page_title = __( 'Manage ingredients', 'cookbook' );
include __DIR__ . '/_header.php';
?>
<style>
    .mi-toolbar { position: sticky; top: 0; background: var(--bg); padding: 0.6rem 0; border-bottom: 1px solid var(--line); z-index: 10; margin: 0 0 1rem; display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
    .mi-toolbar.is-active { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    .mi-toolbar select { width: auto; min-width: 14rem; }
    .mi-search { width: 100%; max-width: 100%; }
    .mi-list { list-style: none; padding: 0; margin: 0; }
    .mi-row { display: flex; gap: 0.6rem; align-items: center; padding: 0.4rem 0.5rem; border-bottom: 1px solid var(--line); }
    .mi-row:hover { background: var(--card); }
    .mi-row.is-hidden { display: none; }
    .mi-row label { margin: 0; font-weight: 500; flex: 1; cursor: pointer; display: flex; align-items: baseline; gap: 0.5rem; }
    .mi-row .mi-count { color: var(--muted); font-size: 0.85rem; min-width: 2.5rem; text-align: right; }
    .mi-row .mi-parent { color: var(--muted); font-size: 0.85rem; }
    .mi-row .mi-actions { display: flex; gap: 0.4rem; }
    .mi-row .mi-actions a, .mi-row .mi-actions button { font-size: 0.85rem; padding: 0.2rem 0.5rem; }
    .mi-rename { display: none; flex: 1; gap: 0.4rem; align-items: center; }
    .mi-rename.is-open { display: flex; }
    .mi-rename input { flex: 1; padding: 0.3rem 0.5rem; }
    .mi-row.is-renaming label { display: none; }
    .mi-empty { color: var(--muted); padding: 1rem 0; }
    .mi-selected-count { font-weight: 600; }
</style>

<h1><?php esc_html_e( 'Manage ingredients', 'cookbook' ); ?></h1>
<p class="subtitle"><?php esc_html_e( 'Tick duplicates and merge them into one canonical term, or group similar ingredients under a parent. Merging rewrites the linked recipes; grouping just sets a hierarchy.', 'cookbook' ); ?></p>

<?php if ( $merged_count >= 0 ) : ?>
    <div class="notice success">
        <?php
        /* translators: %d: number of merged ingredient terms */
        echo esc_html( sprintf( _n( 'Merged %d ingredient.', 'Merged %d ingredients.', max( 1, $merged_count ), 'cookbook' ), $merged_count ) );
        ?>
    </div>
<?php endif; ?>
<?php if ( $grouped_count >= 0 ) : ?>
    <div class="notice success">
        <?php
        /* translators: %d: number of grouped ingredient terms */
        echo esc_html( sprintf( _n( 'Grouped %d ingredient.', 'Grouped %d ingredients.', max( 1, $grouped_count ), 'cookbook' ), $grouped_count ) );
        ?>
    </div>
<?php endif; ?>
<?php if ( $renamed_count >= 0 ) : ?>
    <div class="notice success"><?php esc_html_e( 'Ingredient renamed.', 'cookbook' ); ?></div>
<?php endif; ?>

<?php if ( ! $all_terms ) : ?>
    <div class="notice"><?php esc_html_e( 'No ingredients yet — add or import a recipe to populate this list.', 'cookbook' ); ?></div>
<?php else : ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="mi-form">
    <?php wp_nonce_field( 'cookbook_manage_ingredients' ); ?>
    <input type="hidden" name="action" id="mi-action" value="cookbook_merge_ingredients">

    <input type="search" class="mi-search" id="mi-search" placeholder="<?php esc_attr_e( 'Filter ingredients…', 'cookbook' ); ?>" autocomplete="off">

    <div class="mi-toolbar" id="mi-toolbar">
        <span><span class="mi-selected-count" id="mi-count">0</span> <?php esc_html_e( 'selected', 'cookbook' ); ?></span>
        <span class="spacer" style="flex:1"></span>
        <label for="mi-target" style="margin:0"><?php esc_html_e( 'Target:', 'cookbook' ); ?></label>
        <select name="target_id" id="mi-target">
            <option value="0">— <?php esc_html_e( 'pick a target ingredient', 'cookbook' ); ?> —</option>
            <?php foreach ( $all_terms as $t ) : ?>
                <option value="<?php echo (int) $t->term_id; ?>"><?php echo esc_html( $t->name ); ?> <?php /* translators: %d: number of recipes using this ingredient */ printf( esc_html__( '(%d)', 'cookbook' ), (int) $t->count ); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn" name="do" value="merge" id="mi-merge" disabled><?php esc_html_e( 'Merge into target', 'cookbook' ); ?></button>
        <button type="submit" class="btn secondary" name="do" value="group" id="mi-group" disabled><?php esc_html_e( 'Make children of target', 'cookbook' ); ?></button>
    </div>

    <ul class="mi-list" id="mi-list">
        <?php foreach ( $all_terms as $t ) :
            $parent = $t->parent && isset( $by_id[ (int) $t->parent ] ) ? $by_id[ (int) $t->parent ] : null;
            $view_url = home_url( '/cookbook/ingredient/' . $t->slug );
            ?>
            <li class="mi-row" data-name="<?php echo esc_attr( mb_strtolower( $t->name ) ); ?>" data-id="<?php echo (int) $t->term_id; ?>">
                <label>
                    <input type="checkbox" name="source_ids[]" value="<?php echo (int) $t->term_id; ?>" class="mi-check">
                    <span class="mi-name"><?php echo esc_html( $t->name ); ?></span>
                    <?php if ( $parent ) : ?>
                        <span class="mi-parent">↳ <?php
                            /* translators: %s: parent ingredient name */
                            echo esc_html( sprintf( __( 'child of %s', 'cookbook' ), $parent->name ) );
                        ?></span>
                    <?php endif; ?>
                </label>
                <span class="mi-rename" data-rename-for="<?php echo (int) $t->term_id; ?>">
                    <input type="text" value="<?php echo esc_attr( $t->name ); ?>" data-original="<?php echo esc_attr( $t->name ); ?>">
                    <button type="button" class="btn mi-rename-save"><?php esc_html_e( 'Save', 'cookbook' ); ?></button>
                    <button type="button" class="btn secondary mi-rename-cancel"><?php esc_html_e( 'Cancel', 'cookbook' ); ?></button>
                </span>
                <span class="mi-count"><?php echo (int) $t->count; ?></span>
                <span class="mi-actions">
                    <a href="<?php echo esc_url( $view_url ); ?>"><?php esc_html_e( 'View', 'cookbook' ); ?></a>
                    <button type="button" class="btn secondary mi-rename-toggle"><?php esc_html_e( 'Rename', 'cookbook' ); ?></button>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
</form>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="mi-rename-form" style="display:none">
    <?php wp_nonce_field( 'cookbook_manage_ingredients' ); ?>
    <input type="hidden" name="action" value="cookbook_rename_ingredient">
    <input type="hidden" name="term_id" id="mi-rename-term">
    <input type="hidden" name="name" id="mi-rename-name">
</form>

<script>
(function () {
    const form    = document.getElementById('mi-form');
    const list    = document.getElementById('mi-list');
    const search  = document.getElementById('mi-search');
    const target  = document.getElementById('mi-target');
    const action  = document.getElementById('mi-action');
    const merge   = document.getElementById('mi-merge');
    const group   = document.getElementById('mi-group');
    const counter = document.getElementById('mi-count');
    const toolbar = document.getElementById('mi-toolbar');

    function refresh() {
        const checked = list.querySelectorAll('.mi-check:checked');
        const n = checked.length;
        counter.textContent = String(n);
        toolbar.classList.toggle('is-active', n > 0);
        const haveTarget = parseInt(target.value, 10) > 0;
        // For "merge" the target must not be one of the checked sources.
        let conflict = false;
        if (haveTarget) {
            checked.forEach(cb => { if (cb.value === target.value) conflict = true; });
        }
        merge.disabled = !(n > 0 && haveTarget && !conflict);
        // "Group" allows target = 0 (top-level).
        group.disabled = !(n > 0 && !conflict);
    }

    let lastOp = 'merge';
    list.addEventListener('change', e => {
        if (e.target.classList.contains('mi-check')) refresh();
    });
    target.addEventListener('change', refresh);
    merge.addEventListener('click', () => { lastOp = 'merge'; action.value = 'cookbook_merge_ingredients'; });
    group.addEventListener('click', () => { lastOp = 'group'; action.value = 'cookbook_group_ingredients'; });

    form.addEventListener('submit', e => {
        const op = lastOp;
        const n  = list.querySelectorAll('.mi-check:checked').length;
        const tgt = target.options[target.selectedIndex].text.replace(/\s*\(\d+\)\s*$/, '');
        const msg = op === 'merge'
            ? `Merge ${n} ingredient(s) into "${tgt}"? This rewrites the linked recipes and deletes the source terms.`
            : (parseInt(target.value, 10) > 0
                ? `Make ${n} ingredient(s) children of "${tgt}"?`
                : `Move ${n} ingredient(s) to top level (no parent)?`);
        if (!window.confirm(msg)) e.preventDefault();
    });

    // Substring filter — case-insensitive, matches the lowercased name on data-name.
    search.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        list.querySelectorAll('.mi-row').forEach(row => {
            const name = row.getAttribute('data-name') || '';
            row.classList.toggle('is-hidden', q !== '' && !name.includes(q));
        });
    });

    // Inline rename: posts via the hidden mi-rename-form so we can keep this page sticky-stateful.
    const renameForm = document.getElementById('mi-rename-form');
    list.addEventListener('click', e => {
        const row = e.target.closest('.mi-row');
        if (!row) return;
        if (e.target.classList.contains('mi-rename-toggle')) {
            row.classList.add('is-renaming');
            row.querySelector('.mi-rename').classList.add('is-open');
            const inp = row.querySelector('.mi-rename input');
            inp.focus(); inp.select();
        } else if (e.target.classList.contains('mi-rename-cancel')) {
            row.classList.remove('is-renaming');
            row.querySelector('.mi-rename').classList.remove('is-open');
            const inp = row.querySelector('.mi-rename input');
            inp.value = inp.getAttribute('data-original') || '';
        } else if (e.target.classList.contains('mi-rename-save')) {
            const inp = row.querySelector('.mi-rename input');
            const name = inp.value.trim();
            if (name === '' || name === inp.getAttribute('data-original')) {
                row.classList.remove('is-renaming');
                row.querySelector('.mi-rename').classList.remove('is-open');
                return;
            }
            document.getElementById('mi-rename-term').value = row.getAttribute('data-id');
            document.getElementById('mi-rename-name').value = name;
            renameForm.submit();
        }
    });

    refresh();
})();
</script>

<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>
