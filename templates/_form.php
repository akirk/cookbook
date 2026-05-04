<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- variables here are template-local, not actually global.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Shared recipe form. Variables expected:
 *   $id (int|0), $post (WP_Post|null), $is_new (bool)
 */
use Cookbook\App;
use Cookbook\Units;

$title        = $post ? get_the_title( $post ) : '';
$content      = $post ? $post->post_content : '';
$servings     = $post ? (int) get_post_meta( $id, App::META_SERVINGS, true ) : 4;
$prep         = $post ? (int) get_post_meta( $id, App::META_PREP, true ) : 0;
$cook         = $post ? (int) get_post_meta( $id, App::META_COOK, true ) : 0;
$ingredients  = $post ? (array) get_post_meta( $id, App::META_INGREDIENTS, true ) : [];
$instructions = $post ? (array) get_post_meta( $id, App::META_INSTRUCTIONS, true ) : [];
$source_url   = $post ? (string) get_post_meta( $id, App::META_SOURCE_URL, true ) : '';
$notes        = $post ? (string) get_post_meta( $id, App::META_NOTES, true ) : '';

if ( ! $ingredients ) {
    $ingredients = [ [ 'amount' => '', 'unit' => '', 'name' => '', 'notes' => '' ] ];
}
if ( ! $instructions ) {
    $instructions = [ '' ];
}

$categories = get_terms( [ 'taxonomy' => App::TAX_CATEGORY, 'hide_empty' => false ] );
$cuisines   = get_terms( [ 'taxonomy' => App::TAX_CUISINE,  'hide_empty' => false ] );
$current_categories = $post ? wp_get_object_terms( $id, App::TAX_CATEGORY, [ 'fields' => 'ids' ] ) : [];
$current_cuisines   = $post ? wp_get_object_terms( $id, App::TAX_CUISINE,  [ 'fields' => 'ids' ] ) : [];
$current_tags = $post ? wp_get_object_terms( $id, App::TAX_TAG, [ 'fields' => 'names' ] ) : [];
$tags_string = is_wp_error( $current_tags ) ? '' : implode( ', ', $current_tags );

