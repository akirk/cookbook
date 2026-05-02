<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- variables here are template-local, not actually global.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
use Recipes\App;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- idempotent read-only search.
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

// If the user pasted a URL into the search box, redirect to the import flow.
if ( $search !== '' && preg_match( '~^https?://~i', $search ) && filter_var( $search, FILTER_VALIDATE_URL ) ) {
    wp_safe_redirect( add_query_arg( [
        'source_url' => $search,
        'autoimport' => 1,
    ], home_url( '/recipes/import' ) ) );
    exit;
}

$is_searching = $search !== '';

if ( $is_searching ) {
    $recipes = get_posts( [
        'post_type'      => App::POST_TYPE,
        'post_status'    => [ 'publish', 'draft' ],
        'posts_per_page' => 50,
        'orderby'        => 'date',
        'order'          => 'DESC',
        's'              => $search,
    ] );
} else {
    $recipes = get_posts( [
        'post_type'      => App::POST_TYPE,
        'post_status'    => [ 'publish', 'draft' ],
        'posts_per_page' => 1,
        'orderby'        => 'rand',
    ] );
}

$total_recipes = (int) wp_count_posts( App::POST_TYPE )->publish + (int) wp_count_posts( App::POST_TYPE )->draft;

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

include __DIR__ . '/_header.php';
?>
<h1><?php esc_html_e( 'Recipes', 'recipes' ); ?></h1>
<p class="subtitle"><?php esc_html_e( 'Your personal cookbook.', 'recipes' ); ?></p>

<form method="get" action="" class="toolbar">
    <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search recipes or paste a URL to import…', 'recipes' ); ?>">
    <button class="btn" type="submit"><?php esc_html_e( 'Search', 'recipes' ); ?></button>
    <span class="spacer"></span>
    <a class="btn secondary" href="<?php echo esc_url( home_url( '/recipes/by-ingredients' ) ); ?>"><?php esc_html_e( 'By ingredients', 'recipes' ); ?></a>
    <a class="btn" href="<?php echo esc_url( home_url( '/recipes/new' ) ); ?>"><?php esc_html_e( '+ New recipe', 'recipes' ); ?></a>
    <a class="btn secondary" href="<?php echo esc_url( home_url( '/recipes/import' ) ); ?>"><?php esc_html_e( 'Import from web', 'recipes' ); ?></a>
</form>

<?php if ( ! is_wp_error( $categories ) && $categories ) : ?>
    <div class="toolbar">
        <strong><?php esc_html_e( 'Categories:', 'recipes' ); ?></strong>
        <?php foreach ( $categories as $cat ) : ?>
            <a class="badge" href="<?php echo esc_url( home_url( '/recipes/category/' . $cat->slug ) ); ?>">
                <?php echo esc_html( $cat->name ); ?> (<?php echo (int) $cat->count; ?>)
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ( $top_ingredients ) : ?>
    <h2 style="margin-top:1.25rem"><?php esc_html_e( 'Browse by ingredient', 'recipes' ); ?></h2>
    <div class="ingredient-cloud">
        <?php foreach ( $top_ingredients as $t ) :
            $weight = $top_ingredient_max > 0 ? sqrt( (int) $t->count / $top_ingredient_max ) : 0;
            $size   = 0.85 + $weight * 0.6;
            $href   = add_query_arg( [ 'have' => [ (int) $t->term_id ] ], home_url( '/recipes/by-ingredients' ) );
            ?>
            <a class="ing-chip" href="<?php echo esc_url( $href ); ?>" style="font-size:<?php echo esc_attr( number_format( $size, 2, '.', '' ) ); ?>rem">
                <span><?php echo esc_html( $t->name ); ?></span>
                <span class="ing-chip-count"><?php echo (int) $t->count; ?></span>
            </a>
        <?php endforeach; ?>
        <a class="ing-chip" href="<?php echo esc_url( home_url( '/recipes/by-ingredients' ) ); ?>" style="font-size:0.85rem"><?php esc_html_e( 'all ingredients →', 'recipes' ); ?></a>
    </div>
<?php endif; ?>

<?php if ( ! $recipes ) : ?>
    <?php if ( $is_searching ) : ?>
        <div class="notice"><?php esc_html_e( 'No recipes match your search.', 'recipes' ); ?></div>
    <?php else : ?>
        <div class="notice">
            <?php
            printf(
                /* translators: 1: link to /recipes/new, 2: link to /recipes/import */
                esc_html__( 'No recipes yet. %1$s or %2$s.', 'recipes' ),
                '<a href="' . esc_url( home_url( '/recipes/new' ) ) . '">' . esc_html__( 'Create one', 'recipes' ) . '</a>',
                '<a href="' . esc_url( home_url( '/recipes/import' ) ) . '">' . esc_html__( 'import from a URL', 'recipes' ) . '</a>'
            );
            ?>
        </div>
    <?php endif; ?>
