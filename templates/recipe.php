<?php
use Recipes\App;
use Recipes\Importer;
use Recipes\Units;

$id = (int) get_query_var( 'id' );
$post = $id ? get_post( $id ) : null;
if ( ! $post || $post->post_type !== App::POST_TYPE ) {
    status_header( 404 );
    include __DIR__ . '/_header.php';
    echo '<h1>Not found</h1><p>That recipe does not exist.</p>';
    include __DIR__ . '/_footer.php';
    return;
}

$servings_default = max( 1, (int) get_post_meta( $id, App::META_SERVINGS, true ) ?: 4 );
$prep             = (int) get_post_meta( $id, App::META_PREP, true );
$cook             = (int) get_post_meta( $id, App::META_COOK, true );
$ingredients      = (array) get_post_meta( $id, App::META_INGREDIENTS, true );
$instructions     = (array) get_post_meta( $id, App::META_INSTRUCTIONS, true );
$source_url       = (string) get_post_meta( $id, App::META_SOURCE_URL, true );
$notes            = (string) get_post_meta( $id, App::META_NOTES, true );

$preference = isset( $_GET['units'] ) && in_array( $_GET['units'], [ 'metric', 'imperial' ], true )
    ? $_GET['units']
    : App::get_user_unit_preference();

$cats     = wp_get_object_terms( $id, App::TAX_CATEGORY );
$cuisines = wp_get_object_terms( $id, App::TAX_CUISINE );
$tags     = wp_get_object_terms( $id, App::TAX_TAG );

include __DIR__ . '/_header.php';
?>
<a class="badge" href="<?php echo esc_url( home_url( '/recipes/' ) ); ?>">← All recipes</a>
<h1><?php echo esc_html( get_the_title( $post ) ); ?></h1>

<?php if ( has_post_thumbnail( $id ) ) : ?>
    <?php echo get_the_post_thumbnail( $id, 'large', [
        'style' => 'max-width:100%;height:auto;border-radius:8px;margin:0.5rem 0 1rem',
        'alt'   => esc_attr( get_the_title( $post ) ),
    ] ); ?>
<?php endif; ?>

<div class="meta">
    <?php if ( $prep ) : ?><span>Prep: <?php echo (int) $prep; ?> min</span><?php endif; ?>
    <?php if ( $cook ) : ?><span>Cook: <?php echo (int) $cook; ?> min</span><?php endif; ?>
    <?php if ( $source_url ) : ?>
        <span>Source: <a href="<?php echo esc_url( $source_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $source_url, PHP_URL_HOST ) ?: $source_url ); ?></a></span>
    <?php endif; ?>
</div>

<?php if ( ( ! is_wp_error( $cats ) && $cats ) || ( ! is_wp_error( $cuisines ) && $cuisines ) || ( ! is_wp_error( $tags ) && $tags ) ) : ?>
<p style="margin-top:0.75rem">
    <?php foreach ( (array) $cats as $t ) : ?>
        <a class="badge" href="<?php echo esc_url( home_url( '/recipes/category/' . $t->slug ) ); ?>"><?php echo esc_html( $t->name ); ?></a>
    <?php endforeach; ?>
    <?php foreach ( (array) $cuisines as $t ) : ?>
        <span class="badge"><?php echo esc_html( $t->name ); ?></span>
    <?php endforeach; ?>
    <?php foreach ( (array) $tags as $t ) : ?>
        <a class="badge" href="<?php echo esc_url( home_url( '/recipes/tag/' . $t->slug ) ); ?>">#<?php echo esc_html( $t->name ); ?></a>
    <?php endforeach; ?>
</p>
<?php endif; ?>

<div class="toolbar">
    <div class="portion-control">
        <label for="servings" style="margin:0">Servings:</label>
        <input id="servings" type="number" min="1" step="1" value="<?php echo (int) $servings_default; ?>" data-default="<?php echo (int) $servings_default; ?>">
    </div>
    <div class="unit-toggle" role="tablist" aria-label="Unit system">
        <button type="button" class="<?php echo $preference === 'metric' ? 'active' : ''; ?>" data-units="metric">Metric</button>
        <button type="button" class="<?php echo $preference === 'imperial' ? 'active' : ''; ?>" data-units="imperial">Imperial</button>
    </div>
    <span class="spacer"></span>
    <a class="btn secondary" href="<?php echo esc_url( home_url( '/recipes/recipe/' . $id . '/edit' ) ); ?>">Edit</a>
