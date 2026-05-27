<?php

namespace Cookbook;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RegistryService extends AbstractService {
    public function add_recipe_admin_bar_edit_link( $wp_admin_bar ): void {
        global $wp_app_route;

        if ( empty( $wp_app_route['template'] ) || $wp_app_route['template'] !== 'recipe.php' ) {
            return;
        }

        $id = isset( $wp_app_route['params']['id'] ) ? absint( $wp_app_route['params']['id'] ) : 0;
        $post = $id ? get_post( $id ) : null;
        if ( ! $post || $post->post_type !== App::POST_TYPE || ! current_user_can( 'edit_post', $id ) ) {
            return;
        }

        $wp_admin_bar->add_node( [
            'id'    => 'edit',
            'title' => __( 'Edit recipe', 'cookbook' ),
            'href'  => home_url( '/' . $this->get_url_path() . '/recipe/' . $id . '/edit' ),
            'meta'  => [
                'class' => 'cookbook-edit-recipe',
            ],
        ] );
    }

    public function activate(): void {
        $this->register_post_type();
        $this->register_taxonomies();
        flush_rewrite_rules();
    }

    public function register_post_type(): void {
        register_post_type( App::POST_TYPE, [
            'labels' => [
                'name'               => __( 'Recipes', 'cookbook' ),
                'singular_name'      => __( 'Recipe', 'cookbook' ),
                'add_new'            => __( 'New recipe', 'cookbook' ),
                'add_new_item'       => __( 'Add new recipe', 'cookbook' ),
                'edit_item'          => __( 'Edit recipe', 'cookbook' ),
                'view_item'          => __( 'View recipe', 'cookbook' ),
                'search_items'       => __( 'Search recipes', 'cookbook' ),
                'not_found'          => __( 'No recipes yet', 'cookbook' ),
                'not_found_in_trash' => __( 'No recipes in trash', 'cookbook' ),
            ],
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'menu_icon'          => 'dashicons-carrot',
            'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'revisions' ],
            'has_archive'        => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
        ] );

        register_post_meta( App::POST_TYPE, App::META_SERVINGS, [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => 4,
            'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
        ] );
        register_post_meta( App::POST_TYPE, App::META_PREP, [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
        ] );
        register_post_meta( App::POST_TYPE, App::META_COOK, [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
        ] );
        register_post_meta( App::POST_TYPE, App::META_INGREDIENTS, [
            'type'         => 'array',
            'single'       => true,
            'show_in_rest' => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [ 'type' => 'object' ],
                ],
            ],
            'revisions_enabled' => true,
            'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
        ] );
        register_post_meta( App::POST_TYPE, App::META_INSTRUCTIONS, [
            'type'         => 'array',
            'single'       => true,
            'show_in_rest' => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [ 'type' => 'string' ],
                ],
            ],
            'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
        ] );
        register_post_meta( App::POST_TYPE, App::META_PARTS, [
            'type'         => 'array',
            'single'       => true,
            'show_in_rest' => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [ 'type' => 'object' ],
                ],
            ],
            'revisions_enabled' => true,
            'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
        ] );
        register_post_meta( App::POST_TYPE, App::META_SOURCE_URL, [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
        ] );
        register_post_meta( App::POST_TYPE, App::META_NOTES, [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
        ] );

        register_post_status( App::SHOPPING_ITEM_STATUS_CHECKED, [
            'label'                     => __( 'Checked', 'cookbook' ),
            'public'                    => false,
            'internal'                  => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Checked <span class="count">(%s)</span>', 'Checked <span class="count">(%s)</span>', 'cookbook' ),
        ] );

        register_post_type( App::SHOPPING_LIST_POST_TYPE, [
            'labels' => [
                'name'          => __( 'Shopping lists', 'cookbook' ),
                'singular_name' => __( 'Shopping list', 'cookbook' ),
                'edit_item'     => __( 'Edit shopping list', 'cookbook' ),
                'view_item'     => __( 'View shopping list', 'cookbook' ),
                'not_found'     => __( 'No shopping lists yet', 'cookbook' ),
            ],
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=' . App::POST_TYPE,
            'show_in_rest'       => true,
            'hierarchical'       => true,
            'supports'           => [ 'title', 'author', 'page-attributes' ],
            'has_archive'        => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
        ] );
        register_post_meta( App::SHOPPING_LIST_POST_TYPE, App::META_SHOPPING_ITEMS, [
            'type'         => 'array',
            'single'       => true,
            'show_in_rest' => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [ 'type' => 'object' ],
                ],
            ],
            'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
        ] );
        foreach ( [
            App::META_SHOPPING_ITEM_AMOUNT              => 'string',
            App::META_SHOPPING_ITEM_UNIT                => 'string',
            App::META_SHOPPING_ITEM_NOTES               => 'string',
            App::META_SHOPPING_ITEM_SOURCE_RECIPE_ID    => 'integer',
            App::META_SHOPPING_ITEM_SOURCE_RECIPE_TITLE => 'string',
        ] as $meta_key => $type ) {
            register_post_meta( App::SHOPPING_LIST_POST_TYPE, $meta_key, [
                'type'         => $type,
                'single'       => true,
                'show_in_rest' => true,
                'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
            ] );
        }
        register_post_meta( App::SHOPPING_LIST_POST_TYPE, App::META_SHOPPING_ITEM_SOURCE_RECIPES, [
            'type'         => 'array',
            'single'       => true,
            'show_in_rest' => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [ 'type' => 'object' ],
                ],
            ],
            'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
        ] );

        register_post_type( App::WEEK_PLAN_POST_TYPE, [
            'labels' => [
                'name'          => __( 'Week plans', 'cookbook' ),
                'singular_name' => __( 'Week plan', 'cookbook' ),
                'edit_item'     => __( 'Edit week plan', 'cookbook' ),
                'view_item'     => __( 'View week plan', 'cookbook' ),
                'not_found'     => __( 'No week plans yet', 'cookbook' ),
            ],
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=' . App::POST_TYPE,
            'show_in_rest'       => true,
            'supports'           => [ 'title', 'author', 'revisions' ],
            'has_archive'        => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
        ] );
        register_post_meta( App::WEEK_PLAN_POST_TYPE, App::META_WEEK_START, [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
        ] );
        register_post_meta( App::WEEK_PLAN_POST_TYPE, App::META_WEEK_MEALS, [
            'type'         => 'object',
            'single'       => true,
            'show_in_rest' => [
                'schema' => [
                    'type'  => 'object',
                    'additionalProperties' => [
                        'type'  => 'object',
                        'additionalProperties' => [ 'type' => 'integer' ],
                    ],
                ],
            ],
            'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
        ] );

        register_post_type( App::COOKED_ENTRY_POST_TYPE, [
            'labels' => [
                'name'          => __( 'Cooking history entries', 'cookbook' ),
                'singular_name' => __( 'Cooking history entry', 'cookbook' ),
                'edit_item'     => __( 'Edit cooking history entry', 'cookbook' ),
                'view_item'     => __( 'View cooking history entry', 'cookbook' ),
                'not_found'     => __( 'No cooking history entries yet', 'cookbook' ),
            ],
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=' . App::POST_TYPE,
            'show_in_rest'       => true,
            'supports'           => [ 'title', 'author' ],
            'has_archive'        => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
        ] );
        register_post_meta( App::COOKED_ENTRY_POST_TYPE, App::META_COOKED_RECIPE_ID, [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback' => function() { return is_user_logged_in(); },
        ] );
        register_post_meta( App::COOKED_ENTRY_POST_TYPE, App::META_COOKED_DATE, [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback' => function() { return is_user_logged_in(); },
        ] );
    }

    public function register_taxonomies(): void {
        register_taxonomy( App::TAX_CATEGORY, App::POST_TYPE, [
            'labels' => [
                'name'          => __( 'Categories', 'cookbook' ),
                'singular_name' => __( 'Category', 'cookbook' ),
            ],
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => false,
        ] );
        register_taxonomy( App::TAX_CUISINE, App::POST_TYPE, [
            'labels' => [
                'name'          => __( 'Cuisines', 'cookbook' ),
                'singular_name' => __( 'Cuisine', 'cookbook' ),
            ],
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => false,
        ] );
        register_taxonomy( App::TAX_TAG, App::POST_TYPE, [
            'labels' => [
                'name'          => __( 'Tags', 'cookbook' ),
                'singular_name' => __( 'Tag', 'cookbook' ),
            ],
            'hierarchical'      => false,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => false,
        ] );
        register_taxonomy( App::TAX_INGREDIENT, [ App::POST_TYPE, App::SHOPPING_LIST_POST_TYPE ], [
            'labels' => [
                'name'          => __( 'Ingredients', 'cookbook' ),
                'singular_name' => __( 'Ingredient', 'cookbook' ),
            ],
            // Hierarchical so users can manually group similar ingredients
            // ("cherry tomato" as a child of "tomato") via the standard WP UI.
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => false,
            'rewrite'           => false,
        ] );
    }

    public function register_rest_routes(): void {
        register_rest_route( 'cookbook/v1', '/home-ingredients', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'rest_home_ingredients' ],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
        ] );
    }

    public function rest_home_ingredients(): array {
        $stats = $this->get_home_ingredient_stats();

        return [
            'count'       => (int) $stats['count'],
            'count_label' => sprintf(
                /* translators: %d: number of ingredients */
                _n( '%d ingredient', '%d ingredients', (int) $stats['count'], 'cookbook' ),
                (int) $stats['count']
            ),
            'terms'       => $stats['top_terms'],
            'all_url'     => home_url( '/cookbook/by-ingredients' ),
            'all_label'   => __( 'all ingredients →', 'cookbook' ),
        ];
    }

    public function get_home_ingredient_stats(): array {
        $cached = get_transient( App::HOME_INGREDIENT_STATS_TRANSIENT );
        if (
            is_array( $cached )
            && isset( $cached['version'], $cached['top_terms'], $cached['count'] )
            && 1 === (int) $cached['version']
            && is_array( $cached['top_terms'] )
        ) {
            return [
                'top_terms' => $cached['top_terms'],
                'count'     => (int) $cached['count'],
            ];
        }

        $top_terms = get_terms( [
            'taxonomy'     => App::TAX_INGREDIENT,
            'hide_empty'   => true,
            'hierarchical' => false,
            'orderby'      => 'count',
            'order'        => 'DESC',
            'number'       => 24,
        ] );
        if ( is_wp_error( $top_terms ) ) {
            $top_terms = [];
        }

        $top_count = 0;
        foreach ( $top_terms as $term ) {
            $top_count = max( $top_count, (int) $term->count );
        }
        $top_terms = array_map( function( $term ) use ( $top_count ) {
            $weight = $top_count > 0 ? sqrt( (int) $term->count / $top_count ) : 0;

            return [
                'id'        => (int) $term->term_id,
                'name'      => (string) $term->name,
                'slug'      => (string) $term->slug,
                'count'     => (int) $term->count,
                'font_size' => number_format( 0.85 + $weight * 0.6, 2, '.', '' ),
                'url'       => add_query_arg( [ 'have' => [ (int) $term->term_id ] ], home_url( '/cookbook/by-ingredients' ) ),
            ];
        }, $top_terms );

        $count = wp_count_terms( [
            'taxonomy'     => App::TAX_INGREDIENT,
            'hide_empty'   => true,
            'hierarchical' => false,
        ] );
        if ( is_wp_error( $count ) ) {
            $count = count( $top_terms );
        }

        $stats = [
            'version'   => 1,
            'top_terms' => $top_terms,
            'count'     => (int) $count,
        ];
        set_transient( App::HOME_INGREDIENT_STATS_TRANSIENT, $stats, 12 * HOUR_IN_SECONDS );

        return $stats;
    }

    public function flush_home_ingredient_stats_cache( ...$args ): void {
        delete_transient( App::HOME_INGREDIENT_STATS_TRANSIENT );
    }

    public function maybe_flush_home_ingredient_stats_cache_for_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ): void {
        if ( $taxonomy === App::TAX_INGREDIENT ) {
            $this->flush_home_ingredient_stats_cache();
        }
    }

    public function maybe_flush_home_ingredient_stats_cache_for_status( string $new_status, string $old_status, \WP_Post $post ): void {
        if ( $new_status !== $old_status && $post->post_type === App::POST_TYPE ) {
            $this->flush_home_ingredient_stats_cache();
        }
    }
}