<?php elseif ( $is_searching ) : ?>
    <div class="grid">
    <?php foreach ( $recipes as $r ) :
        $servings = (int) get_post_meta( $r->ID, App::META_SERVINGS, true );
        $prep     = (int) get_post_meta( $r->ID, App::META_PREP, true );
        $cook     = (int) get_post_meta( $r->ID, App::META_COOK, true );
        $cui_terms = wp_get_object_terms( $r->ID, App::TAX_CUISINE );
        $is_draft = $r->post_status === 'draft';
        ?>
        <a class="recipe-card" href="<?php echo esc_url( home_url( '/recipes/recipe/' . $r->ID ) ); ?>" style="<?php echo has_post_thumbnail( $r->ID ) ? 'display:flex;gap:0.9rem;align-items:flex-start' : ''; ?>">
            <?php if ( has_post_thumbnail( $r->ID ) ) : ?>
                <?php echo get_the_post_thumbnail( $r->ID, 'thumbnail', [
                    'style' => 'width:80px;height:80px;object-fit:cover;border-radius:6px;flex-shrink:0',
                    'alt'   => '',
                ] ); ?>
                <div style="flex:1;min-width:0">
            <?php endif; ?>
            <h3><?php echo esc_html( get_the_title( $r ) ); ?>
                <?php if ( $is_draft ) : ?><span class="badge"><?php esc_html_e( 'draft', 'recipes' ); ?></span><?php endif; ?>
            </h3>
            <div class="meta">
                <?php if ( $servings ) : ?>
                    <span>
                        <?php
                        /* translators: %d: number of servings */
                        echo esc_html( sprintf( _n( '%d serving', '%d servings', $servings, 'recipes' ), $servings ) );
                        ?>
                    </span>
                <?php endif; ?>
                <?php if ( $prep ) : ?>
                    <span>
                        <?php
                        /* translators: %d: prep time in minutes */
                        echo esc_html( sprintf( __( 'prep %dm', 'recipes' ), $prep ) );
                        ?>
                    </span>
                <?php endif; ?>
                <?php if ( $cook ) : ?>
                    <span>
                        <?php
                        /* translators: %d: cook time in minutes */
                        echo esc_html( sprintf( __( 'cook %dm', 'recipes' ), $cook ) );
                        ?>
                    </span>
                <?php endif; ?>
                <?php if ( ! is_wp_error( $cui_terms ) && $cui_terms ) : ?>
                    <span>
                        <?php echo esc_html( implode( ', ', wp_list_pluck( $cui_terms, 'name' ) ) ); ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php if ( $r->post_excerpt ) : ?>
                <p style="margin:0.5rem 0 0;color:var(--muted)"><?php echo esc_html( $r->post_excerpt ); ?></p>
            <?php endif; ?>
            <?php if ( has_post_thumbnail( $r->ID ) ) : ?>
                </div>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
    </div>
<?php else :
    // Random pick — single hero card.
    $r = $recipes[0];
    $servings  = (int) get_post_meta( $r->ID, App::META_SERVINGS, true );
    $prep      = (int) get_post_meta( $r->ID, App::META_PREP, true );
    $cook      = (int) get_post_meta( $r->ID, App::META_COOK, true );
    $cui_terms = wp_get_object_terms( $r->ID, App::TAX_CUISINE );
    $is_draft  = $r->post_status === 'draft';
    ?>
    <p class="subtitle" style="margin-top:1.5rem">
        <?php
        /* translators: %d: total number of recipes in the cookbook */
        echo esc_html( sprintf( __( 'How about this one? (1 of %d)', 'recipes' ), $total_recipes ) );
        ?>
    </p>
    <a class="recipe-card hero" href="<?php echo esc_url( home_url( '/recipes/recipe/' . $r->ID ) ); ?>">
        <?php if ( has_post_thumbnail( $r->ID ) ) : ?>
            <?php echo get_the_post_thumbnail( $r->ID, 'large', [
                'style' => 'display:block;width:100%;max-height:360px;object-fit:cover;border-radius:6px;margin:0 0 0.75rem',
                'alt'   => '',
            ] ); ?>
        <?php endif; ?>
        <h3 style="font-size:1.5rem"><?php echo esc_html( get_the_title( $r ) ); ?>
            <?php if ( $is_draft ) : ?><span class="badge"><?php esc_html_e( 'draft', 'recipes' ); ?></span><?php endif; ?>
        </h3>
        <div class="meta">
            <?php if ( $servings ) : ?>
                <span>
                    <?php
                    /* translators: %d: number of servings */
                    echo esc_html( sprintf( _n( '%d serving', '%d servings', $servings, 'recipes' ), $servings ) );
                    ?>
                </span>
            <?php endif; ?>
            <?php if ( $prep ) : ?>
                <span>
                    <?php
                    /* translators: %d: prep time in minutes */
                    echo esc_html( sprintf( __( 'prep %dm', 'recipes' ), $prep ) );
                    ?>
                </span>
            <?php endif; ?>
            <?php if ( $cook ) : ?>
                <span>
                    <?php
                    /* translators: %d: cook time in minutes */
                    echo esc_html( sprintf( __( 'cook %dm', 'recipes' ), $cook ) );
                    ?>
                </span>
            <?php endif; ?>
            <?php if ( ! is_wp_error( $cui_terms ) && $cui_terms ) : ?>
                <span><?php echo esc_html( implode( ', ', wp_list_pluck( $cui_terms, 'name' ) ) ); ?></span>
            <?php endif; ?>
        </div>
        <?php if ( $r->post_excerpt ) : ?>
            <p style="margin:0.6rem 0 0;color:var(--muted)"><?php echo esc_html( $r->post_excerpt ); ?></p>
        <?php endif; ?>
    </a>
    <div class="toolbar" style="justify-content:center;margin-top:1rem">
        <a class="btn secondary" href="<?php echo esc_url( add_query_arg( 'r', wp_rand( 1, PHP_INT_MAX ), home_url( '/recipes/' ) ) ); ?>"><?php esc_html_e( 'Pick another', 'recipes' ); ?></a>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>