</div>

<?php if ( $post->post_content ) : ?>
    <div class="description"><?php echo wp_kses_post( wpautop( $post->post_content ) ); ?></div>
<?php endif; ?>

<h2>Ingredients</h2>
<?php if ( ! $ingredients ) : ?>
    <p class="help">No ingredients yet. <a href="<?php echo esc_url( home_url( '/recipes/recipe/' . $id . '/edit' ) ); ?>">Add some</a>.</p>
<?php else : ?>
<ul class="ingredient-list" id="ingredients">
    <?php foreach ( $ingredients as $ing ) :
        $rendered = Units::render_ingredient( $ing, 1.0, $preference );
        $raw_amount = isset( $ing['amount'] ) ? $ing['amount'] : '';
        $parsed_amount = Units::parse_amount( $raw_amount );
        ?>
        <li
            data-amount="<?php echo esc_attr( $parsed_amount ?? '' ); ?>"
            data-amount-raw="<?php echo esc_attr( $raw_amount ); ?>"
            data-unit="<?php echo esc_attr( Units::normalize_unit( $ing['unit'] ?? '' ) ); ?>"
        >
            <span class="amt"><?php
                echo esc_html( trim( $rendered['amount'] . ' ' . $rendered['unit'] ) );
            ?></span>
            <span>
                <?php echo esc_html( $rendered['name'] ); ?>
                <?php if ( ! empty( $rendered['notes'] ) ) : ?>
                    <span style="color:#888"> – <?php echo esc_html( $rendered['notes'] ); ?></span>
                <?php endif; ?>
            </span>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<h2>Instructions</h2>
<?php if ( ! $instructions ) : ?>
    <p class="help">No instructions yet.</p>
<?php else : ?>
<ol class="instruction-list">
    <?php foreach ( $instructions as $step ) :
        $step = Importer::clean_step( (string) $step );
        if ( $step === '' ) continue;
        ?>
        <li><?php echo wp_kses_post( $step ); ?></li>
    <?php endforeach; ?>
</ol>
<?php endif; ?>

<?php if ( $notes ) : ?>
    <h2>Notes</h2>
    <div><?php echo wp_kses_post( wpautop( $notes ) ); ?></div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:2rem" onsubmit="return confirm('Move this recipe to trash?')">
    <?php wp_nonce_field( 'recipes_delete' ); ?>
    <input type="hidden" name="action" value="recipes_delete">
    <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
    <button class="btn danger" type="submit">Delete recipe</button>
</form>

<script>
(function () {
    const ingredients = document.getElementById('ingredients');
    const servingsInput = document.getElementById('servings');
    const unitButtons = document.querySelectorAll('.unit-toggle button');
    if (!ingredients || !servingsInput) return;

    const baseServings = parseInt(servingsInput.dataset.default, 10) || 1;
    let preference = '<?php echo esc_js( $preference ); ?>';

    // Conversion tables (kept in sync with src/Units.php).
    const MASS = { g:1, kg:1000, oz:28.3495, lb:453.592 };
    const VOLUME = { ml:1, l:1000, tsp:4.92892, tbsp:14.7868, floz:29.5735, cup:236.588, pt:473.176, qt:946.353, gal:3785.41 };
    const IMPERIAL = ['oz','lb','tsp','tbsp','cup','floz','pt','qt','gal'];
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

    function rerender() {
        const wanted = Math.max(1, parseInt(servingsInput.value, 10) || baseServings);
        const scale = wanted / baseServings;
        ingredients.querySelectorAll('li').forEach(li => {
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
    }

    servingsInput.addEventListener('input', rerender);
    unitButtons.forEach(btn => btn.addEventListener('click', () => {
        preference = btn.dataset.units;
        unitButtons.forEach(b => b.classList.toggle('active', b === btn));
        rerender();
    }));
})();
</script>

<?php include __DIR__ . '/_footer.php'; ?>
