<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- variables here are template-local, not actually global.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
use Recipes\App;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- idempotent read-only search.
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

$query_args = [
    'post_type'      => App::POST_TYPE,
    'post_status'    => [ 'publish', 'draft' ],
    'posts_per_page' => 50,
    'orderby'        => 'date',
    'order'          => 'DESC',
];
if ( $search !== '' ) {
    $query_args['s'] = $search;
}
$recipes = get_posts( $query_args );

$categories = get_terms( [
    'taxonomy'   => App::TAX_CATEGORY,
    'hide_empty' => true,
] );

include __DIR__ . '/_header.php';
?>
<h1><?php esc_html_e( 'Recipes', 'recipes' ); ?></h1>
<p class="subtitle"><?php esc_html_e( 'Your personal cookbook.', 'recipes' ); ?></p>

<form method="get" action="" class="toolbar">
    <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search recipes…', 'recipes' ); ?>">
    <button class="btn" type="submit"><?php esc_html_e( 'Search', 'recipes' ); ?></button>
    <span class="spacer"></span>
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

<?php if ( ! $recipes ) : ?>
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
<?php else : ?>
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
<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>