$pref = App::get_user_unit_preference();
$unit_options = Units::COMMON_UNITS[ $pref ];
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" id="recipe-form">
    <?php wp_nonce_field( 'cookbook_save' ); ?>
    <input type="hidden" name="action" value="cookbook_save">
    <input type="hidden" name="id" value="<?php echo (int) $id; ?>">

    <label for="title"><?php esc_html_e( 'Title', 'cookbook' ); ?></label>
    <input id="title" type="text" name="title" value="<?php echo esc_attr( $title ); ?>" required autofocus>

    <label><?php esc_html_e( 'Photo', 'cookbook' ); ?></label>
    <?php $thumb_url = $post && has_post_thumbnail( $id ) ? get_the_post_thumbnail_url( $id, 'medium' ) : ''; ?>
    <?php if ( $thumb_url ) : ?>
        <div style="display:flex;gap:1rem;align-items:flex-start;margin-bottom:0.5rem">
            <img src="<?php echo esc_url( $thumb_url ); ?>" alt="" style="max-width:240px;border-radius:6px;border:1px solid var(--line)">
            <label style="font-weight:normal;display:flex;gap:0.4rem;align-items:center;margin:0">
                <input type="checkbox" name="remove_image" value="1"> <?php esc_html_e( 'Remove photo', 'cookbook' ); ?>
            </label>
        </div>
    <?php endif; ?>
    <input id="image" type="file" name="image" accept="image/*">
    <p class="help"><?php echo $thumb_url ? esc_html__( 'Upload a new file to replace the current photo.', 'cookbook' ) : esc_html__( 'Optional. Will be added to the media library.', 'cookbook' ); ?></p>

    <label for="description"><?php esc_html_e( 'Short description', 'cookbook' ); ?></label>
    <textarea id="description" name="description" style="min-height:4rem"><?php echo esc_textarea( $content ); ?></textarea>

    <div class="grid">
        <div>
            <label for="servings"><?php esc_html_e( 'Servings (default)', 'cookbook' ); ?></label>
            <input id="servings" type="number" min="1" name="servings" value="<?php echo (int) ( $servings ?: 4 ); ?>">
        </div>
        <div>
            <label for="prep_time"><?php esc_html_e( 'Prep time (minutes)', 'cookbook' ); ?></label>
            <input id="prep_time" type="number" min="0" name="prep_time" value="<?php echo (int) $prep; ?>">
        </div>
        <div>
            <label for="cook_time"><?php esc_html_e( 'Cook time (minutes)', 'cookbook' ); ?></label>
            <input id="cook_time" type="number" min="0" name="cook_time" value="<?php echo (int) $cook; ?>">
        </div>
        <div>
            <label for="source_url"><?php esc_html_e( 'Source URL (optional)', 'cookbook' ); ?></label>
            <input id="source_url" type="url" name="source_url" value="<?php echo esc_attr( $source_url ); ?>">
        </div>
    </div>

    <h2><?php esc_html_e( 'Ingredients', 'cookbook' ); ?></h2>
    <p class="help"><?php esc_html_e( 'Amount + unit are optional. Enter "1/2", "1.5", or use fractions like ½. Recognised units convert automatically; "piece", "clove", "pinch" etc. are kept as-is.', 'cookbook' ); ?></p>
    <div id="ingredient-rows">
        <?php foreach ( $ingredients as $i => $row ) : ?>
            <div class="row">
                <input type="text"   name="ingredients[<?php echo (int) $i; ?>][amount]" value="<?php echo esc_attr( $row['amount'] ?? '' ); ?>" placeholder="<?php esc_attr_e( '2', 'cookbook' ); ?>">
                <input type="text"   name="ingredients[<?php echo (int) $i; ?>][unit]"   value="<?php echo esc_attr( $row['unit'] ?? '' ); ?>"   placeholder="<?php esc_attr_e( 'g', 'cookbook' ); ?>" list="recipe-units">
                <input type="text"   name="ingredients[<?php echo (int) $i; ?>][name]"   value="<?php echo esc_attr( $row['name'] ?? '' ); ?>"   placeholder="<?php esc_attr_e( 'ingredient', 'cookbook' ); ?>" required>
                <input type="text"   name="ingredients[<?php echo (int) $i; ?>][notes]"  value="<?php echo esc_attr( $row['notes'] ?? '' ); ?>"  placeholder="<?php esc_attr_e( 'chopped', 'cookbook' ); ?>">
                <button type="button" class="remove" aria-label="<?php esc_attr_e( 'Remove', 'cookbook' ); ?>">×</button>
            </div>
        <?php endforeach; ?>
    </div>
    <datalist id="recipe-units">
        <?php foreach ( $unit_options as $u ) : ?>
            <option value="<?php echo esc_attr( $u ); ?>"></option>
        <?php endforeach; ?>
    </datalist>
    <button type="button" class="btn secondary" id="add-ingredient"><?php esc_html_e( '+ Add ingredient', 'cookbook' ); ?></button>

    <h2><?php esc_html_e( 'Instructions', 'cookbook' ); ?></h2>
    <div id="instruction-rows">
        <?php foreach ( $instructions as $i => $step ) : ?>
            <div class="row" style="grid-template-columns: 1fr auto; align-items: flex-start">
                <textarea name="instructions[]" placeholder="<?php
                    /* translators: %d: step number */
                    echo esc_attr( sprintf( __( 'Step %d', 'cookbook' ), (int) $i + 1 ) );
                ?>"><?php echo esc_textarea( $step ); ?></textarea>
                <button type="button" class="remove" aria-label="<?php esc_attr_e( 'Remove', 'cookbook' ); ?>">×</button>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn secondary" id="add-instruction"><?php esc_html_e( '+ Add step', 'cookbook' ); ?></button>

    <h2><?php esc_html_e( 'Categorisation', 'cookbook' ); ?></h2>
    <div class="grid">
        <div>
            <label for="categories"><?php esc_html_e( 'Categories', 'cookbook' ); ?></label>
            <select id="categories" name="categories[]" multiple size="5" style="height:auto">
                <?php foreach ( (array) $categories as $cat ) : ?>
                    <option value="<?php echo (int) $cat->term_id; ?>" <?php selected( in_array( $cat->term_id, (array) $current_categories ) ); ?>>
                        <?php echo esc_html( $cat->name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="help"><?php esc_html_e( 'Or type new ones (comma-separated):', 'cookbook' ); ?> <input type="text" name="categories[]" placeholder="<?php esc_attr_e( 'Mains, Desserts', 'cookbook' ); ?>"></p>
        </div>
        <div>
            <label for="cuisines"><?php esc_html_e( 'Cuisines', 'cookbook' ); ?></label>
            <select id="cuisines" name="cuisines[]" multiple size="5" style="height:auto">
                <?php foreach ( (array) $cuisines as $cui ) : ?>
                    <option value="<?php echo (int) $cui->term_id; ?>" <?php selected( in_array( $cui->term_id, (array) $current_cuisines ) ); ?>>
                        <?php echo esc_html( $cui->name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="help"><?php esc_html_e( 'Or type a new one:', 'cookbook' ); ?> <input type="text" name="cuisines[]" placeholder="<?php esc_attr_e( 'Italian', 'cookbook' ); ?>"></p>
        </div>
    </div>

    <label for="tags"><?php esc_html_e( 'Tags (comma-separated)', 'cookbook' ); ?></label>
    <input id="tags" type="text" name="tags" value="<?php echo esc_attr( $tags_string ); ?>" placeholder="<?php esc_attr_e( 'quick, vegetarian, weeknight', 'cookbook' ); ?>">

    <label for="notes"><?php esc_html_e( 'Notes (optional)', 'cookbook' ); ?></label>
    <textarea id="notes" name="notes" style="min-height:5rem"><?php echo esc_textarea( $notes ); ?></textarea>

    <div class="toolbar" style="margin-top:1.5rem">
        <button class="btn" type="submit"><?php echo $is_new ? esc_html__( 'Create recipe', 'cookbook' ) : esc_html__( 'Save recipe', 'cookbook' ); ?></button>
        <a class="btn secondary" href="<?php echo esc_url( $post ? home_url( '/cookbook/recipe/' . $id ) : home_url( '/cookbook/' ) ); ?>"><?php esc_html_e( 'Cancel', 'cookbook' ); ?></a>
    </div>
</form>

<script>
(function () {
    const ingRoot = document.getElementById('ingredient-rows');
    const insRoot = document.getElementById('instruction-rows');

    function nextIndex(root) {
        return root.querySelectorAll('.row').length;
    }

    document.getElementById('add-ingredient').addEventListener('click', () => {
        const i = nextIndex(ingRoot);
        const row = document.createElement('div');
        row.className = 'row';
        row.innerHTML = `
            <input type="text" name="ingredients[${i}][amount]" placeholder="2">
            <input type="text" name="ingredients[${i}][unit]"   placeholder="g" list="recipe-units">
            <input type="text" name="ingredients[${i}][name]"   placeholder="ingredient" required>
            <input type="text" name="ingredients[${i}][notes]"  placeholder="chopped">
            <button type="button" class="remove" aria-label="Remove">×</button>
        `;
        ingRoot.appendChild(row);
        row.querySelector('input').focus();
    });

    document.getElementById('add-instruction').addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'row';
        row.style.gridTemplateColumns = '1fr auto';
        row.style.alignItems = 'flex-start';
        const num = insRoot.querySelectorAll('.row').length + 1;
        row.innerHTML = `
            <textarea name="instructions[]" placeholder="Step ${num}"></textarea>
            <button type="button" class="remove" aria-label="Remove">×</button>
        `;
        insRoot.appendChild(row);
        row.querySelector('textarea').focus();
    });

    document.addEventListener('click', (e) => {
        if (e.target.classList && e.target.classList.contains('remove')) {
            const row = e.target.closest('.row');
            const root = row.parentElement;
            if (root.querySelectorAll('.row').length > 1) {
                row.remove();
            } else {
                row.querySelectorAll('input, textarea').forEach(el => el.value = '');
            }
        }
    });
})();
</script>
