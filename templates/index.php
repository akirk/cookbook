<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- variables here are template-local, not actually global.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
use Cookbook\App;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- idempotent read-only search.
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

// If the user pasted a URL into the search box, redirect to the import flow.
if ( $search !== '' && preg_match( '~^https?://~i', $search ) && filter_var( $search, FILTER_VALIDATE_URL ) ) {
    wp_safe_redirect( add_query_arg( [
        'source_url' => $search,
        'autoimport' => 1,
    ], home_url( '/cookbook/import' ) ) );
    exit;
}

$is_searching = $search !== '';

$recipes = get_posts( [
    'post_type'      => App::POST_TYPE,
    'post_status'    => [ 'publish', 'draft' ],
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
] );

if ( $is_searching ) {
    $needle = mb_strtolower( $search );
    $recipes = array_values( array_filter( $recipes, function( $recipe ) use ( $needle ) {
        $haystack = implode( "\n", [
            get_the_title( $recipe ),
            $recipe->post_content,
            $recipe->post_excerpt,
            (string) get_post_meta( $recipe->ID, App::META_NOTES, true ),
        ] );
        return mb_strpos( mb_strtolower( wp_strip_all_tags( $haystack ) ), $needle ) !== false;
    } ) );
}

$categories = get_terms( [
    'taxonomy'   => App::TAX_CATEGORY,
    'hide_empty' => true,
] );

$shopping_items_count = 0;
$shopping_list_id = App::get_current_user_shopping_list_id( false );
if ( $shopping_list_id ) {
    $shopping_items_count = count( App::get_shopping_items( $shopping_list_id ) );
}

$planned_meals_count = 0;
$todays_plan = [];
$today_date = wp_date( 'Y-m-d' );
$today_label = wp_date( get_option( 'date_format' ) );
$current_week_start = App::normalize_week_start( $today_date );
$current_plan_id = App::get_user_week_plan_id( $current_week_start, false );
if ( $current_plan_id ) {
    $current_week_meals = App::get_week_meals( $current_plan_id );
    foreach ( $current_week_meals as $day_meals ) {
        if ( is_array( $day_meals ) ) {
            $planned_meals_count += count( array_filter( $day_meals ) );
        }
    }

    $todays_meals = isset( $current_week_meals[ $today_date ] ) && is_array( $current_week_meals[ $today_date ] )
        ? $current_week_meals[ $today_date ]
        : [];
    foreach ( App::meal_slots() as $slot => $slot_label ) {
        $recipe_id = isset( $todays_meals[ $slot ] ) ? absint( $todays_meals[ $slot ] ) : 0;
        $recipe = $recipe_id ? get_post( $recipe_id ) : null;
        if ( $recipe && $recipe->post_type === App::POST_TYPE ) {
            $todays_plan[] = [
                'slot_label' => $slot_label,
                'recipe'     => $recipe,
            ];
        }
    }
}

$recipes_by_letter = [];
foreach ( $recipes as $recipe ) {
    $letter = mb_strtoupper( mb_substr( trim( get_the_title( $recipe ) ), 0, 1 ) );
    if ( $letter === '' || ! preg_match( '/[[:alpha:]]/u', $letter ) ) {
        $letter = '#';
    }
    if ( ! isset( $recipes_by_letter[ $letter ] ) ) {
        $recipes_by_letter[ $letter ] = [];
    }
    $recipes_by_letter[ $letter ][] = $recipe;
}
uksort( $recipes_by_letter, function( $a, $b ) {
    if ( $a === '#' ) return 1;
    if ( $b === '#' ) return -1;
    return strcasecmp( $a, $b );
} );

