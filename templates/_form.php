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

$data_id      = $post ? (int) $post->ID : 0;
$save_id      = $is_new ? 0 : (int) $id;
$source_id    = isset( $variation_source_id ) ? (int) $variation_source_id : 0;
$title        = isset( $title_override ) ? (string) $title_override : ( $post ? get_the_title( $post ) : '' );
$content      = $post ? $post->post_content : '';
$servings     = $post ? (int) get_post_meta( $data_id, App::META_SERVINGS, true ) : 4;
$prep         = $post ? (int) get_post_meta( $data_id, App::META_PREP, true ) : 0;
$cook         = $post ? (int) get_post_meta( $data_id, App::META_COOK, true ) : 0;
$ingredients  = $post ? (array) get_post_meta( $data_id, App::META_INGREDIENTS, true ) : [];
$instructions = $post ? (array) get_post_meta( $data_id, App::META_INSTRUCTIONS, true ) : [];
$recipe_parts = $post ? App::get_recipe_parts( $data_id ) : [];
$source_url   = $post ? (string) get_post_meta( $data_id, App::META_SOURCE_URL, true ) : '';
$notes        = $post ? (string) get_post_meta( $data_id, App::META_NOTES, true ) : '';
$parent_id    = isset( $variation_parent_id )
    ? (int) $variation_parent_id
    : ( $post && ! $is_new ? (int) $post->post_parent : 0 );
$cancel_url   = isset( $cancel_url ) ? (string) $cancel_url : ( $save_id ? home_url( '/cookbook/recipe/' . $save_id ) : home_url( '/cookbook/' ) );
$submit_label = $is_new && $source_id
    ? __( 'Create variation', 'cookbook' )
    : ( $is_new ? __( 'Create recipe', 'cookbook' ) : __( 'Save recipe', 'cookbook' ) );

if ( ! $ingredients ) {
    $ingredients = [ [ 'amount' => '', 'unit' => '', 'name' => '', 'notes' => '' ] ];
}
if ( ! $instructions ) {
    $instructions = [ '' ];
}

$blank_ingredient = [ 'amount' => '', 'unit' => '', 'name' => '', 'notes' => '' ];
$ingredient_sections = [];
$instruction_sections = [];
foreach ( $recipe_parts as $part ) {
    if ( ! empty( $part['ingredients'] ) ) {
        $ingredient_sections[] = [
            'title'       => (string) ( $part['title'] ?? '' ),
            'ingredients' => (array) $part['ingredients'],
        ];
    }
    if ( ! empty( $part['instructions'] ) ) {
        $instruction_sections[] = [
            'title'        => (string) ( $part['title'] ?? '' ),
            'instructions' => (array) $part['instructions'],
        ];
    }
}
if ( ! $ingredient_sections ) {
    $ingredient_sections[] = [
        'title'       => '',
        'ingredients' => $ingredients ?: [ $blank_ingredient ],
    ];
}
if ( ! $instruction_sections ) {
    $instruction_sections[] = [
        'title'        => '',
        'instructions' => $instructions ?: [ '' ],
    ];
}
foreach ( $ingredient_sections as &$section ) {
    if ( empty( $section['ingredients'] ) ) {
        $section['ingredients'] = [ $blank_ingredient ];
    }
}
unset( $section );
foreach ( $instruction_sections as &$section ) {
    if ( empty( $section['instructions'] ) ) {
        $section['instructions'] = [ '' ];
    }
}
unset( $section );

$categories = get_terms( [ 'taxonomy' => App::TAX_CATEGORY, 'hide_empty' => false ] );
$cuisines   = get_terms( [ 'taxonomy' => App::TAX_CUISINE,  'hide_empty' => false ] );
$current_categories = $post ? wp_get_object_terms( $data_id, App::TAX_CATEGORY, [ 'fields' => 'ids' ] ) : [];
$current_cuisines   = $post ? wp_get_object_terms( $data_id, App::TAX_CUISINE,  [ 'fields' => 'ids' ] ) : [];
$current_tags = $post ? wp_get_object_terms( $data_id, App::TAX_TAG, [ 'fields' => 'names' ] ) : [];
$tags_string = is_wp_error( $current_tags ) ? '' : implode( ', ', $current_tags );
$variation_parent_options = get_posts( [
    'post_type'      => App::POST_TYPE,
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
] );

