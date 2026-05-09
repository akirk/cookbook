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

$top_ingredients = $is_searching ? [] : get_terms( [
    'taxonomy'   => App::TAX_INGREDIENT,
    'hide_empty' => true,
    'orderby'    => 'count',
    'order'      => 'DESC',
    'number'     => 24,
] );
if ( is_wp_error( $top_ingredients ) ) {
    $top_ingredients = [];
}
$top_ingredient_max = 0;
foreach ( $top_ingredients as $t ) { $top_ingredient_max = max( $top_ingredient_max, (int) $t->count ); }
$ingredient_count = 0;
if ( ! $is_searching ) {
    $ingredient_ids = get_terms( [
        'taxonomy'   => App::TAX_INGREDIENT,
        'hide_empty' => true,
        'fields'     => 'ids',
    ] );
    $ingredient_count = is_wp_error( $ingredient_ids ) ? 0 : count( $ingredient_ids );
}

$shopping_items_count = 0;
$shopping_list_id = App::get_current_user_shopping_list_id( false );
if ( $shopping_list_id ) {
    $shopping_items_count = count( App::get_shopping_items( $shopping_list_id ) );
}

$planned_meals_count = 0;
$current_week_start = App::normalize_week_start();
$current_plan_id = App::get_user_week_plan_id( $current_week_start, false );
if ( $current_plan_id ) {
    foreach ( App::get_week_meals( $current_plan_id ) as $day_meals ) {
        if ( is_array( $day_meals ) ) {
            $planned_meals_count += count( array_filter( $day_meals ) );
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
    @media (min-width: 720px) { .home-tools { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 520px) { .home-search { grid-template-columns: 1fr; } }
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
            <span>
                <?php
                echo esc_html( sprintf(
                    /* translators: %d: number of ingredients */
                    _n( '%d ingredient', '%d ingredients', $ingredient_count, 'cookbook' ),
                    $ingredient_count
                ) );
                ?>
            </span>
        </a>
    </nav>
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
                    $cui_terms = wp_get_object_terms( $r->ID, App::TAX_CUISINE );
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

<?php if ( $top_ingredients ) : ?>
    <h2 style="margin-top:1.75rem"><?php esc_html_e( 'Browse by ingredient', 'cookbook' ); ?></h2>
    <div class="ingredient-cloud">
        <?php foreach ( $top_ingredients as $t ) :
            $weight = $top_ingredient_max > 0 ? sqrt( (int) $t->count / $top_ingredient_max ) : 0;
            $size   = 0.85 + $weight * 0.6;
            $href   = add_query_arg( [ 'have' => [ (int) $t->term_id ] ], home_url( '/cookbook/by-ingredients' ) );
            ?>
            <a class="ing-chip" href="<?php echo esc_url( $href ); ?>" style="font-size:<?php echo esc_attr( number_format( $size, 2, '.', '' ) ); ?>rem">
                <span><?php echo esc_html( $t->name ); ?></span>
                <span class="ing-chip-count"><?php echo (int) $t->count; ?></span>
            </a>
        <?php endforeach; ?>
        <a class="ing-chip" href="<?php echo esc_url( home_url( '/cookbook/by-ingredients' ) ); ?>" style="font-size:0.85rem"><?php esc_html_e( 'all ingredients →', 'cookbook' ); ?></a>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>