$page_title = __( 'Cookbook', 'cookbook' );
include __DIR__ . '/_header.php';
?>
<style>
    .recipe-index { display: flex; flex-wrap: wrap; gap: 0.35rem; margin: 1rem 0; }
    .recipe-index a { min-width: 2rem; text-align: center; }
    .recipe-alpha-section { margin-top: 1.4rem; }
    .recipe-alpha-heading { margin: 0 0 0.4rem; padding-bottom: 0.2rem; border-bottom: 1px solid var(--line); font-size: 1.1rem; }
    .recipe-alpha-list { list-style: none; padding: 0; margin: 0; }
    .recipe-alpha-list li { border-bottom: 1px dashed var(--line); }
    .recipe-alpha-list a { display: flex; gap: 0.5rem; align-items: baseline; padding: 0.38rem 0; text-decoration: none; color: inherit; }
    .recipe-alpha-list a:hover .recipe-title { color: var(--accent); }
    .recipe-alpha-list .recipe-title { flex: 1; min-width: 0; }
    .recipe-alpha-list .meta { font-size: 0.82rem; gap: 0.45rem; }
    .home-search { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 0.5rem; align-items: center; margin: 1rem 0; }
    .home-tools { display: grid; grid-template-columns: 1fr; gap: 0.6rem; margin: 0.75rem 0 1.25rem; }
    .home-tool { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 0.75rem 0.9rem; border: 1px solid var(--line); border-radius: 6px; background: var(--card); color: inherit; text-decoration: none; }
    .home-tool:hover { border-color: var(--accent); }
    .home-tool strong { color: var(--fg); }
    .home-tool span { color: var(--muted); font-size: 0.86rem; white-space: nowrap; }
    .home-today-plan { margin: 1.25rem 0; }
    .home-today-head { display: flex; gap: 0.75rem; align-items: baseline; justify-content: space-between; }
    .home-today-head h2 { margin: 0; }
    .home-today-head a { white-space: nowrap; }
    .home-ingredients[hidden] { display: none; }
    @media (min-width: 720px) { .home-tools { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 520px) { .home-search { grid-template-columns: 1fr; } .home-today-head { display: block; } }
</style>
<div class="page-head">
    <div>
        <h1><?php esc_html_e( 'Cookbook', 'cookbook' ); ?></h1>
        <p class="subtitle"><?php esc_html_e( 'Your personal recipes.', 'cookbook' ); ?></p>
    </div>
    <div class="page-actions">
        <a class="btn fresh" href="<?php echo esc_url( home_url( '/cookbook/new' ) ); ?>"><?php esc_html_e( '+ New recipe', 'cookbook' ); ?></a>
        <a class="btn secondary" id="cookbook-import-link" href="<?php echo esc_url( home_url( '/cookbook/import' ) ); ?>"><?php esc_html_e( 'Import from web', 'cookbook' ); ?></a>
    </div>
</div>

<form method="get" action="" class="home-search">
    <input id="cookbook-search" type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search recipes or paste a URL to import…', 'cookbook' ); ?>">
    <button class="btn" type="submit"><?php esc_html_e( 'Search', 'cookbook' ); ?></button>
</form>
<?php if ( ! $is_searching ) : ?>
    <nav class="home-tools" aria-label="<?php esc_attr_e( 'Cookbook tools', 'cookbook' ); ?>">
        <a class="home-tool" href="<?php echo esc_url( home_url( '/cookbook/shopping-list' ) ); ?>">
            <strong><?php esc_html_e( 'Shopping list', 'cookbook' ); ?></strong>
            <span>
                <?php
                echo esc_html( sprintf(
                    /* translators: %d: number of shopping-list items */
                    _n( '%d item', '%d items', $shopping_items_count, 'cookbook' ),
                    $shopping_items_count
                ) );
                ?>
            </span>
        </a>
        <a class="home-tool" href="<?php echo esc_url( home_url( '/cookbook/planner' ) ); ?>">
            <strong><?php esc_html_e( 'Week planner', 'cookbook' ); ?></strong>
            <span>
                <?php
                echo esc_html( sprintf(
                    /* translators: %d: number of planned meals */
                    _n( '%d meal planned', '%d meals planned', $planned_meals_count, 'cookbook' ),
                    $planned_meals_count
                ) );
                ?>
            </span>
        </a>
        <a class="home-tool" href="<?php echo esc_url( home_url( '/cookbook/by-ingredients' ) ); ?>">
            <strong><?php esc_html_e( 'By ingredients', 'cookbook' ); ?></strong>
            <span id="home-ingredient-count">
                <?php
                esc_html_e( 'Open', 'cookbook' );
                ?>
            </span>
        </a>
    </nav>
    <?php if ( $todays_plan ) : ?>
        <section class="home-today-plan">
            <div class="home-today-head">
                <div>
                    <h2><?php esc_html_e( "Today's plan", 'cookbook' ); ?></h2>
                    <p class="subtitle"><?php echo esc_html( $today_label ); ?></p>
                </div>
                <a class="badge" href="<?php echo esc_url( home_url( '/cookbook/planner' ) ); ?>"><?php esc_html_e( 'Open week planner', 'cookbook' ); ?></a>
            </div>
            <div class="planned-strip">
                <?php foreach ( $todays_plan as $entry ) :
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
                            <span><?php echo esc_html( $entry['slot_label'] ); ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
<script>
(function () {
    var input = document.getElementById('cookbook-search');
    var link  = document.getElementById('cookbook-import-link');
    if ( ! input || ! link ) return;
    link.addEventListener('click', function (e) {
        var v = input.value.trim();
        if ( ! v || ! /^https?:\/\/\S+$/i.test(v) ) return;
        e.preventDefault();
        var u = new URL(link.href, window.location.href);
        u.searchParams.set('source_url', v);
        u.searchParams.set('autoimport', '1');
        window.location.href = u.toString();
    });
})();
</script>

<?php if ( ! is_wp_error( $categories ) && $categories ) : ?>
    <div class="toolbar">
        <strong><?php esc_html_e( 'Categories:', 'cookbook' ); ?></strong>
        <?php foreach ( $categories as $cat ) : ?>
            <a class="badge" href="<?php echo esc_url( home_url( '/cookbook/category/' . $cat->slug ) ); ?>">
                <?php echo esc_html( $cat->name ); ?> (<?php echo (int) $cat->count; ?>)
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ( $is_searching ) : ?>
    <h2 style="margin-top:1.25rem"><?php esc_html_e( 'Search results', 'cookbook' ); ?></h2>
<?php endif; ?>

<?php if ( $recipes_by_letter ) : ?>
    <nav class="recipe-index" aria-label="<?php esc_attr_e( 'Recipe index', 'cookbook' ); ?>">
        <?php foreach ( array_keys( $recipes_by_letter ) as $letter ) : ?>
            <?php $letter_id = $letter === '#' ? 'other' : sanitize_title( $letter ); ?>
            <a class="badge" href="#recipes-<?php echo esc_attr( $letter_id ); ?>"><?php echo esc_html( $letter ); ?></a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<?php if ( ! $recipes ) : ?>
    <?php if ( $is_searching ) : ?>
        <div class="notice"><?php esc_html_e( 'No recipes match your search.', 'cookbook' ); ?></div>
    <?php else : ?>
        <div class="notice">
            <?php
            printf(
                /* translators: 1: link to /cookbook/new, 2: link to /cookbook/import */
                esc_html__( 'No recipes yet. %1$s or %2$s.', 'cookbook' ),
                '<a href="' . esc_url( home_url( '/cookbook/new' ) ) . '">' . esc_html__( 'Create one', 'cookbook' ) . '</a>',
                '<a href="' . esc_url( home_url( '/cookbook/import' ) ) . '">' . esc_html__( 'import from a URL', 'cookbook' ) . '</a>'
            );
            ?>
        </div>
    <?php endif; ?>
<?php else :
    foreach ( $recipes_by_letter as $letter => $group ) : ?>
        <?php $letter_id = $letter === '#' ? 'other' : sanitize_title( $letter ); ?>
        <section class="recipe-alpha-section" id="recipes-<?php echo esc_attr( $letter_id ); ?>">
            <h3 class="recipe-alpha-heading"><?php echo esc_html( $letter ); ?></h3>
            <ul class="recipe-alpha-list">
                <?php foreach ( $group as $r ) :
                    $servings  = (int) get_post_meta( $r->ID, App::META_SERVINGS, true );
                    $prep      = (int) get_post_meta( $r->ID, App::META_PREP, true );
                    $cook      = (int) get_post_meta( $r->ID, App::META_COOK, true );
                    $cui_terms = get_the_terms( $r, App::TAX_CUISINE );
                    $is_draft  = $r->post_status === 'draft';
                    ?>
                    <li>
                        <a href="<?php echo esc_url( home_url( '/cookbook/recipe/' . $r->ID ) ); ?>">
                            <span class="recipe-title">
                                <?php echo esc_html( get_the_title( $r ) ); ?>
                                <?php if ( $is_draft ) : ?><span class="badge"><?php esc_html_e( 'draft', 'cookbook' ); ?></span><?php endif; ?>
                            </span>
                            <span class="meta">
                                <?php if ( $servings ) : ?>
                                    <span>
                                        <?php
                                        /* translators: %d: number of servings */
                                        echo esc_html( sprintf( _n( '%d serving', '%d servings', $servings, 'cookbook' ), $servings ) );
                                        ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ( $prep ) : ?>
                                    <span>
                                        <?php
                                        /* translators: %d: prep time in minutes */
                                        echo esc_html( sprintf( __( 'prep %dm', 'cookbook' ), $prep ) );
                                        ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ( $cook ) : ?>
                                    <span>
                                        <?php
                                        /* translators: %d: cook time in minutes */
                                        echo esc_html( sprintf( __( 'cook %dm', 'cookbook' ), $cook ) );
                                        ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ( ! is_wp_error( $cui_terms ) && $cui_terms ) : ?>
                                    <span><?php echo esc_html( implode( ', ', wp_list_pluck( $cui_terms, 'name' ) ) ); ?></span>
                                <?php endif; ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ( ! $is_searching ) : ?>
    <section class="home-ingredients" id="home-ingredients" hidden>
        <h2 style="margin-top:1.75rem"><?php esc_html_e( 'Browse by ingredient', 'cookbook' ); ?></h2>
        <div class="ingredient-cloud" data-home-ingredient-cloud></div>
    </section>
    <script>
    (function () {
        var config = <?php echo wp_json_encode( [
            'endpoint'      => rest_url( 'cookbook/v1/home-ingredients' ),
            'nonce'         => wp_create_nonce( 'wp_rest' ),
            'fallbackLabel' => __( 'Open', 'cookbook' ),
        ] ); ?>;
        var countEl = document.getElementById('home-ingredient-count');
        var section = document.getElementById('home-ingredients');
        var cloud = section ? section.querySelector('[data-home-ingredient-cloud]') : null;

        if ( ! window.fetch ) return;

        fetch(config.endpoint, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-WP-Nonce': config.nonce
            }
        }).then(function (response) {
            if ( ! response.ok ) throw new Error('Ingredient request failed');
            return response.json();
        }).then(function (data) {
            if (countEl) {
                countEl.textContent = data.count_label || config.fallbackLabel;
            }
            if ( ! section || ! cloud || ! Array.isArray(data.terms) || ! data.terms.length ) {
                return;
            }

            data.terms.forEach(function (term) {
                var link = document.createElement('a');
                link.className = 'ing-chip';
                link.href = term.url;
                link.style.fontSize = term.font_size + 'rem';

                var name = document.createElement('span');
                name.textContent = term.name;
                link.appendChild(name);

                var count = document.createElement('span');
                count.className = 'ing-chip-count';
                count.textContent = term.count;
                link.appendChild(count);

                cloud.appendChild(link);
            });

            var all = document.createElement('a');
            all.className = 'ing-chip';
            all.href = data.all_url;
            all.style.fontSize = '0.85rem';
            all.textContent = data.all_label;
            cloud.appendChild(all);

            section.hidden = false;
        }).catch(function () {
            if (countEl) countEl.textContent = config.fallbackLabel;
        });
    })();
    </script>
<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>