$pref = App::get_user_unit_preference();
$unit_options = Units::COMMON_UNITS[ $pref ];
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" id="recipe-form">
    <?php wp_nonce_field( 'cookbook_save' ); ?>
    <input type="hidden" name="action" value="cookbook_save">
    <input type="hidden" name="id" value="<?php echo (int) $save_id; ?>">
    <?php if ( $is_new && $source_id && has_post_thumbnail( $source_id ) ) : ?>
        <input type="hidden" name="copy_thumbnail_from" value="<?php echo (int) $source_id; ?>">
    <?php endif; ?>

    <label for="title"><?php esc_html_e( 'Title', 'cookbook' ); ?></label>
    <input id="title" type="text" name="title" value="<?php echo esc_attr( $title ); ?>" required autofocus>

    <label><?php esc_html_e( 'Photo', 'cookbook' ); ?></label>
    <?php $thumb_url = $post && has_post_thumbnail( $data_id ) ? get_the_post_thumbnail_url( $data_id, 'medium' ) : ''; ?>
    <?php if ( $thumb_url ) : ?>
        <div style="display:flex;gap:1rem;align-items:flex-start;margin-bottom:0.5rem">
            <img src="<?php echo esc_url( $thumb_url ); ?>" alt="" style="max-width:240px;border-radius:6px;border:1px solid var(--line)">
            <?php if ( ! $is_new ) : ?>
                <label style="font-weight:normal;display:flex;gap:0.4rem;align-items:center;margin:0">
                    <input type="checkbox" name="remove_image" value="1"> <?php esc_html_e( 'Remove photo', 'cookbook' ); ?>
                </label>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <input id="image" type="file" name="image" accept="image/*">
    <p class="help">
        <?php
        if ( $thumb_url && $is_new ) {
            esc_html_e( 'The source photo will be reused unless you upload a different one.', 'cookbook' );
        } elseif ( $thumb_url ) {
            esc_html_e( 'Upload a new file to replace the current photo.', 'cookbook' );
        } else {
            esc_html_e( 'Optional. Will be added to the media library.', 'cookbook' );
        }
        ?>
    </p>
    <label for="image_url"><?php esc_html_e( 'Photo URL', 'cookbook' ); ?></label>
    <input id="image_url" type="url" name="image_url" placeholder="https://example.com/recipe-photo.jpg">
    <p class="help"><?php esc_html_e( 'Paste an image URL to add it to the media library. If you also upload a file, the uploaded file is used.', 'cookbook' ); ?></p>
    <div id="image-url-preview" hidden style="margin-top:0.5rem">
        <img src="" alt="" style="max-width:240px;border-radius:6px;border:1px solid var(--line)">
    </div>

    <label for="description"><?php esc_html_e( 'Short description', 'cookbook' ); ?></label>
    <textarea id="description" name="description" style="min-height:4rem"><?php echo esc_textarea( $content ); ?></textarea>

    <label for="parent_id"><?php esc_html_e( 'Variation of', 'cookbook' ); ?></label>
    <select id="parent_id" name="parent_id">
        <option value="0"><?php esc_html_e( 'Standalone recipe', 'cookbook' ); ?></option>
        <?php foreach ( $variation_parent_options as $candidate ) : ?>
            <?php
            if ( $save_id && ( (int) $candidate->ID === $save_id || App::recipe_is_descendant_of( (int) $candidate->ID, $save_id ) ) ) {
                continue;
            }
            ?>
            <option value="<?php echo (int) $candidate->ID; ?>" <?php selected( $parent_id, (int) $candidate->ID ); ?>>
                <?php echo esc_html( get_the_title( $candidate ) ); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p class="help"><?php esc_html_e( 'Choose a parent recipe to make this recipe a variation.', 'cookbook' ); ?></p>

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
    <div id="ingredient-sections" class="recipe-form-sections" data-section-root="ingredient">
        <?php foreach ( $ingredient_sections as $section_index => $section ) : ?>
            <section class="recipe-form-section" data-ingredient-section data-section-index="<?php echo (int) $section_index; ?>">
                <div class="recipe-form-section-header">
                    <input type="text" name="ingredient_parts[<?php echo (int) $section_index; ?>][title]" value="<?php echo esc_attr( $section['title'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Section title (optional)', 'cookbook' ); ?>">
                    <button type="button" class="btn secondary remove-section"><?php esc_html_e( 'Remove section', 'cookbook' ); ?></button>
                </div>
                <div class="recipe-form-section-rows" data-ingredient-rows>
                    <?php foreach ( (array) $section['ingredients'] as $i => $row ) : ?>
                        <div class="row">
                            <input type="text" name="ingredient_parts[<?php echo (int) $section_index; ?>][ingredients][<?php echo (int) $i; ?>][amount]" value="<?php echo esc_attr( $row['amount'] ?? '' ); ?>" placeholder="<?php esc_attr_e( '2', 'cookbook' ); ?>">
                            <input type="text" name="ingredient_parts[<?php echo (int) $section_index; ?>][ingredients][<?php echo (int) $i; ?>][unit]" value="<?php echo esc_attr( $row['unit'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'g', 'cookbook' ); ?>" list="recipe-units">
                            <input type="text" name="ingredient_parts[<?php echo (int) $section_index; ?>][ingredients][<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr( $row['name'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'ingredient', 'cookbook' ); ?>" required>
                            <input type="text" name="ingredient_parts[<?php echo (int) $section_index; ?>][ingredients][<?php echo (int) $i; ?>][notes]" value="<?php echo esc_attr( $row['notes'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'chopped', 'cookbook' ); ?>">
                            <button type="button" class="remove" aria-label="<?php esc_attr_e( 'Remove', 'cookbook' ); ?>">×</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn secondary add-ingredient-row"><?php esc_html_e( '+ Add ingredient', 'cookbook' ); ?></button>
            </section>
        <?php endforeach; ?>
    </div>
    <datalist id="recipe-units">
        <?php foreach ( $unit_options as $u ) : ?>
            <option value="<?php echo esc_attr( $u ); ?>"></option>
        <?php endforeach; ?>
    </datalist>
    <button type="button" class="btn secondary" id="add-ingredient-section"><?php esc_html_e( '+ Add ingredient section', 'cookbook' ); ?></button>

    <h2><?php esc_html_e( 'Instructions', 'cookbook' ); ?></h2>
    <div id="instruction-sections" class="recipe-form-sections" data-section-root="instruction">
        <?php foreach ( $instruction_sections as $section_index => $section ) : ?>
            <section class="recipe-form-section" data-instruction-section data-section-index="<?php echo (int) $section_index; ?>">
                <div class="recipe-form-section-header">
                    <input type="text" name="instruction_parts[<?php echo (int) $section_index; ?>][title]" value="<?php echo esc_attr( $section['title'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Section title (optional)', 'cookbook' ); ?>">
                    <button type="button" class="btn secondary remove-section"><?php esc_html_e( 'Remove section', 'cookbook' ); ?></button>
                </div>
                <div class="recipe-form-section-rows" data-instruction-rows>
                    <?php foreach ( (array) $section['instructions'] as $i => $step ) : ?>
                        <div class="row" style="grid-template-columns: 1fr auto; align-items: flex-start">
                            <textarea name="instruction_parts[<?php echo (int) $section_index; ?>][instructions][]" placeholder="<?php
                                /* translators: %d: step number */
                                echo esc_attr( sprintf( __( 'Step %d', 'cookbook' ), (int) $i + 1 ) );
                            ?>"><?php echo esc_textarea( $step ); ?></textarea>
                            <button type="button" class="remove" aria-label="<?php esc_attr_e( 'Remove', 'cookbook' ); ?>">×</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn secondary add-instruction-row"><?php esc_html_e( '+ Add step', 'cookbook' ); ?></button>
            </section>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn secondary" id="add-instruction-section"><?php esc_html_e( '+ Add instruction section', 'cookbook' ); ?></button>

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
        <button class="btn" type="submit"><?php echo esc_html( $submit_label ); ?></button>
        <a class="btn secondary" href="<?php echo esc_url( $cancel_url ); ?>"><?php esc_html_e( 'Cancel', 'cookbook' ); ?></a>
    </div>
