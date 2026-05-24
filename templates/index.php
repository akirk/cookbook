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
    $existing_recipe = App::find_recipe_by_source_url( $search );
    if ( $existing_recipe ) {
        wp_safe_redirect( home_url( '/cookbook/recipe/' . $existing_recipe->ID ) );
        exit;
    }

    wp_safe_redirect( add_query_arg( [
        'source_url' => $search,
        'autoimport' => 1,
    ], home_url( '/cookbook/import' ) ) );
    exit;
}

$is_searching = $search !== '';

$recipes = get_posts( [
    'post_type'      => App::POST_TYPE,
    'post_status'    => 'publish',
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

$todays_plan = [];
$today_date = wp_date( 'Y-m-d' );
$today_label = wp_date( get_option( 'date_format' ) );
$current_week_start = App::normalize_week_start( $today_date );
$current_plan_id = App::get_user_week_plan_id( $current_week_start, false );
if ( $current_plan_id ) {
    $current_week_meals = App::get_week_meals( $current_plan_id );

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
    .recipe-index-row { display: flex; gap: 0.75rem; align-items: center; justify-content: space-between; flex-wrap: wrap; margin: 1rem 0; }
    .recipe-index { display: flex; flex-wrap: wrap; gap: 0.35rem; margin: 0; }
    .recipe-index .badge { min-width: 2rem; box-sizing: border-box; text-align: center; }
    .recipe-index .badge.muted { background: transparent; color: var(--muted); opacity: 0.45; }
    .recipe-index-row .btn { margin-left: auto; white-space: nowrap; }
    .recipe-alpha-section { margin-top: 1.4rem; }
    .recipe-alpha-heading { margin: 0 0 0.4rem; padding-bottom: 0.2rem; border-bottom: 1px solid var(--line); font-size: 1.1rem; }
    .recipe-alpha-list { list-style: none; padding: 0; margin: 0; }
    .recipe-alpha-list li { border-bottom: 1px dashed var(--line); }
    .recipe-alpha-list a { display: grid; gap: 0.12rem; padding: 0.38rem 0; text-decoration: none; color: inherit; }
    .recipe-alpha-list a:hover .recipe-title { color: var(--accent); }
    .recipe-alpha-list .recipe-title { min-width: 0; }
    .recipe-alpha-list .meta { font-size: 0.82rem; gap: 0.45rem; }
    .home-search { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 0.5rem; align-items: center; margin: 1rem 0; }
    .home-today-plan { margin: 1.25rem 0; }
    .home-today-head { display: flex; gap: 0.75rem; align-items: baseline; justify-content: space-between; }
    .home-today-head h2 { margin: 0; }
    .home-today-head a { white-space: nowrap; }
    .home-ingredients[hidden] { display: none; }
    @media (max-width: 520px) { .home-search { grid-template-columns: 1fr; } .home-today-head { display: block; } .recipe-index-row { align-items: stretch; } .recipe-index-row .btn { margin-left: 0; } }
</style>
<?php cookbook_page_head( __( 'Cookbook', 'cookbook' ), [ 'current_section' => 'recipes' ] ); ?>

<form method="get" action="" class="home-search">
    <input id="cookbook-search" type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search recipes or paste a URL to import…', 'cookbook' ); ?>">
    <button class="btn" type="submit"><?php esc_html_e( 'Search', 'cookbook' ); ?></button>
</form>
<?php if ( ! $is_searching ) : ?>
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

<div class="recipe-index-row">
    <?php if ( $recipes_by_letter ) : ?>
        <nav class="recipe-index" aria-label="<?php esc_attr_e( 'Recipe index', 'cookbook' ); ?>">
            <?php foreach ( range( 'A', 'Z' ) as $letter ) : ?>
                <?php $letter_id = sanitize_title( $letter ); ?>
                <?php if ( isset( $recipes_by_letter[ $letter ] ) ) : ?>
                    <a class="badge" href="#recipes-<?php echo esc_attr( $letter_id ); ?>"><?php echo esc_html( $letter ); ?></a>
                <?php else : ?>
                    <span class="badge muted" aria-disabled="true"><?php echo esc_html( $letter ); ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ( isset( $recipes_by_letter['#'] ) ) : ?>
                <a class="badge" href="#recipes-other">#</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
    <a class="btn secondary" href="<?php echo esc_url( home_url( '/cookbook/new' ) ); ?>"><?php esc_html_e( 'New recipe', 'cookbook' ); ?></a>
</div>

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
                    ?>
                    <li>
                        <a href="<?php echo esc_url( home_url( '/cookbook/recipe/' . $r->ID ) ); ?>">
                            <span class="recipe-title">
                                <?php echo esc_html( get_the_title( $r ) ); ?>
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
        ] ); ?>;
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
