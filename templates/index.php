<?php
use Recipes\App;

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
<h1>Recipes</h1>
<p class="subtitle">Your personal cookbook.</p>

<form method="get" action="" class="toolbar">
    <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search recipes…">
    <button class="btn" type="submit">Search</button>
    <span class="spacer"></span>
    <a class="btn" href="<?php echo esc_url( home_url( '/recipes/new' ) ); ?>">+ New recipe</a>
    <a class="btn secondary" href="<?php echo esc_url( home_url( '/recipes/import' ) ); ?>">Import from web</a>
</form>

<?php if ( ! is_wp_error( $categories ) && $categories ) : ?>
    <div class="toolbar">
        <strong>Categories:</strong>
        <?php foreach ( $categories as $cat ) : ?>
            <a class="badge" href="<?php echo esc_url( home_url( '/recipes/category/' . $cat->slug ) ); ?>">
                <?php echo esc_html( $cat->name ); ?> (<?php echo (int) $cat->count; ?>)
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ( ! $recipes ) : ?>
    <div class="notice">
        No recipes yet.
        <a href="<?php echo esc_url( home_url( '/recipes/new' ) ); ?>">Create one</a>
        or
        <a href="<?php echo esc_url( home_url( '/recipes/import' ) ); ?>">import from a URL</a>.
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
                <?php if ( $is_draft ) : ?><span class="badge">draft</span><?php endif; ?>
            </h3>
            <div class="meta">
                <?php if ( $servings ) : ?><span><?php echo (int) $servings; ?> servings</span><?php endif; ?>
                <?php if ( $prep ) : ?><span>prep <?php echo (int) $prep; ?>m</span><?php endif; ?>
                <?php if ( $cook ) : ?><span>cook <?php echo (int) $cook; ?>m</span><?php endif; ?>
                <?php if ( ! is_wp_error( $cui_terms ) && $cui_terms ) : ?>
                    <span>
                        <?php echo esc_html( implode( ', ', wp_list_pluck( $cui_terms, 'name' ) ) ); ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php if ( $r->post_excerpt ) : ?>
                <p style="margin:0.5rem 0 0;color:#555"><?php echo esc_html( $r->post_excerpt ); ?></p>
            <?php endif; ?>
            <?php if ( has_post_thumbnail( $r->ID ) ) : ?>
                </div>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>
