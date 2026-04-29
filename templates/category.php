<?php
use Recipes\App;

$slug = (string) get_query_var( 'slug' );
$term = $slug ? get_term_by( 'slug', $slug, App::TAX_CATEGORY ) : null;
if ( ! $term ) {
    status_header( 404 );
    include __DIR__ . '/_header.php';
    echo '<h1>Category not found</h1>';
    include __DIR__ . '/_footer.php';
    return;
}

$recipes = get_posts( [
    'post_type'      => App::POST_TYPE,
    'post_status'    => [ 'publish', 'draft' ],
    'posts_per_page' => 100,
    'tax_query'      => [
        [ 'taxonomy' => App::TAX_CATEGORY, 'field' => 'term_id', 'terms' => $term->term_id ],
    ],
] );

include __DIR__ . '/_header.php';
?>
<a class="badge" href="<?php echo esc_url( home_url( '/recipes/' ) ); ?>">← All recipes</a>
<h1><?php echo esc_html( $term->name ); ?></h1>
<p class="subtitle"><?php echo (int) count( $recipes ); ?> recipe<?php echo count( $recipes ) === 1 ? '' : 's'; ?>.</p>

<?php if ( ! $recipes ) : ?>
    <div class="notice">No recipes in this category yet.</div>
<?php else : ?>
    <div class="grid">
    <?php foreach ( $recipes as $r ) : ?>
        <a class="recipe-card" href="<?php echo esc_url( home_url( '/recipes/recipe/' . $r->ID ) ); ?>">
            <h3><?php echo esc_html( get_the_title( $r ) ); ?></h3>
            <?php if ( $r->post_excerpt ) : ?>
                <p style="margin:0.5rem 0 0;color:#555"><?php echo esc_html( $r->post_excerpt ); ?></p>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/_footer.php'; ?>
