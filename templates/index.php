<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- variables here are template-local, not actually global.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
use Cookbook\App;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- idempotent read-only search.
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- idempotent read-only display preference.
$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'compact';
if ( ! in_array( $view, [ 'images', 'compact', 'recent' ], true ) ) {
    $view = 'compact';
}

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
$image_recipes = array_values( array_filter( $recipes, function( $recipe ) {
    return has_post_thumbnail( $recipe->ID );
} ) );

$last_cooked_by_recipe = [];
if ( $view === 'recent' && $recipes ) {
    $recipe_ids = array_fill_keys( array_map( 'intval', wp_list_pluck( $recipes, 'ID' ) ), true );
    foreach ( App::get_user_cooked_entries() as $entry ) {
        $recipe_id = (int) get_post_meta( $entry->ID, App::META_COOKED_RECIPE_ID, true );
        if ( ! $recipe_id || ! isset( $recipe_ids[ $recipe_id ] ) || isset( $last_cooked_by_recipe[ $recipe_id ] ) ) {
            continue;
        }

        $last_cooked_by_recipe[ $recipe_id ] = (string) get_post_meta( $entry->ID, App::META_COOKED_DATE, true );
    }
}

$recent_recipes = [];
if ( $view === 'recent' ) {
    $recent_recipes = $recipes;
    usort( $recent_recipes, function( $a, $b ) use ( $last_cooked_by_recipe ) {
        $a_time = ! empty( $last_cooked_by_recipe[ $a->ID ] ) ? strtotime( $last_cooked_by_recipe[ $a->ID ] ) : strtotime( $a->post_date_gmt ?: $a->post_date );
        $b_time = ! empty( $last_cooked_by_recipe[ $b->ID ] ) ? strtotime( $last_cooked_by_recipe[ $b->ID ] ) : strtotime( $b->post_date_gmt ?: $b->post_date );

        if ( $a_time === $b_time ) {
            return strcasecmp( get_the_title( $a ), get_the_title( $b ) );
        }

        return $b_time <=> $a_time;
    } );
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

$view_query_args = [];
if ( $search !== '' ) {
    $view_query_args['s'] = $search;
}
$images_view_url = add_query_arg( array_merge( $view_query_args, [ 'view' => 'images' ] ), home_url( '/cookbook/' ) );
$compact_view_url = add_query_arg( array_merge( $view_query_args, [ 'view' => 'compact' ] ), home_url( '/cookbook/' ) );
$recent_view_url = add_query_arg( array_merge( $view_query_args, [ 'view' => 'recent' ] ), home_url( '/cookbook/' ) );
$new_recipe_url = $search !== ''
    ? add_query_arg( 'title', $search, home_url( '/cookbook/new' ) )
    : home_url( '/cookbook/new' );

$page_title = __( 'Cookbook', 'cookbook' );
include __DIR__ . '/_header.php';
?>
<style>
    .recipe-index-row { display: flex; gap: 0.75rem; align-items: center; justify-content: space-between; flex-wrap: wrap; margin: 1rem 0; }
    .recipe-index { display: flex; flex-wrap: wrap; gap: 0.35rem; margin: 0; }
    .recipe-index .badge { min-width: 2rem; box-sizing: border-box; text-align: center; }
    .recipe-index .badge.muted { background: transparent; color: var(--muted); opacity: 0.45; }
    .recipe-index-row .btn { margin-left: auto; white-space: nowrap; }
    .recipe-view-switch { display: inline-flex; gap: 0.2rem; flex-shrink: 0; align-items: center; }
    .recipe-view-switch a { display: inline-flex; gap: 0.35rem; align-items: center; justify-content: center; min-height: 2rem; padding: 0 0.65rem; color: var(--muted); text-decoration: none; border-radius: 4px; font-size: 0.9rem; }
    .recipe-view-switch a:hover,
    .recipe-view-switch a:focus { color: var(--fg); background: var(--secondary-bg); }
    .recipe-view-switch a.is-active { color: var(--accent); background: color-mix(in srgb, var(--accent) 12%, transparent); font-weight: 600; }
    .recipe-view-switch a.is-active:hover,
    .recipe-view-switch a.is-active:focus { color: var(--accent); background: color-mix(in srgb, var(--accent) 18%, transparent); }
    .recipe-view-icon { width: 1rem; height: 1rem; flex: 0 0 1rem; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; fill: none; }
    .recipe-alpha-section { margin-top: 1.4rem; }
    .recipe-alpha-heading { margin: 0 0 0.4rem; padding-bottom: 0.2rem; border-bottom: 1px solid var(--line); font-size: 1.1rem; }
    .recipe-alpha-list { list-style: none; padding: 0; margin: 0; }
    .recipe-alpha-list li { border-bottom: 1px dashed var(--line); }
    .recipe-alpha-list a { display: grid; gap: 0.12rem; padding: 0.38rem 0; text-decoration: none; color: inherit; }
    .recipe-alpha-list a:hover .recipe-title { color: var(--accent); }
    .recipe-alpha-list .recipe-title { min-width: 0; }
    .recipe-recent-list { list-style: none; padding: 0; margin: 1rem 0; }
    .recipe-recent-list li { border-bottom: 1px dashed var(--line); }
    .recipe-recent-list a { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 0.75rem; align-items: baseline; padding: 0.5rem 0; color: inherit; text-decoration: none; }
    .recipe-recent-list a:hover .recipe-title { color: var(--accent); }
    .recipe-recent-list .recipe-title { min-width: 0; overflow-wrap: anywhere; }
    .recipe-recent-list time { color: var(--muted); font-size: 0.9rem; white-space: nowrap; }
    .recipe-photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 0.75rem; margin: 1rem 0; }
    .recipe-photo-card { position: relative; display: block; aspect-ratio: 4 / 3; overflow: hidden; border: 1px solid var(--line); border-radius: 6px; background: var(--secondary-bg); color: #fff; text-decoration: none; }
    .recipe-photo-card img { display: block; width: 100%; height: 100%; object-fit: cover; transition: transform 0.12s ease; }
    .recipe-photo-card:hover img,
    .recipe-photo-card:focus img { transform: scale(1.03); }
    .recipe-photo-title { position: absolute; left: 0; right: 0; bottom: 0; padding: 1.8rem 0.75rem 0.65rem; background: linear-gradient(to top, rgba(0,0,0,0.76), transparent); color: #fff; font-weight: 600; line-height: 1.25; overflow-wrap: anywhere; }
    .home-search { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 0.5rem; align-items: center; margin: 1rem 0; }
    .home-today-plan { margin: 1.25rem 0; }
    .home-today-head { display: flex; gap: 0.75rem; align-items: baseline; justify-content: space-between; }
    .home-today-head h2 { margin: 0; }
    .home-today-head a { white-space: nowrap; }
    .home-ingredients[hidden] { display: none; }
    @media (max-width: 520px) { .home-search { grid-template-columns: 1fr; } .home-today-head { display: block; } .recipe-index-row { align-items: stretch; } .recipe-index-row .btn { margin-left: 0; } .recipe-view-switch { width: 100%; } .recipe-view-switch a { flex: 1; } .recipe-recent-list a { grid-template-columns: 1fr; gap: 0.1rem; } }
</style>
<?php cookbook_page_head( __( 'Cookbook', 'cookbook' ), [ 'current_section' => 'recipes' ] ); ?>

<form method="get" action="" class="home-search">
    <input id="cookbook-search" type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search recipes or paste a URL to import…', 'cookbook' ); ?>">
    <input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>">
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
    <?php if ( $view === 'compact' && $recipes_by_letter ) : ?>
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
    <nav class="recipe-view-switch" aria-label="<?php esc_attr_e( 'Recipe view', 'cookbook' ); ?>">
        <a class="<?php echo $view === 'images' ? 'is-active' : ''; ?>" href="<?php echo esc_url( $images_view_url ); ?>"<?php echo $view === 'images' ? ' aria-current="true"' : ''; ?>>
            <svg class="recipe-view-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                <circle cx="8.5" cy="10" r="1.4"></circle>
                <path d="M21 15l-4.5-4.5L10 17l-2.5-2.5L3 19"></path>
            </svg>
            <?php esc_html_e( 'Photos', 'cookbook' ); ?>
        </a>
        <a class="<?php echo $view === 'compact' ? 'is-active' : ''; ?>" href="<?php echo esc_url( $compact_view_url ); ?>"<?php echo $view === 'compact' ? ' aria-current="true"' : ''; ?>>
            <svg class="recipe-view-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M8 6h13"></path>
                <path d="M8 12h13"></path>
                <path d="M8 18h13"></path>
                <path d="M3 6h.01"></path>
                <path d="M3 12h.01"></path>
                <path d="M3 18h.01"></path>
            </svg>
            <?php esc_html_e( 'Compact', 'cookbook' ); ?>
        </a>
        <a class="<?php echo $view === 'recent' ? 'is-active' : ''; ?>" href="<?php echo esc_url( $recent_view_url ); ?>"<?php echo $view === 'recent' ? ' aria-current="true"' : ''; ?>>
            <svg class="recipe-view-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M12 8v5l3 2"></path>
                <path d="M3.05 11a9 9 0 1 1 .5 4"></path>
                <path d="M3 5v6h6"></path>
            </svg>
            <?php esc_html_e( 'Recent', 'cookbook' ); ?>
        </a>
    </nav>
    <a class="btn secondary" href="<?php echo esc_url( home_url( '/cookbook/new' ) ); ?>"><?php esc_html_e( 'New recipe', 'cookbook' ); ?></a>
</div>

<?php if ( ! $recipes ) : ?>
    <?php if ( $is_searching ) : ?>
        <div class="notice">
            <?php
            printf(
                /* translators: 1: search text, 2: link to create a new recipe */
                esc_html__( 'No recipes match "%1$s". %2$s', 'cookbook' ),
                esc_html( $search ),
                '<a href="' . esc_url( $new_recipe_url ) . '">' . esc_html__( 'Create a new recipe with that title.', 'cookbook' ) . '</a>'
            );
            ?>
        </div>
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
    <?php elseif ( $view === 'images' ) : ?>
        <?php if ( ! $image_recipes ) : ?>
            <div class="notice">
                <?php
                echo esc_html(
                    $is_searching
                        ? __( 'No recipes with photos match your search.', 'cookbook' )
                        : __( 'No recipes with photos yet.', 'cookbook' )
                );
                ?>
            </div>
        <?php else : ?>
            <div class="recipe-photo-grid">
                <?php foreach ( $image_recipes as $r ) : ?>
                    <a class="recipe-photo-card" href="<?php echo esc_url( home_url( '/cookbook/recipe/' . $r->ID ) ); ?>">
                        <?php echo get_the_post_thumbnail( $r->ID, 'medium_large', [ 'alt' => '' ] ); ?>
                        <span class="recipe-photo-title"><?php echo esc_html( get_the_title( $r ) ); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php elseif ( $view === 'recent' ) : ?>
        <ul class="recipe-recent-list">
            <?php foreach ( $recent_recipes as $r ) : ?>
                <?php
                $last_cooked = isset( $last_cooked_by_recipe[ $r->ID ] ) ? $last_cooked_by_recipe[ $r->ID ] : '';
                $date_label  = $last_cooked
                    ? sprintf(
                        /* translators: %s: cooked date */
                        __( 'Cooked %s', 'cookbook' ),
                        App::format_cooked_date( $last_cooked )
                    )
                    : sprintf(
                        /* translators: %s: recipe published date */
                        __( 'Added %s', 'cookbook' ),
                        get_the_date( '', $r )
                    );
                $datetime    = $last_cooked ? $last_cooked : get_the_date( 'Y-m-d', $r );
                ?>
                <li>
                    <a href="<?php echo esc_url( home_url( '/cookbook/recipe/' . $r->ID ) ); ?>">
                        <span class="recipe-title"><?php echo esc_html( get_the_title( $r ) ); ?></span>
                        <time datetime="<?php echo esc_attr( $datetime ); ?>"><?php echo esc_html( $date_label ); ?></time>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else :
        foreach ( $recipes_by_letter as $letter => $group ) : ?>
            <?php $letter_id = $letter === '#' ? 'other' : sanitize_title( $letter ); ?>
            <section class="recipe-alpha-section" id="recipes-<?php echo esc_attr( $letter_id ); ?>">
                <h3 class="recipe-alpha-heading"><?php echo esc_html( $letter ); ?></h3>
                <ul class="recipe-alpha-list">
                    <?php foreach ( $group as $r ) : ?>
                        <li>
                            <a href="<?php echo esc_url( home_url( '/cookbook/recipe/' . $r->ID ) ); ?>">
                                <span class="recipe-title">
                                    <?php echo esc_html( get_the_title( $r ) ); ?>
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