</form>

<script>
(function () {
    const ingredientRoot = document.getElementById('ingredient-sections');
    const instructionRoot = document.getElementById('instruction-sections');
    const strings = {
        sectionTitle: <?php echo wp_json_encode( __( 'Section title (optional)', 'cookbook' ) ); ?>,
        removeSection: <?php echo wp_json_encode( __( 'Remove section', 'cookbook' ) ); ?>,
        addIngredient: <?php echo wp_json_encode( __( '+ Add ingredient', 'cookbook' ) ); ?>,
        addStep: <?php echo wp_json_encode( __( '+ Add step', 'cookbook' ) ); ?>,
        two: <?php echo wp_json_encode( __( '2', 'cookbook' ) ); ?>,
        gram: <?php echo wp_json_encode( __( 'g', 'cookbook' ) ); ?>,
        ingredient: <?php echo wp_json_encode( __( 'ingredient', 'cookbook' ) ); ?>,
        chopped: <?php echo wp_json_encode( __( 'chopped', 'cookbook' ) ); ?>,
        remove: <?php echo wp_json_encode( __( 'Remove', 'cookbook' ) ); ?>,
        step: <?php echo wp_json_encode( __( 'Step', 'cookbook' ) ); ?>
    };

    function initSectionCounters(root, sectionSelector) {
        if (!root) return;
        let next = 0;
        root.querySelectorAll(sectionSelector).forEach(section => {
            const index = parseInt(section.dataset.sectionIndex, 10);
            if (!isNaN(index)) next = Math.max(next, index + 1);
            const ingredientRows = section.querySelector('[data-ingredient-rows]');
            if (ingredientRows) {
                section.dataset.nextRowIndex = String(ingredientRows.querySelectorAll('.row').length);
            }
        });
        root.dataset.nextSectionIndex = String(next);
    }

    function nextSectionIndex(root) {
        const next = parseInt(root.dataset.nextSectionIndex || '0', 10) || 0;
        root.dataset.nextSectionIndex = String(next + 1);
        return next;
    }

    function nextIngredientRowIndex(section) {
        const next = parseInt(section.dataset.nextRowIndex || '0', 10) || 0;
        section.dataset.nextRowIndex = String(next + 1);
        return next;
    }

    function addIngredientRow(section, focus) {
        const rows = section.querySelector('[data-ingredient-rows]');
        if (!rows) return;
        const sectionIndex = section.dataset.sectionIndex;
        const rowIndex = nextIngredientRowIndex(section);
        const row = document.createElement('div');
        row.className = 'row';
        row.innerHTML = `
            <input type="text" name="ingredient_parts[${sectionIndex}][ingredients][${rowIndex}][amount]" placeholder="${strings.two}">
            <input type="text" name="ingredient_parts[${sectionIndex}][ingredients][${rowIndex}][unit]" placeholder="${strings.gram}" list="recipe-units">
            <input type="text" name="ingredient_parts[${sectionIndex}][ingredients][${rowIndex}][name]" placeholder="${strings.ingredient}" required>
            <input type="text" name="ingredient_parts[${sectionIndex}][ingredients][${rowIndex}][notes]" placeholder="${strings.chopped}">
            <button type="button" class="remove" aria-label="${strings.remove}">×</button>
        `;
        rows.appendChild(row);
        if (focus) row.querySelector('input').focus();
    }

    function addInstructionRow(section, focus) {
        const rows = section.querySelector('[data-instruction-rows]');
        if (!rows) return;
        const sectionIndex = section.dataset.sectionIndex;
        const row = document.createElement('div');
        row.className = 'row';
        row.style.gridTemplateColumns = '1fr auto';
        row.style.alignItems = 'flex-start';
        const num = rows.querySelectorAll('.row').length + 1;
        row.innerHTML = `
            <textarea name="instruction_parts[${sectionIndex}][instructions][]" placeholder="${strings.step} ${num}"></textarea>
            <button type="button" class="remove" aria-label="${strings.remove}">×</button>
        `;
        rows.appendChild(row);
        if (focus) row.querySelector('textarea').focus();
    }

    function addIngredientSection(focus) {
        const index = nextSectionIndex(ingredientRoot);
        const section = document.createElement('section');
        section.className = 'recipe-form-section';
        section.dataset.sectionIndex = String(index);
        section.dataset.nextRowIndex = '0';
        section.setAttribute('data-ingredient-section', '');
        section.innerHTML = `
            <div class="recipe-form-section-header">
                <input type="text" name="ingredient_parts[${index}][title]" placeholder="${strings.sectionTitle}">
                <button type="button" class="btn secondary remove-section">${strings.removeSection}</button>
            </div>
            <div class="recipe-form-section-rows" data-ingredient-rows></div>
            <button type="button" class="btn secondary add-ingredient-row">${strings.addIngredient}</button>
        `;
        ingredientRoot.appendChild(section);
        addIngredientRow(section, false);
        if (focus) section.querySelector('input').focus();
    }

    function addInstructionSection(focus) {
        const index = nextSectionIndex(instructionRoot);
        const section = document.createElement('section');
        section.className = 'recipe-form-section';
        section.dataset.sectionIndex = String(index);
        section.setAttribute('data-instruction-section', '');
        section.innerHTML = `
            <div class="recipe-form-section-header">
                <input type="text" name="instruction_parts[${index}][title]" placeholder="${strings.sectionTitle}">
                <button type="button" class="btn secondary remove-section">${strings.removeSection}</button>
            </div>
            <div class="recipe-form-section-rows" data-instruction-rows></div>
            <button type="button" class="btn secondary add-instruction-row">${strings.addStep}</button>
        `;
        instructionRoot.appendChild(section);
        addInstructionRow(section, false);
        if (focus) section.querySelector('input').focus();
    }

    function clearSection(section) {
        const root = section.parentElement;
        const rows = section.querySelector('.recipe-form-section-rows');
        const title = section.querySelector('.recipe-form-section-header input');
        if (title) title.value = '';
        if (!rows) return;
        Array.from(rows.querySelectorAll('.row')).slice(1).forEach(row => row.remove());
        let first = rows.querySelector('.row');
        if (!first && section.matches('[data-ingredient-section]')) {
            addIngredientRow(section, false);
            first = rows.querySelector('.row');
        } else if (!first && section.matches('[data-instruction-section]')) {
            addInstructionRow(section, false);
            first = rows.querySelector('.row');
        }
        if (first) first.querySelectorAll('input, textarea').forEach(el => { el.value = ''; });
        if (root) {
            const input = section.querySelector('input, textarea');
            if (input) input.focus();
        }
    }

    initSectionCounters(ingredientRoot, '[data-ingredient-section]');
    initSectionCounters(instructionRoot, '[data-instruction-section]');

    const addIngredientSectionButton = document.getElementById('add-ingredient-section');
    if (addIngredientSectionButton) {
        addIngredientSectionButton.addEventListener('click', () => addIngredientSection(true));
    }
    const addInstructionSectionButton = document.getElementById('add-instruction-section');
    if (addInstructionSectionButton) {
        addInstructionSectionButton.addEventListener('click', () => addInstructionSection(true));
    }

    document.addEventListener('click', (e) => {
        if (e.target.classList && e.target.classList.contains('add-ingredient-row')) {
            const section = e.target.closest('[data-ingredient-section]');
            if (section) addIngredientRow(section, true);
            return;
        }
        if (e.target.classList && e.target.classList.contains('add-instruction-row')) {
            const section = e.target.closest('[data-instruction-section]');
            if (section) addInstructionRow(section, true);
            return;
        }
        if (e.target.classList && e.target.classList.contains('remove-section')) {
            const section = e.target.closest('.recipe-form-section');
            const root = section ? section.parentElement : null;
            if (!section || !root) return;
            if (root.querySelectorAll('.recipe-form-section').length > 1) {
                section.remove();
            } else {
                clearSection(section);
            }
            return;
        }
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

    const imageUrl = document.getElementById('image_url');
    const imagePreview = document.getElementById('image-url-preview');
    if (imageUrl && imagePreview) {
        const image = imagePreview.querySelector('img');
        imageUrl.addEventListener('input', () => {
            const url = imageUrl.value.trim();
            if (!url) {
                imagePreview.hidden = true;
                image.removeAttribute('src');
                return;
            }
            image.src = url;
            imagePreview.hidden = false;
        });
        image.addEventListener('error', () => {
            imagePreview.hidden = true;
        });
    }
})();
</script>
