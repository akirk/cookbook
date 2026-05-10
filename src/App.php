<?php

namespace Cookbook;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WpApp\WpApp;
use WpApp\BaseApp;

class App extends BaseApp {
    const POST_TYPE     = 'cb-recipes';
    const TAX_CATEGORY  = 'recipe_category';
    const TAX_CUISINE   = 'recipe_cuisine';
    const TAX_TAG       = 'recipe_tag';
    const TAX_INGREDIENT = 'recipe_ingredient';

    const META_SERVINGS    = '_recipe_servings';
    const META_PREP        = '_recipe_prep_time';
    const META_COOK        = '_recipe_cook_time';
    const META_INGREDIENTS = '_recipe_ingredients';
    const META_INSTRUCTIONS = '_recipe_instructions';
    const META_SOURCE_URL  = '_recipe_source_url';
    const META_NOTES       = '_recipe_notes';

    const SHOPPING_LIST_POST_TYPE = 'cb-shopping-list';
    const WEEK_PLAN_POST_TYPE     = 'cb-week-plan';

    const META_SHOPPING_ITEMS = '_cookbook_shopping_items';
    const META_WEEK_START     = '_cookbook_week_start';
    const META_WEEK_MEALS     = '_cookbook_week_meals';

    const USER_PREF_UNITS = 'cookbook_unit_preference';

    const MEAL_SLOTS = [ 'breakfast', 'lunch', 'dinner' ];

    public function __construct() {
        $this->app = new WpApp( $this->get_template_dir(), $this->get_url_path(), [
            'require_login' => true,
            'app_name'      => __( 'Cookbook', 'cookbook' ),
        ] );
    }

    protected function get_url_path(): string {
        return 'cookbook';
    }

    protected function get_template_dir(): string {
        return dirname( __DIR__ ) . '/templates';
    }

    public function init() {
        // cookbook.php hooks this method on init priority 10. We're already in init,
        // so register the CPT and taxonomies directly rather than via nested
        // add_action('init', …) — those don't fire reliably when added during the
        // priority-10 iteration.
        $this->register_post_type();
        $this->register_taxonomies();
        add_action( 'admin_post_cookbook_save', [ $this, 'handle_save' ] );
        add_action( 'admin_post_cookbook_delete', [ $this, 'handle_delete' ] );
        add_action( 'admin_post_cookbook_settings', [ $this, 'handle_settings' ] );
        add_action( 'admin_post_cookbook_import', [ $this, 'handle_import' ] );
        add_action( 'admin_post_cookbook_refetch', [ $this, 'handle_refetch' ] );
        add_action( 'admin_post_cookbook_replace_ingredient', [ $this, 'handle_replace_ingredient' ] );
        add_action( 'admin_post_cookbook_add_to_shopping_list', [ $this, 'handle_add_to_shopping_list' ] );
        add_action( 'admin_post_cookbook_update_shopping_list', [ $this, 'handle_update_shopping_list' ] );
        add_action( 'admin_post_cookbook_save_planner', [ $this, 'handle_save_planner' ] );
        add_action( 'admin_post_cookbook_add_planner_to_shopping_list', [ $this, 'handle_add_planner_to_shopping_list' ] );
        add_action( 'admin_post_cookbook_merge_ingredients', [ $this, 'handle_merge_ingredients' ] );
        add_action( 'admin_post_cookbook_group_ingredients', [ $this, 'handle_group_ingredients' ] );
        add_action( 'admin_post_cookbook_rename_ingredient', [ $this, 'handle_rename_ingredient' ] );
        add_action( 'wp_ajax_cookbook_parse_url', [ $this, 'ajax_parse_url' ] );
        add_action( 'wp_ajax_cookbook_parse_text', [ $this, 'ajax_parse_text' ] );

        add_action( 'wp_loaded', [ $this, 'handle_extension_save' ], 100 );
        add_filter( 'friends_browser_extension_actions', [ $this, 'register_browser_extension_action' ] );
        add_action( 'wp_app_admin_bar_menu', [ $this, 'add_recipe_admin_bar_edit_link' ] );
        add_action( 'wp_abilities_api_categories_init', [ $this, 'register_ability_categories' ] );
        add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
        add_filter( 'ai_assistant_ability_domains', [ $this, 'register_ability_domains' ] );
        add_filter( 'ai_assistant_ability_instructions', [ $this, 'ability_result_instructions' ], 10, 4 );

        parent::init();
    }

    protected function setup_database(): void {
        // Native WP storage: CPT + taxonomies + post meta.
    }

    protected function setup_routes(): void {
        $this->app->route( 'recipe/{id}' );
        $this->app->route( 'recipe/{id}/edit', 'recipe-edit.php' );
        $this->app->route( 'new' );
        $this->app->route( 'import' );
        $this->app->route( 'shopping-list' );
        $this->app->route( 'planner' );
        $this->app->route( 'by-ingredients' );
        $this->app->route( 'manage-ingredients' );
        $this->app->route( 'settings' );
        $this->app->route( 'category/{slug}' );
        $this->app->route( 'tag/{slug}' );
        $this->app->route( 'ingredient/{slug}' );
    }

    protected function setup_menu(): void {
        $home = home_url( '/' . $this->get_url_path() . '/' );
        $this->app->add_menu_item( 'all', __( 'All recipes', 'cookbook' ), $home );
        $this->app->add_menu_item( 'shopping-list', __( 'Shopping list', 'cookbook' ), $home . 'shopping-list' );
        $this->app->add_menu_item( 'planner', __( 'Week planner', 'cookbook' ), $home . 'planner' );
        $this->app->add_menu_item( 'by-ingredients', __( 'By ingredients', 'cookbook' ), $home . 'by-ingredients' );
        $this->app->add_menu_item( 'manage-ingredients', __( 'Manage ingredients', 'cookbook' ), $home . 'manage-ingredients' );
        $this->app->add_menu_item( 'new', __( 'New recipe', 'cookbook' ), $home . 'new' );
        $this->app->add_menu_item( 'import', __( 'Import from web', 'cookbook' ), $home . 'import' );
        $this->app->add_menu_item( 'settings', __( 'Settings', 'cookbook' ), $home . 'settings' );
    }

    /**
     * Tell AI Assistant which user topics should prefer Cookbook abilities.
     *
     * @param array $domains Existing plugin domain hints.
     * @return array
     */
    public function register_ability_domains( array $domains ): array {
        $domains['cookbook'] = implode( ', ', [
            'recipes',
            'recipe',
            'cooking',
            'cookbook',
            'ingredients',
            'ingredient',
            'meal planning',
            'meal plan',
            'week planner',
            'shopping list',
            'groceries',
            'cuisine',
            'servings',
            'recipe variations',
            'variation of a recipe',
        ] );

        return $domains;
    }

    /**
     * Tell AI Assistant how to present Cookbook ability results.
     *
     * @param string $instructions Existing instructions.
     * @param string $ability_id Ability ID.
     * @param array  $args Ability arguments.
     * @param mixed  $result Ability result.
     * @return string
     */
    public function ability_result_instructions( string $instructions, string $ability_id, array $args, $result ): string {
        if ( strpos( $ability_id, 'cookbook/' ) !== 0 || empty( $result ) ) {
            return $instructions;
        }

        if ( in_array( $ability_id, [ 'cookbook/get-recipe', 'cookbook/create-recipe', 'cookbook/import-recipe', 'cookbook/create-recipe-variation' ], true ) ) {
            return __( 'When presenting Cookbook recipes, include the recipe title and link it with view_url when present. For created recipes or variations, mention that the recipe was saved as a draft unless the returned status is publish.', 'cookbook' );
        }

        if ( $ability_id === 'cookbook/search-recipes' ) {
            return __( 'When presenting Cookbook search results, show concise recipe matches and link each recipe with view_url when present. If no recipes match, say that no Cookbook recipe was found instead of guessing from the database.', 'cookbook' );
        }

        if ( in_array( $ability_id, [ 'cookbook/get-week-plan', 'cookbook/save-week-plan' ], true ) ) {
            return __( 'When presenting Cookbook week plans, summarize planned meals by day and meal slot. Link planned recipes with their view_url when present, and link to the planner using the returned url.', 'cookbook' );
        }

        return $instructions;
    }

    /**
     * Register the Cookbook ability category.
     */
    public function register_ability_categories(): void {
        if ( ! function_exists( 'wp_register_ability_category' ) ) {
            return;
        }

        wp_register_ability_category(
            'cookbook',
            [
                'label'       => __( 'Cookbook', 'cookbook' ),
                'description' => __( 'Abilities for working with Cookbook recipes and week plans.', 'cookbook' ),
            ]
        );
    }

    /**
     * Register Abilities API actions for recipes and week plans.
     */
    public function register_abilities(): void {
        if ( ! function_exists( 'wp_register_ability' ) ) {
            return;
        }

        wp_register_ability(
            'cookbook/search-recipes',
            [
                'label'               => __( 'Search Cookbook Recipes', 'cookbook' ),
                'description'         => __( 'Searches Cookbook recipes and returns matching recipe summaries.', 'cookbook' ),
                'category'            => 'cookbook',
                'input_schema'        => self::recipe_search_input_schema(),
                'output_schema'       => self::recipe_search_output_schema(),
                'execute_callback'    => [ $this, 'ability_search_recipes' ],
                'permission_callback' => [ $this, 'can_read_abilities' ],
                'meta'                => [
                    'annotations'  => [
                        'instructions' => __( 'Use this when the user asks to find, list, filter, or choose Cookbook recipes. Return recipe IDs for follow-up get-recipe calls, and use view_url when linking results to the user.', 'cookbook' ),
                        'readonly'    => true,
                        'destructive' => false,
                        'idempotent'  => true,
                    ],
                    'show_in_rest' => true,
                ],
            ]
        );

        wp_register_ability(
            'cookbook/get-recipe',
            [
                'label'               => __( 'Get Cookbook Recipe', 'cookbook' ),
                'description'         => __( 'Returns one structured Cookbook recipe by ID.', 'cookbook' ),
                'category'            => 'cookbook',
                'input_schema'        => [
                    'type'                 => 'object',
                    'required'             => [ 'id' ],
                    'properties'           => [
                        'id' => [
                            'type'        => 'integer',
                            'description' => __( 'Recipe post ID.', 'cookbook' ),
                        ],
                    ],
                    'additionalProperties' => false,
                ],
                'output_schema'       => self::recipe_output_schema(),
                'execute_callback'    => [ $this, 'ability_get_recipe' ],
                'permission_callback' => [ $this, 'can_read_abilities' ],
                'meta'                => [
                    'annotations'  => [
                        'instructions' => __( 'Use this when the user asks about one known Cookbook recipe. The response includes view_url for linking, ingredients, instructions, notes, taxonomy terms, and variation family data.', 'cookbook' ),
                        'readonly'    => true,
                        'destructive' => false,
                        'idempotent'  => true,
                    ],
                    'show_in_rest' => true,
                ],
            ]
        );

        wp_register_ability(
            'cookbook/create-recipe',
            [
                'label'               => __( 'Create Cookbook Recipe', 'cookbook' ),
                'description'         => __( 'Creates a structured Cookbook recipe from provided fields.', 'cookbook' ),
                'category'            => 'cookbook',
                'input_schema'        => self::recipe_create_input_schema(),
                'output_schema'       => self::recipe_output_schema(),
                'execute_callback'    => [ $this, 'ability_create_recipe' ],
                'permission_callback' => [ $this, 'can_edit_abilities' ],
                'meta'                => [
                    'annotations'  => [
                        'instructions' => __( 'Use this when the user asks to create a brand-new structured recipe. Prefer create-recipe-variation when adapting an existing recipe. After creation, link the result using view_url.', 'cookbook' ),
                        'readonly'    => false,
                        'destructive' => false,
                        'idempotent'  => false,
                    ],
                    'show_in_rest' => true,
                ],
            ]
        );

        wp_register_ability(
            'cookbook/import-recipe',
            [
                'label'               => __( 'Import Cookbook Recipe', 'cookbook' ),
                'description'         => __( 'Imports a recipe from a URL or pasted recipe text and creates a draft recipe.', 'cookbook' ),
                'category'            => 'cookbook',
                'input_schema'        => [
                    'type'                 => 'object',
                    'properties'           => [
                        'source_url' => [
                            'type'        => 'string',
                            'description' => __( 'Recipe page URL to parse.', 'cookbook' ),
                        ],
                        'paste'      => [
                            'type'        => 'string',
                            'description' => __( 'Plain recipe text to parse if no URL can be parsed.', 'cookbook' ),
                        ],
                        'image_url'  => [
                            'type'        => 'string',
                            'description' => __( 'Optional image URL to sideload as the recipe photo.', 'cookbook' ),
                        ],
                    ],
                    'additionalProperties' => false,
                ],
                'output_schema'       => self::recipe_output_schema(),
                'execute_callback'    => [ $this, 'ability_import_recipe' ],
                'permission_callback' => [ $this, 'can_edit_abilities' ],
                'meta'                => [
                    'annotations'  => [
                        'instructions' => __( 'Use this when the user provides a recipe URL, pasted recipe text, or an image URL to import into Cookbook. This creates a draft recipe; link the result using view_url.', 'cookbook' ),
                        'readonly'    => false,
                        'destructive' => false,
                        'idempotent'  => false,
                    ],
                    'show_in_rest' => true,
                ],
            ]
        );

        wp_register_ability(
            'cookbook/create-recipe-variation',
            [
                'label'               => __( 'Create Cookbook Recipe Variation', 'cookbook' ),
                'description'         => __( 'Creates a child recipe variation from an existing Cookbook recipe, copying omitted fields from the source.', 'cookbook' ),
                'category'            => 'cookbook',
                'input_schema'        => self::recipe_variation_input_schema(),
                'output_schema'       => self::recipe_output_schema(),
                'execute_callback'    => [ $this, 'ability_create_recipe_variation' ],
                'permission_callback' => [ $this, 'can_edit_abilities' ],
                'meta'                => [
                    'annotations'  => [
                        'instructions' => __( 'Use this when the user asks for an adapted version of an existing recipe, such as substituting an ingredient they do not have. First call get-recipe for the source, then pass the complete revised recipe fields here so omitted fields intentionally copy from the source. Link the created variation using view_url.', 'cookbook' ),
                        'readonly'    => false,
                        'destructive' => false,
                        'idempotent'  => false,
                    ],
                    'show_in_rest' => true,
                ],
            ]
        );

        wp_register_ability(
            'cookbook/get-week-plan',
            [
                'label'               => __( 'Get Cookbook Week Plan', 'cookbook' ),
                'description'         => __( 'Returns the signed-in user\'s week planner for a normalized week.', 'cookbook' ),
                'category'            => 'cookbook',
                'input_schema'        => self::week_plan_get_input_schema(),
                'output_schema'       => self::week_plan_output_schema(),
                'execute_callback'    => [ $this, 'ability_get_week_plan' ],
                'permission_callback' => [ $this, 'can_read_abilities' ],
                'meta'                => [
                    'annotations'  => [
                        'instructions' => __( 'Use this when the user asks what meals are planned for a week. The week_start input can be any date in the week; use the returned url to link to the planner.', 'cookbook' ),
                        'readonly'    => true,
                        'destructive' => false,
                        'idempotent'  => true,
                    ],
                    'show_in_rest' => true,
                ],
            ]
        );

        wp_register_ability(
            'cookbook/save-week-plan',
            [
                'label'               => __( 'Save Cookbook Week Plan', 'cookbook' ),
                'description'         => __( 'Saves recipe IDs into the signed-in user\'s week planner meal slots.', 'cookbook' ),
                'category'            => 'cookbook',
                'input_schema'        => self::week_plan_save_input_schema(),
                'output_schema'       => self::week_plan_output_schema(),
                'execute_callback'    => [ $this, 'ability_save_week_plan' ],
                'permission_callback' => [ $this, 'can_plan_abilities' ],
                'meta'                => [
                    'annotations'  => [
                        'instructions' => __( 'Use this when the user asks to add, move, clear, or replace recipes in their week planner. This can clear slots with recipe ID 0 or replace the whole week when replace is true, so confirm ambiguous planner edits before executing.', 'cookbook' ),
                        'readonly'    => false,
                        'destructive' => true,
                        'idempotent'  => true,
                    ],
                    'show_in_rest' => true,
                ],
            ]
        );
    }

    /**
     * Permission callback for read-only abilities.
     */
    public function can_read_abilities(): bool {
        return is_user_logged_in();
    }

    /**
     * Permission callback for write abilities.
     */
    public function can_edit_abilities(): bool {
        return is_user_logged_in() && current_user_can( 'edit_posts' );
    }

    /**
     * Permission callback for user-owned planner abilities.
     */
    public function can_plan_abilities(): bool {
        return is_user_logged_in();
    }

    /**
     * Ability: search recipes.
     *
     * @param array $input Ability input.
     * @return array
     */
    public function ability_search_recipes( $input = [] ): array {
        $recipes = $this->search_recipes( is_array( $input ) ? $input : [] );

        return [
            'count'   => count( $recipes ),
            'recipes' => array_map( function( $recipe ) {
                return $this->recipe_payload( $recipe, false );
            }, $recipes ),
        ];
    }

    /**
     * Ability: get one recipe.
     *
     * @param array $input Ability input.
     * @return array|\WP_Error
     */
    public function ability_get_recipe( $input = [] ) {
        $id = is_array( $input ) && isset( $input['id'] ) ? absint( $input['id'] ) : 0;
        return $this->get_recipe_payload( $id, true );
    }

    /**
     * Ability: import one recipe into a draft.
     *
     * @param array $input Ability input.
     * @return array|\WP_Error
     */
    public function ability_import_recipe( $input = [] ) {
        $input = is_array( $input ) ? $input : [];
        $url   = isset( $input['source_url'] ) ? esc_url_raw( (string) $input['source_url'] ) : '';
        $paste = isset( $input['paste'] ) ? wp_kses_post( (string) $input['paste'] ) : '';

        $image_url = isset( $input['image_url'] ) ? esc_url_raw( (string) $input['image_url'] ) : '';

        $post_id = $this->import_recipe_to_draft( $url, $paste, $image_url );
        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        return $this->get_recipe_payload( (int) $post_id, true );
    }

    /**
     * Ability: create one recipe from structured fields.
     *
     * @param array $input Ability input.
     * @return array|\WP_Error
     */
    public function ability_create_recipe( $input = [] ) {
        $input     = is_array( $input ) ? $input : [];
        $parent_id = isset( $input['parent_id'] ) ? absint( $input['parent_id'] ) : 0;
        $parent_id = $this->sanitize_recipe_parent_id( $parent_id );

        return $this->create_recipe_from_ability_input( $input, $parent_id );
    }

    /**
     * Ability: create a child variation copied from an existing recipe.
     *
     * @param array $input Ability input.
     * @return array|\WP_Error
     */
    public function ability_create_recipe_variation( $input = [] ) {
        $input     = is_array( $input ) ? $input : [];
        $source_id = isset( $input['source_recipe_id'] ) ? absint( $input['source_recipe_id'] ) : 0;
        $source    = $source_id ? get_post( $source_id ) : null;
        if ( ! $source || $source->post_type !== self::POST_TYPE ) {
            return new \WP_Error( 'cookbook_recipe_not_found', __( 'Recipe not found.', 'cookbook' ) );
        }

        $parent_id = isset( $input['parent_id'] ) ? absint( $input['parent_id'] ) : $source_id;
        $parent_id = $this->sanitize_recipe_parent_id( $parent_id );
        if ( ! $parent_id ) {
            return new \WP_Error( 'cookbook_variation_parent_not_found', __( 'Variation parent recipe not found.', 'cookbook' ) );
        }

        return $this->create_recipe_from_ability_input( $input, $parent_id, $source );
    }

    /**
     * Ability: get the current user's week plan.
     *
     * @param array $input Ability input.
     * @return array
     */
    public function ability_get_week_plan( $input = [] ): array {
        $input = is_array( $input ) ? $input : [];
        $week_start = isset( $input['week_start'] ) ? sanitize_text_field( (string) $input['week_start'] ) : '';
        $week_start = self::normalize_week_start( $week_start );
        $plan_id    = self::get_user_week_plan_id( $week_start, false );

        return $this->week_plan_payload( $week_start, $plan_id );
    }

    /**
     * Ability: save slots in the current user's week plan.
     *
     * @param array $input Ability input.
     * @return array|\WP_Error
     */
    public function ability_save_week_plan( $input = [] ) {
        $input      = is_array( $input ) ? $input : [];
        $week_start = isset( $input['week_start'] ) ? sanitize_text_field( (string) $input['week_start'] ) : '';
        $week_start = self::normalize_week_start( $week_start );
        $raw_meals  = isset( $input['meals'] ) && is_array( $input['meals'] ) ? $input['meals'] : [];
        $replace    = ! empty( $input['replace'] );

        $plan_id = self::get_user_week_plan_id( $week_start, true );
        if ( ! $plan_id ) {
            return new \WP_Error( 'cookbook_week_plan_not_saved', __( 'Week plan could not be saved.', 'cookbook' ) );
        }

        $base  = $replace ? [] : self::get_week_meals( $plan_id );
        $meals = $this->merge_week_plan_meals( $raw_meals, $week_start, $base );

        update_post_meta( $plan_id, self::META_WEEK_START, $week_start );
        update_post_meta( $plan_id, self::META_WEEK_MEALS, $meals );

        return $this->week_plan_payload( $week_start, $plan_id );
    }

    /**
     * Internal recipe search used by ability adapters and other app code.
     */
    private function search_recipes( array $filters = [] ): array {
        $limit = isset( $filters['limit'] ) ? absint( $filters['limit'] ) : 20;
        $limit = max( 1, min( 100, $limit ) );

        $status = isset( $filters['status'] ) ? sanitize_key( (string) $filters['status'] ) : 'any';
        if ( ! in_array( $status, [ 'publish', 'draft', 'any' ], true ) ) {
            $status = 'any';
        }

        $args = [
            'post_type'      => self::POST_TYPE,
            'post_status'    => $status === 'any' ? [ 'publish', 'draft' ] : $status,
            'posts_per_page' => $limit,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];

        $search = isset( $filters['search'] ) ? sanitize_text_field( (string) $filters['search'] ) : '';
        if ( $search !== '' ) {
            $args['s'] = $search;
        }

        $tax_query = [];
        foreach ( [
            'category'   => self::TAX_CATEGORY,
            'tag'        => self::TAX_TAG,
            'ingredient' => self::TAX_INGREDIENT,
        ] as $field => $taxonomy ) {
            $clause = $this->tax_query_clause( $filters[ $field ] ?? '', $taxonomy );
            if ( $clause ) {
                $tax_query[] = $clause;
            }
        }
        if ( $tax_query ) {
            if ( count( $tax_query ) > 1 ) {
                $tax_query['relation'] = 'AND';
            }
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- ability filters by requested taxonomy terms.
            $args['tax_query'] = $tax_query;
        }

        return get_posts( $args );
    }

    /**
     * Internal structured recipe payload used by ability adapters.
     *
     * @return array|\WP_Error
     */
    private function get_recipe_payload( int $id, bool $include_details ) {
        $post = $id ? get_post( $id ) : null;
        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            return new \WP_Error( 'cookbook_recipe_not_found', __( 'Recipe not found.', 'cookbook' ) );
        }

        return $this->recipe_payload( $post, $include_details );
    }

    private function create_recipe_from_ability_input( array $input, int $parent_id = 0, $source = null ) {
        $source = $source instanceof \WP_Post && $source->post_type === self::POST_TYPE ? $source : null;
        $source_id = $source ? (int) $source->ID : 0;

        $title = $this->ability_text_input(
            $input,
            'title',
            $source
                ? sprintf(
                    /* translators: %s: source recipe title */
                    __( '%s variation', 'cookbook' ),
                    get_the_title( $source )
                )
                : __( 'Untitled recipe', 'cookbook' )
        );
        $description = $this->ability_html_input( $input, 'description', $source ? $source->post_content : '' );
        $servings    = $this->ability_positive_int_input( $input, 'servings', $source_id ? (int) get_post_meta( $source_id, self::META_SERVINGS, true ) : 4, 1 );
        $prep        = $this->ability_positive_int_input( $input, 'prep_time', $source_id ? (int) get_post_meta( $source_id, self::META_PREP, true ) : 0, 0 );
        $cook        = $this->ability_positive_int_input( $input, 'cook_time', $source_id ? (int) get_post_meta( $source_id, self::META_COOK, true ) : 0, 0 );
        $source_url  = isset( $input['source_url'] )
            ? esc_url_raw( (string) $input['source_url'] )
            : ( $source_id ? (string) get_post_meta( $source_id, self::META_SOURCE_URL, true ) : '' );
        $notes       = $this->ability_html_input( $input, 'notes', $source_id ? (string) get_post_meta( $source_id, self::META_NOTES, true ) : '' );

        if ( $source && ! empty( $input['change_summary'] ) ) {
            $change_summary = sanitize_text_field( (string) $input['change_summary'] );
            if ( $change_summary !== '' ) {
                $notes = trim( $notes );
                $notes .= ( $notes === '' ? '' : "\n\n" ) . sprintf(
                    /* translators: %s: generated variation summary */
                    __( 'Variation notes: %s', 'cookbook' ),
                    $change_summary
                );
            }
        }

        $ingredients = isset( $input['ingredients'] ) && is_array( $input['ingredients'] )
            ? $this->sanitize_recipe_ingredient_rows( $input['ingredients'] )
            : ( $source_id ? (array) get_post_meta( $source_id, self::META_INGREDIENTS, true ) : [] );
        $instructions = isset( $input['instructions'] ) && is_array( $input['instructions'] )
            ? $this->sanitize_recipe_instruction_rows( $input['instructions'] )
            : ( $source_id ? (array) get_post_meta( $source_id, self::META_INSTRUCTIONS, true ) : [] );

        $status = isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : 'draft';
        if ( ! in_array( $status, [ 'draft', 'publish' ], true ) ) {
            $status = 'draft';
        }
        if ( $status === 'publish' && ! current_user_can( 'publish_posts' ) ) {
            $status = 'draft';
        }

        $post_id = wp_insert_post( [
            'post_type'    => self::POST_TYPE,
            'post_status'  => $status,
            'post_title'   => $title !== '' ? $title : __( 'Untitled recipe', 'cookbook' ),
            'post_content' => $description,
            'post_author'  => get_current_user_id(),
            'post_parent'  => $parent_id,
        ], true );
        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }
        $post_id = (int) $post_id;

        update_post_meta( $post_id, self::META_SERVINGS, $servings );
        update_post_meta( $post_id, self::META_PREP, $prep );
        update_post_meta( $post_id, self::META_COOK, $cook );
        $this->persist_ingredients( $post_id, $ingredients );
        update_post_meta( $post_id, self::META_INSTRUCTIONS, $instructions );
        update_post_meta( $post_id, self::META_SOURCE_URL, $source_url );
        update_post_meta( $post_id, self::META_NOTES, $notes );

        $this->set_or_copy_ability_terms( $post_id, $input, 'categories', self::TAX_CATEGORY, $source_id );
        $this->set_or_copy_ability_terms( $post_id, $input, 'cuisines', self::TAX_CUISINE, $source_id );
        $this->set_or_copy_ability_terms( $post_id, $input, 'tags', self::TAX_TAG, $source_id );

        $image_url = isset( $input['image_url'] ) ? esc_url_raw( (string) $input['image_url'] ) : '';
        if ( $image_url !== '' ) {
            $this->sideload_image_to_post( $post_id, $image_url );
        } elseif (
            $source_id
            && ( ! array_key_exists( 'copy_source_thumbnail', $input ) || ! empty( $input['copy_source_thumbnail'] ) )
            && has_post_thumbnail( $source_id )
        ) {
            set_post_thumbnail( $post_id, get_post_thumbnail_id( $source_id ) );
        }

        return $this->get_recipe_payload( $post_id, true );
    }

    private function week_plan_payload( string $week_start, int $plan_id = 0 ): array {
        $week_start = self::normalize_week_start( $week_start );
        $days       = self::week_days( $week_start );
        $meal_slots = self::meal_slots();
        $raw_meals  = $plan_id ? self::get_week_meals( $plan_id ) : [];
        $meal_ids   = $this->sanitize_planner_meals( $raw_meals, $week_start );
        $planned    = [];

        foreach ( $days as $date => $day ) {
            foreach ( $meal_slots as $slot => $slot_label ) {
                $recipe_id = isset( $meal_ids[ $date ][ $slot ] ) ? absint( $meal_ids[ $date ][ $slot ] ) : 0;
                if ( ! $recipe_id ) {
                    continue;
                }

                $recipe = get_post( $recipe_id );
                if ( ! $recipe || $recipe->post_type !== self::POST_TYPE ) {
                    continue;
                }

                $planned[] = [
                    'date'       => $date,
                    'day_short'  => (string) $day['short'],
                    'day_label'  => (string) $day['label'],
                    'slot'       => $slot,
                    'slot_label' => $slot_label,
                    'recipe'     => $this->recipe_payload( $recipe, false ),
                ];
            }
        }

        $timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
        try {
            $start = new \DateTimeImmutable( $week_start, $timezone );
        } catch ( \Exception $e ) {
            $start = new \DateTimeImmutable( self::normalize_week_start(), $timezone );
        }

        return [
            'id'            => $plan_id,
            'week_start'    => $week_start,
            'url'           => add_query_arg( 'week', $week_start, home_url( '/' . $this->get_url_path() . '/planner' ) ),
            'previous_week' => $start->modify( '-7 days' )->format( 'Y-m-d' ),
            'next_week'     => $start->modify( '+7 days' )->format( 'Y-m-d' ),
            'days'          => array_map( function( string $date, array $day ) {
                return [
                    'date'  => $date,
                    'short' => (string) $day['short'],
                    'label' => (string) $day['label'],
                ];
            }, array_keys( $days ), $days ),
            'meal_slots'    => array_map( function( string $slot, string $label ) {
                return [
                    'slot'  => $slot,
                    'label' => $label,
                ];
            }, array_keys( $meal_slots ), $meal_slots ),
            'meal_ids'      => $meal_ids,
            'planned_meals' => $planned,
        ];
    }

    private function merge_week_plan_meals( array $raw_meals, string $week_start, array $base = [] ): array {
        $meals = $this->sanitize_planner_meals( $base, $week_start );
        $days  = array_keys( self::week_days( $week_start ) );
        $slots = array_keys( self::meal_slots() );

        foreach ( $days as $date ) {
            if ( ! isset( $raw_meals[ $date ] ) || ! is_array( $raw_meals[ $date ] ) ) {
                continue;
            }

            foreach ( $slots as $slot ) {
                if ( ! array_key_exists( $slot, $raw_meals[ $date ] ) ) {
                    continue;
                }

                $recipe_id = absint( $raw_meals[ $date ][ $slot ] );
                if ( $recipe_id && $this->recipe_exists( $recipe_id ) ) {
                    $meals[ $date ][ $slot ] = $recipe_id;
                    continue;
                }

                unset( $meals[ $date ][ $slot ] );
                if ( empty( $meals[ $date ] ) ) {
                    unset( $meals[ $date ] );
                }
            }
        }

        return $meals;
    }

    private function ability_text_input( array $input, string $key, string $default = '' ): string {
        if ( ! array_key_exists( $key, $input ) || ! is_scalar( $input[ $key ] ) ) {
            return $default;
        }

        return sanitize_text_field( (string) $input[ $key ] );
    }

    private function ability_html_input( array $input, string $key, string $default = '' ): string {
        if ( ! array_key_exists( $key, $input ) || ! is_scalar( $input[ $key ] ) ) {
            return $default;
        }

        return wp_kses_post( (string) $input[ $key ] );
    }

    private function ability_positive_int_input( array $input, string $key, int $default, int $minimum ): int {
        if ( ! array_key_exists( $key, $input ) ) {
            return max( $minimum, $default );
        }

        return max( $minimum, absint( $input[ $key ] ) );
    }

    private function sanitize_recipe_ingredient_rows( array $rows ): array {
        $ingredients = [];
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $name = isset( $row['name'] ) ? sanitize_text_field( (string) $row['name'] ) : '';
            if ( $name === '' ) {
                continue;
            }

            $ingredients[] = [
                'amount' => isset( $row['amount'] ) ? sanitize_text_field( (string) $row['amount'] ) : '',
                'unit'   => isset( $row['unit'] ) ? sanitize_text_field( (string) $row['unit'] ) : '',
                'name'   => $name,
                'notes'  => isset( $row['notes'] ) ? sanitize_text_field( (string) $row['notes'] ) : '',
            ];
        }

        return $ingredients;
    }

    private function sanitize_recipe_instruction_rows( array $rows ): array {
        $instructions = [];
        foreach ( $rows as $step ) {
            if ( ! is_scalar( $step ) ) {
                continue;
            }

            $step = Importer::clean_step( wp_kses_post( (string) $step ) );
            if ( $step !== '' ) {
                $instructions[] = $step;
            }
        }

        return $instructions;
    }

    private function set_or_copy_ability_terms( int $post_id, array $input, string $field, string $taxonomy, int $source_id = 0 ): void {
        if ( array_key_exists( $field, $input ) ) {
            $values = is_array( $input[ $field ] ) ? $input[ $field ] : [];
            $values = array_filter( array_map( function( $value ) {
                return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
            }, $values ) );
            wp_set_object_terms( $post_id, $this->resolve_term_ids( $values, $taxonomy ), $taxonomy );
            return;
        }

        if ( ! $source_id ) {
            return;
        }

        $term_ids = wp_get_object_terms( $source_id, $taxonomy, [ 'fields' => 'ids' ] );
        if ( is_wp_error( $term_ids ) ) {
            return;
        }

        wp_set_object_terms( $post_id, array_map( 'intval', $term_ids ), $taxonomy );
    }

    private function tax_query_clause( $value, string $taxonomy ): array {
        $value = is_scalar( $value ) ? trim( (string) $value ) : '';
        if ( $value === '' ) {
            return [];
        }

        return [
            'taxonomy' => $taxonomy,
            'field'    => ctype_digit( $value ) ? 'term_id' : 'slug',
            'terms'    => ctype_digit( $value ) ? absint( $value ) : sanitize_title( $value ),
        ];
    }

    private function recipe_payload( $post, bool $include_details ): array {
        if ( ! $post instanceof \WP_Post ) {
            return [];
        }

        $id = (int) $post->ID;
        $payload = [
            'id'            => $id,
            'title'         => get_the_title( $post ),
            'status'        => $post->post_status,
            'url'           => home_url( '/' . $this->get_url_path() . '/recipe/' . $id ),
            'view_url'      => home_url( '/' . $this->get_url_path() . '/recipe/' . $id ),
            'edit_url'      => home_url( '/' . $this->get_url_path() . '/recipe/' . $id . '/edit' ),
            'variation_url' => add_query_arg( 'variation_of', $id, home_url( '/' . $this->get_url_path() . '/new' ) ),
            'parent_id'     => (int) $post->post_parent,
            'variation_root_id' => self::get_recipe_variation_root_id( $id ),
            'servings'      => (int) get_post_meta( $id, self::META_SERVINGS, true ),
            'prep_time'     => (int) get_post_meta( $id, self::META_PREP, true ),
            'cook_time'     => (int) get_post_meta( $id, self::META_COOK, true ),
            'source_url'    => (string) get_post_meta( $id, self::META_SOURCE_URL, true ),
            'thumbnail_url' => get_the_post_thumbnail_url( $id, 'large' ) ?: '',
            'categories'    => $this->terms_payload( $id, self::TAX_CATEGORY ),
            'cuisines'      => $this->terms_payload( $id, self::TAX_CUISINE ),
            'tags'          => $this->terms_payload( $id, self::TAX_TAG ),
            'ingredients'   => (array) get_post_meta( $id, self::META_INGREDIENTS, true ),
        ];

        if ( $include_details ) {
            $payload['description']  = wp_strip_all_tags( $post->post_content );
            $payload['instructions'] = (array) get_post_meta( $id, self::META_INSTRUCTIONS, true );
            $payload['notes']        = wp_strip_all_tags( (string) get_post_meta( $id, self::META_NOTES, true ) );
            $payload['variation_family'] = array_map( function( $item ) {
                $variation = $item['post'] ?? null;
                if ( ! $variation instanceof \WP_Post ) {
                    return [];
                }

                return [
                    'id'        => (int) $variation->ID,
                    'title'     => get_the_title( $variation ),
                    'status'    => $variation->post_status,
                    'url'       => home_url( '/' . $this->get_url_path() . '/recipe/' . $variation->ID ),
                    'view_url'  => home_url( '/' . $this->get_url_path() . '/recipe/' . $variation->ID ),
                    'parent_id' => (int) $variation->post_parent,
                    'depth'     => isset( $item['depth'] ) ? (int) $item['depth'] : 0,
                ];
            }, self::get_recipe_variation_family( $id ) );
        }

        return $payload;
    }

    private function terms_payload( int $post_id, string $taxonomy ): array {
        $terms = wp_get_object_terms( $post_id, $taxonomy );
        if ( is_wp_error( $terms ) || ! $terms ) {
            return [];
        }

        return array_map( function( $term ) {
            return [
                'id'   => (int) $term->term_id,
                'name' => (string) $term->name,
                'slug' => (string) $term->slug,
            ];
        }, $terms );
    }

    private static function recipe_search_input_schema(): array {
        return [
            'type'                 => 'object',
            'properties'           => [
                'search'     => [
                    'type'        => 'string',
                    'description' => __( 'Optional search text for recipe title and content.', 'cookbook' ),
                ],
                'category'   => [
                    'type'        => 'string',
                    'description' => __( 'Optional category slug or term ID.', 'cookbook' ),
                ],
                'tag'        => [
                    'type'        => 'string',
                    'description' => __( 'Optional tag slug or term ID.', 'cookbook' ),
                ],
                'ingredient' => [
                    'type'        => 'string',
                    'description' => __( 'Optional ingredient slug or term ID.', 'cookbook' ),
                ],
                'status'     => [
                    'type'        => 'string',
                    'description' => __( 'Recipe status to include.', 'cookbook' ),
                    'enum'        => [ 'publish', 'draft', 'any' ],
                ],
                'limit'      => [
                    'type'        => 'integer',
                    'description' => __( 'Maximum number of recipes to return, from 1 to 100.', 'cookbook' ),
                    'minimum'     => 1,
                    'maximum'     => 100,
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    private static function recipe_search_output_schema(): array {
        return [
            'type'                 => 'object',
            'required'             => [ 'count', 'recipes' ],
            'properties'           => [
                'count'   => [
                    'type'        => 'integer',
                    'description' => __( 'Number of recipes returned.', 'cookbook' ),
                ],
                'recipes' => [
                    'type'  => 'array',
                    'items' => self::recipe_summary_schema(),
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    private static function recipe_summary_schema(): array {
        return [
            'type'                 => 'object',
            'required'             => [ 'id', 'title', 'status', 'url', 'view_url', 'ingredients' ],
            'properties'           => [
                'id'            => [ 'type' => 'integer' ],
                'title'         => [ 'type' => 'string' ],
                'status'        => [ 'type' => 'string' ],
                'url'           => [ 'type' => 'string' ],
                'view_url'      => [
                    'type'        => 'string',
                    'description' => __( 'User-facing app URL for linking to the recipe.', 'cookbook' ),
                ],
                'edit_url'      => [ 'type' => 'string' ],
                'variation_url' => [ 'type' => 'string' ],
                'parent_id'     => [ 'type' => 'integer' ],
                'variation_root_id' => [ 'type' => 'integer' ],
                'servings'      => [ 'type' => 'integer' ],
                'prep_time'     => [ 'type' => 'integer' ],
                'cook_time'     => [ 'type' => 'integer' ],
                'source_url'    => [ 'type' => 'string' ],
                'thumbnail_url' => [ 'type' => 'string' ],
                'categories'    => [
                    'type'  => 'array',
                    'items' => self::term_schema(),
                ],
                'cuisines'      => [
                    'type'  => 'array',
                    'items' => self::term_schema(),
                ],
                'tags'          => [
                    'type'  => 'array',
                    'items' => self::term_schema(),
                ],
                'ingredients'   => [
                    'type'  => 'array',
                    'items' => self::ingredient_schema(),
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    private static function recipe_output_schema(): array {
        $schema = self::recipe_summary_schema();
        $schema['required'] = array_merge( $schema['required'], [ 'description', 'instructions', 'notes' ] );
        $schema['properties']['description'] = [ 'type' => 'string' ];
        $schema['properties']['instructions'] = [
            'type'  => 'array',
            'items' => [ 'type' => 'string' ],
        ];
        $schema['properties']['notes'] = [ 'type' => 'string' ];
        $schema['properties']['variation_family'] = [
            'type'  => 'array',
            'items' => self::variation_family_item_schema(),
        ];
        return $schema;
    }

    private static function recipe_create_input_schema(): array {
        return [
            'type'                 => 'object',
            'required'             => [ 'title' ],
            'properties'           => self::recipe_create_input_properties(),
            'additionalProperties' => false,
        ];
    }

    private static function recipe_variation_input_schema(): array {
        $properties = self::recipe_create_input_properties();
        $properties['source_recipe_id'] = [
            'type'        => 'integer',
            'description' => __( 'Recipe post ID to copy as the source for the new variation.', 'cookbook' ),
        ];
        $properties['copy_source_thumbnail'] = [
            'type'        => 'boolean',
            'description' => __( 'Whether to reuse the source recipe photo when image_url is omitted. Defaults to true.', 'cookbook' ),
        ];
        $properties['change_summary'] = [
            'type'        => 'string',
            'description' => __( 'Short note describing what changed in this variation.', 'cookbook' ),
        ];

        return [
            'type'                 => 'object',
            'required'             => [ 'source_recipe_id' ],
            'properties'           => $properties,
            'additionalProperties' => false,
        ];
    }

    private static function recipe_create_input_properties(): array {
        return [
            'title'       => [
                'type'        => 'string',
                'description' => __( 'Recipe title.', 'cookbook' ),
            ],
            'description' => [
                'type'        => 'string',
                'description' => __( 'Short recipe description.', 'cookbook' ),
            ],
            'ingredients' => [
                'type'        => 'array',
                'description' => __( 'Structured ingredient rows.', 'cookbook' ),
                'items'       => self::ingredient_input_schema(),
            ],
            'instructions' => [
                'type'        => 'array',
                'description' => __( 'Recipe instruction steps.', 'cookbook' ),
                'items'       => [ 'type' => 'string' ],
            ],
            'servings'    => [
                'type'        => 'integer',
                'description' => __( 'Default serving count.', 'cookbook' ),
                'minimum'     => 1,
            ],
            'prep_time'   => [
                'type'        => 'integer',
                'description' => __( 'Prep time in minutes.', 'cookbook' ),
                'minimum'     => 0,
            ],
            'cook_time'   => [
                'type'        => 'integer',
                'description' => __( 'Cook time in minutes.', 'cookbook' ),
                'minimum'     => 0,
            ],
            'source_url'  => [
                'type'        => 'string',
                'description' => __( 'Optional source URL.', 'cookbook' ),
            ],
            'notes'       => [
                'type'        => 'string',
                'description' => __( 'Private recipe notes.', 'cookbook' ),
            ],
            'status'      => [
                'type'        => 'string',
                'description' => __( 'Recipe status. Defaults to draft.', 'cookbook' ),
                'enum'        => [ 'draft', 'publish' ],
            ],
            'parent_id'   => [
                'type'        => 'integer',
                'description' => __( 'Optional parent recipe ID to link this recipe as a variation.', 'cookbook' ),
                'minimum'     => 0,
            ],
            'categories'  => self::taxonomy_values_input_schema( __( 'Category names, slugs, or IDs.', 'cookbook' ) ),
            'cuisines'    => self::taxonomy_values_input_schema( __( 'Cuisine names, slugs, or IDs.', 'cookbook' ) ),
            'tags'        => self::taxonomy_values_input_schema( __( 'Tag names, slugs, or IDs.', 'cookbook' ) ),
            'image_url'   => [
                'type'        => 'string',
                'description' => __( 'Optional image URL to sideload as the recipe photo.', 'cookbook' ),
            ],
        ];
    }

    private static function week_plan_get_input_schema(): array {
        return [
            'type'                 => 'object',
            'properties'           => [
                'week_start' => [
                    'type'        => 'string',
                    'description' => __( 'Any date in the requested week. The site week start is returned as YYYY-MM-DD.', 'cookbook' ),
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    private static function week_plan_save_input_schema(): array {
        $schema = self::week_plan_get_input_schema();
        $schema['required'] = [ 'meals' ];
        $schema['properties']['replace'] = [
            'type'        => 'boolean',
            'description' => __( 'Whether omitted meal slots should be cleared. Defaults to false.', 'cookbook' ),
        ];
        $schema['properties']['meals'] = [
            'type'                 => 'object',
            'description'          => __( 'Object keyed by YYYY-MM-DD, then breakfast, lunch, and dinner recipe IDs. Use 0 to clear a slot.', 'cookbook' ),
            'additionalProperties' => self::week_plan_meal_ids_schema(),
        ];
        return $schema;
    }

    private static function week_plan_output_schema(): array {
        return [
            'type'                 => 'object',
            'required'             => [ 'id', 'week_start', 'url', 'previous_week', 'next_week', 'days', 'meal_slots', 'meal_ids', 'planned_meals' ],
            'properties'           => [
                'id'            => [ 'type' => 'integer' ],
                'week_start'    => [ 'type' => 'string' ],
                'url'           => [ 'type' => 'string' ],
                'previous_week' => [ 'type' => 'string' ],
                'next_week'     => [ 'type' => 'string' ],
                'days'          => [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'required'             => [ 'date', 'short', 'label' ],
                        'properties'           => [
                            'date'  => [ 'type' => 'string' ],
                            'short' => [ 'type' => 'string' ],
                            'label' => [ 'type' => 'string' ],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
                'meal_slots'    => [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'required'             => [ 'slot', 'label' ],
                        'properties'           => [
                            'slot'  => [
                                'type' => 'string',
                                'enum' => self::MEAL_SLOTS,
                            ],
                            'label' => [ 'type' => 'string' ],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
                'meal_ids'      => [
                    'type'                 => 'object',
                    'additionalProperties' => self::week_plan_meal_ids_schema(),
                ],
                'planned_meals' => [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'required'             => [ 'date', 'day_short', 'day_label', 'slot', 'slot_label', 'recipe' ],
                        'properties'           => [
                            'date'       => [ 'type' => 'string' ],
                            'day_short'  => [ 'type' => 'string' ],
                            'day_label'  => [ 'type' => 'string' ],
                            'slot'       => [
                                'type' => 'string',
                                'enum' => self::MEAL_SLOTS,
                            ],
                            'slot_label' => [ 'type' => 'string' ],
                            'recipe'     => self::recipe_summary_schema(),
                        ],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    private static function week_plan_meal_ids_schema(): array {
        return [
            'type'                 => 'object',
            'properties'           => [
                'breakfast' => [
                    'type'        => 'integer',
                    'description' => __( 'Breakfast recipe ID, or 0 to clear.', 'cookbook' ),
                    'minimum'     => 0,
                ],
                'lunch'     => [
                    'type'        => 'integer',
                    'description' => __( 'Lunch recipe ID, or 0 to clear.', 'cookbook' ),
                    'minimum'     => 0,
                ],
                'dinner'    => [
                    'type'        => 'integer',
                    'description' => __( 'Dinner recipe ID, or 0 to clear.', 'cookbook' ),
                    'minimum'     => 0,
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    private static function ingredient_schema(): array {
        return [
            'type'                 => 'object',
            'properties'           => [
                'amount'  => [ 'type' => 'string' ],
                'unit'    => [ 'type' => 'string' ],
                'name'    => [ 'type' => 'string' ],
                'notes'   => [ 'type' => 'string' ],
                'term_id' => [ 'type' => 'integer' ],
            ],
            'additionalProperties' => false,
        ];
    }

    private static function ingredient_input_schema(): array {
        return [
            'type'                 => 'object',
            'properties'           => [
                'amount' => [ 'type' => 'string' ],
                'unit'   => [ 'type' => 'string' ],
                'name'   => [ 'type' => 'string' ],
                'notes'  => [ 'type' => 'string' ],
            ],
            'required'             => [ 'name' ],
            'additionalProperties' => false,
        ];
    }

    private static function taxonomy_values_input_schema( string $description ): array {
        return [
            'type'        => 'array',
            'description' => $description,
            'items'       => [ 'type' => 'string' ],
        ];
    }

    private static function variation_family_item_schema(): array {
        return [
            'type'                 => 'object',
            'required'             => [ 'id', 'title', 'status', 'url', 'view_url', 'parent_id', 'depth' ],
            'properties'           => [
                'id'        => [ 'type' => 'integer' ],
                'title'     => [ 'type' => 'string' ],
                'status'    => [ 'type' => 'string' ],
                'url'       => [ 'type' => 'string' ],
                'view_url'  => [
                    'type'        => 'string',
                    'description' => __( 'User-facing app URL for linking to the variation.', 'cookbook' ),
                ],
                'parent_id' => [ 'type' => 'integer' ],
                'depth'     => [ 'type' => 'integer' ],
            ],
            'additionalProperties' => false,
        ];
    }

    private static function term_schema(): array {
        return [
            'type'                 => 'object',
            'required'             => [ 'id', 'name', 'slug' ],
            'properties'           => [
                'id'   => [ 'type' => 'integer' ],
                'name' => [ 'type' => 'string' ],
                'slug' => [ 'type' => 'string' ],
            ],
            'additionalProperties' => false,
        ];
    }

    public function add_recipe_admin_bar_edit_link( $wp_admin_bar ): void {
        global $wp_app_route;

        if ( empty( $wp_app_route['template'] ) || $wp_app_route['template'] !== 'recipe.php' ) {
            return;
        }

        $id = isset( $wp_app_route['params']['id'] ) ? absint( $wp_app_route['params']['id'] ) : 0;
        $post = $id ? get_post( $id ) : null;
        if ( ! $post || $post->post_type !== self::POST_TYPE || ! current_user_can( 'edit_post', $id ) ) {
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
        register_post_type( self::POST_TYPE, [
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

        register_post_meta( self::POST_TYPE, self::META_SERVINGS, [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => 4,
            'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
        ] );
        register_post_meta( self::POST_TYPE, self::META_PREP, [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
        ] );
        register_post_meta( self::POST_TYPE, self::META_COOK, [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
        ] );
        register_post_meta( self::POST_TYPE, self::META_INGREDIENTS, [
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
        register_post_meta( self::POST_TYPE, self::META_INSTRUCTIONS, [
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
        register_post_meta( self::POST_TYPE, self::META_SOURCE_URL, [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
        ] );
        register_post_meta( self::POST_TYPE, self::META_NOTES, [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
        ] );

        register_post_type( self::SHOPPING_LIST_POST_TYPE, [
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
            'show_in_menu'       => 'edit.php?post_type=' . self::POST_TYPE,
            'show_in_rest'       => true,
            'supports'           => [ 'title', 'author', 'revisions' ],
            'has_archive'        => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
        ] );
        register_post_meta( self::SHOPPING_LIST_POST_TYPE, self::META_SHOPPING_ITEMS, [
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

        register_post_type( self::WEEK_PLAN_POST_TYPE, [
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
            'show_in_menu'       => 'edit.php?post_type=' . self::POST_TYPE,
            'show_in_rest'       => true,
            'supports'           => [ 'title', 'author', 'revisions' ],
            'has_archive'        => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
        ] );
        register_post_meta( self::WEEK_PLAN_POST_TYPE, self::META_WEEK_START, [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
        ] );
        register_post_meta( self::WEEK_PLAN_POST_TYPE, self::META_WEEK_MEALS, [
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
    }

    public function register_taxonomies(): void {
        register_taxonomy( self::TAX_CATEGORY, self::POST_TYPE, [
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
        register_taxonomy( self::TAX_CUISINE, self::POST_TYPE, [
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
        register_taxonomy( self::TAX_TAG, self::POST_TYPE, [
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
        register_taxonomy( self::TAX_INGREDIENT, self::POST_TYPE, [
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

    public static function get_user_unit_preference( int $user_id = 0 ): string {
        $user_id = $user_id ?: get_current_user_id();
        $pref    = get_user_meta( $user_id, self::USER_PREF_UNITS, true );
        return in_array( $pref, [ 'metric', 'imperial' ], true ) ? $pref : 'metric';
    }

    public static function meal_slots(): array {
        $labels = [
            'breakfast' => __( 'Breakfast', 'cookbook' ),
            'lunch'     => __( 'Lunch', 'cookbook' ),
            'dinner'    => __( 'Dinner', 'cookbook' ),
        ];
        return array_intersect_key( $labels, array_flip( self::MEAL_SLOTS ) );
    }

    public static function normalize_week_start( string $date = '' ): string {
        $timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
        try {
            $dt = $date !== ''
                ? new \DateTimeImmutable( $date, $timezone )
                : new \DateTimeImmutable( 'today', $timezone );
        } catch ( \Exception $e ) {
            $dt = new \DateTimeImmutable( 'today', $timezone );
        }

        $start_of_week = (int) get_option( 'start_of_week', 1 );
        $diff = ( (int) $dt->format( 'w' ) - $start_of_week + 7 ) % 7;
        if ( $diff > 0 ) {
            $dt = $dt->modify( '-' . $diff . ' days' );
        }
        return $dt->format( 'Y-m-d' );
    }

    public static function week_days( string $week_start ): array {
        $timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
        try {
            $start = new \DateTimeImmutable( $week_start, $timezone );
        } catch ( \Exception $e ) {
            $start = new \DateTimeImmutable( self::normalize_week_start(), $timezone );
        }

        $days = [];
        for ( $i = 0; $i < 7; $i++ ) {
            $day = $start->modify( '+' . $i . ' days' );
            $timestamp = $day->getTimestamp();
            $days[ $day->format( 'Y-m-d' ) ] = [
                'short' => wp_date( 'D', $timestamp ),
                'label' => wp_date( get_option( 'date_format' ), $timestamp ),
            ];
        }
        return $days;
    }

    public static function get_current_user_shopping_list_id( bool $create = true ): int {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return 0;
        }

        $ids = get_posts( [
            'post_type'      => self::SHOPPING_LIST_POST_TYPE,
            'post_status'    => [ 'publish', 'private', 'draft' ],
            'author'         => $user_id,
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ] );
        if ( $ids ) {
            return (int) $ids[0];
        }
        if ( ! $create ) {
            return 0;
        }

        $user  = get_userdata( $user_id );
        $title = $user
            ? sprintf(
                /* translators: %s: user display name */
                __( "%s's shopping list", 'cookbook' ),
                $user->display_name
            )
            : __( 'Shopping list', 'cookbook' );
        $post_id = wp_insert_post( [
            'post_type'   => self::SHOPPING_LIST_POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => $title,
            'post_author' => $user_id,
        ], true );
        if ( is_wp_error( $post_id ) ) {
            return 0;
        }
        update_post_meta( (int) $post_id, self::META_SHOPPING_ITEMS, [] );
        return (int) $post_id;
    }

    public static function get_user_week_plan_id( string $week_start, bool $create = true ): int {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return 0;
        }
        $week_start = self::normalize_week_start( $week_start );

        $ids = get_posts( [
            'post_type'      => self::WEEK_PLAN_POST_TYPE,
            'post_status'    => [ 'publish', 'private', 'draft' ],
            'author'         => $user_id,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- exact lookup for one user's weekly plan.
            'meta_query'     => [
                [
                    'key'   => self::META_WEEK_START,
                    'value' => $week_start,
                ],
            ],
        ] );
        if ( $ids ) {
            return (int) $ids[0];
        }
        if ( ! $create ) {
            return 0;
        }

        $timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
        try {
            $start = new \DateTimeImmutable( $week_start, $timezone );
        } catch ( \Exception $e ) {
            $start = new \DateTimeImmutable( self::normalize_week_start(), $timezone );
        }
        $title = sprintf(
            /* translators: %s: formatted date */
            __( 'Week of %s', 'cookbook' ),
            wp_date( get_option( 'date_format' ), $start->getTimestamp() )
        );
        $post_id = wp_insert_post( [
            'post_type'   => self::WEEK_PLAN_POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => $title,
            'post_author' => $user_id,
        ], true );
        if ( is_wp_error( $post_id ) ) {
            return 0;
        }
        update_post_meta( (int) $post_id, self::META_WEEK_START, $week_start );
        update_post_meta( (int) $post_id, self::META_WEEK_MEALS, [] );
        return (int) $post_id;
    }

    public static function get_shopping_items( int $list_id ): array {
        if ( ! $list_id ) {
            return [];
        }
        return self::normalize_shopping_items( (array) get_post_meta( $list_id, self::META_SHOPPING_ITEMS, true ) );
    }

    public static function get_week_meals( int $plan_id ): array {
        if ( ! $plan_id ) {
            return [];
        }
        $raw = get_post_meta( $plan_id, self::META_WEEK_MEALS, true );
        return is_array( $raw ) ? $raw : [];
    }

    public static function get_recipe_variation_parent( int $recipe_id ): ?\WP_Post {
        $post = $recipe_id ? get_post( $recipe_id ) : null;
        if ( ! $post || $post->post_type !== self::POST_TYPE || ! $post->post_parent ) {
            return null;
        }

        $parent = get_post( (int) $post->post_parent );
        if ( ! $parent || $parent->post_type !== self::POST_TYPE ) {
            return null;
        }

        return $parent;
    }

    public static function get_recipe_variation_root_id( int $recipe_id ): int {
        $post = $recipe_id ? get_post( $recipe_id ) : null;
        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            return 0;
        }

        $root_id = (int) $post->ID;
        $seen    = [];
        while ( $post && $post->post_type === self::POST_TYPE && $post->post_parent ) {
            $parent_id = (int) $post->post_parent;
            if ( isset( $seen[ $parent_id ] ) ) {
                break;
            }
            $seen[ $parent_id ] = true;

            $parent = get_post( $parent_id );
            if ( ! $parent || $parent->post_type !== self::POST_TYPE ) {
                break;
            }

            $root_id = (int) $parent->ID;
            $post    = $parent;
        }

        return $root_id;
    }

    public static function get_recipe_variation_family( int $recipe_id ): array {
        $root_id = self::get_recipe_variation_root_id( $recipe_id );
        $root    = $root_id ? get_post( $root_id ) : null;
        if ( ! $root || $root->post_type !== self::POST_TYPE ) {
            return [];
        }

        $family = [
            [
                'post'  => $root,
                'depth' => 0,
            ],
        ];
        $seen = [ $root_id => true ];
        self::collect_recipe_variation_descendants( $root_id, 1, $family, $seen );

        return $family;
    }

    public static function recipe_is_descendant_of( int $recipe_id, int $ancestor_id ): bool {
        if ( ! $recipe_id || ! $ancestor_id || $recipe_id === $ancestor_id ) {
            return false;
        }

        $post = get_post( $recipe_id );
        $seen = [];
        while ( $post && $post->post_type === self::POST_TYPE && $post->post_parent ) {
            $parent_id = (int) $post->post_parent;
            if ( $parent_id === $ancestor_id ) {
                return true;
            }
            if ( isset( $seen[ $parent_id ] ) ) {
                break;
            }
            $seen[ $parent_id ] = true;
            $post = get_post( $parent_id );
        }

        return false;
    }

    private static function collect_recipe_variation_descendants(
        int $parent_id,
        int $depth,
        array &$family,
        array &$seen
    ): void {
        $children = get_posts( [
            'post_type'      => self::POST_TYPE,
            'post_status'    => [ 'publish', 'draft' ],
            'posts_per_page' => -1,
            'post_parent'    => $parent_id,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        foreach ( $children as $child ) {
            if ( isset( $seen[ $child->ID ] ) ) {
                continue;
            }
            $seen[ $child->ID ] = true;
            $family[] = [
                'post'  => $child,
                'depth' => $depth,
            ];
            self::collect_recipe_variation_descendants( (int) $child->ID, $depth + 1, $family, $seen );
        }
    }

    public function handle_save(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'Not allowed.', 'cookbook' ), 403 );
        }
        check_admin_referer( 'cookbook_save' );

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $description = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
        $servings = isset( $_POST['servings'] ) ? max( 1, absint( $_POST['servings'] ) ) : 4;
        $prep = isset( $_POST['prep_time'] ) ? max( 0, absint( $_POST['prep_time'] ) ) : 0;
        $cook = isset( $_POST['cook_time'] ) ? max( 0, absint( $_POST['cook_time'] ) ) : 0;
        $source_url = isset( $_POST['source_url'] ) ? esc_url_raw( wp_unslash( $_POST['source_url'] ) ) : '';
        $image_url = isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '';
        $notes = isset( $_POST['notes'] ) ? wp_kses_post( wp_unslash( $_POST['notes'] ) ) : '';
        $parent_id = isset( $_POST['parent_id'] ) ? absint( $_POST['parent_id'] ) : 0;
        $parent_id = $this->sanitize_recipe_parent_id( $parent_id, $id );

        $ingredients = [];
        if ( isset( $_POST['ingredients'] ) && is_array( $_POST['ingredients'] ) ) {
            // Each field is sanitized inside the loop.
            $ingredient_rows = wp_unslash( $_POST['ingredients'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            foreach ( $ingredient_rows as $row ) {
                if ( ! is_array( $row ) ) continue;
                $name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
                if ( $name === '' ) continue;
                $ingredients[] = [
                    'amount' => isset( $row['amount'] ) ? sanitize_text_field( $row['amount'] ) : '',
                    'unit'   => isset( $row['unit'] ) ? sanitize_text_field( $row['unit'] ) : '',
                    'name'   => $name,
                    'notes'  => isset( $row['notes'] ) ? sanitize_text_field( $row['notes'] ) : '',
                ];
            }
        }

        $instructions = [];
        if ( isset( $_POST['instructions'] ) && is_array( $_POST['instructions'] ) ) {
            // Each step is run through wp_kses_post + Importer::clean_step below.
            $instruction_rows = wp_unslash( $_POST['instructions'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            foreach ( $instruction_rows as $step ) {
                $step = Importer::clean_step( wp_kses_post( $step ) );
                if ( $step !== '' ) {
                    $instructions[] = $step;
                }
            }
        }

        $postarr = [
            'post_type'    => self::POST_TYPE,
            'post_status'  => 'publish',
            'post_title'   => $title !== '' ? $title : __( 'Untitled recipe', 'cookbook' ),
            'post_content' => $description,
            'post_author'  => get_current_user_id(),
            'post_parent'  => $parent_id,
        ];
        if ( $id ) {
            $existing = get_post( $id );
            if ( ! $existing || $existing->post_type !== self::POST_TYPE ) {
                wp_die( esc_html__( 'Recipe not found.', 'cookbook' ), 404 );
            }
            $postarr['ID'] = $id;
            $post_id = wp_update_post( $postarr, true );
        } else {
            $post_id = wp_insert_post( $postarr, true );
        }
        if ( is_wp_error( $post_id ) ) {
            wp_die( esc_html( $post_id->get_error_message() ) );
        }

        update_post_meta( $post_id, self::META_SERVINGS, $servings );
        update_post_meta( $post_id, self::META_PREP, $prep );
        update_post_meta( $post_id, self::META_COOK, $cook );
        $this->persist_ingredients( $post_id, $ingredients );
        update_post_meta( $post_id, self::META_INSTRUCTIONS, $instructions );
        update_post_meta( $post_id, self::META_SOURCE_URL, $source_url );
        update_post_meta( $post_id, self::META_NOTES, $notes );

        $has_uploaded_image = ! empty( $_FILES['image']['name'] ) && empty( $_FILES['image']['error'] );
        $copy_thumbnail_from = isset( $_POST['copy_thumbnail_from'] ) ? absint( $_POST['copy_thumbnail_from'] ) : 0;
        if ( ! $id && $copy_thumbnail_from && $image_url === '' && ! $has_uploaded_image && empty( $_POST['remove_image'] ) ) {
            $copy_source = get_post( $copy_thumbnail_from );
            if ( $copy_source && $copy_source->post_type === self::POST_TYPE && has_post_thumbnail( $copy_source->ID ) ) {
                set_post_thumbnail( $post_id, get_post_thumbnail_id( $copy_source->ID ) );
            }
        }
        if ( ! empty( $_POST['remove_image'] ) ) {
            delete_post_thumbnail( $post_id );
        }
        if ( $image_url !== '' ) {
            $this->sideload_image_to_post( $post_id, $image_url );
        }
        if ( $has_uploaded_image ) {
            $this->attach_uploaded_image_as_thumbnail( $post_id );
        }

        if ( isset( $_POST['categories'] ) ) {
            $cats = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['categories'] ) );
            wp_set_object_terms( $post_id, $this->resolve_term_ids( $cats, self::TAX_CATEGORY ), self::TAX_CATEGORY );
        }
        if ( isset( $_POST['cuisines'] ) ) {
            $cui = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['cuisines'] ) );
            wp_set_object_terms( $post_id, $this->resolve_term_ids( $cui, self::TAX_CUISINE ), self::TAX_CUISINE );
        }
        if ( isset( $_POST['tags'] ) ) {
            $tags = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['tags'] ) ) ) ) );
            wp_set_object_terms( $post_id, $tags, self::TAX_TAG );
        }

        if ( $id ) {
            $this->save_recipe_revision_snapshot( $post_id );
        }

        wp_safe_redirect( home_url( '/' . $this->get_url_path() . '/recipe/' . $post_id ) );
        exit;
    }

    private function sanitize_recipe_parent_id( int $parent_id, int $post_id = 0 ): int {
        if ( ! $parent_id ) {
            return 0;
        }

        $parent = get_post( $parent_id );
        if ( ! $parent || $parent->post_type !== self::POST_TYPE ) {
            return 0;
        }
        if ( $post_id && $parent_id === $post_id ) {
            return 0;
        }
        if ( $post_id && self::recipe_is_descendant_of( $parent_id, $post_id ) ) {
            return 0;
        }

        return $parent_id;
    }

    /**
     * Sideload an external image URL and set it as the post's featured image.
     * Returns the attachment ID on success.
     */
    private function sideload_image_to_post( int $post_id, string $url ): ?int {
        if ( $url === '' || ! filter_var( $url, FILTER_VALIDATE_URL ) ) return null;

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url( $url, 20 );
        if ( is_wp_error( $tmp ) ) return null;

        $name = basename( wp_parse_url( $url, PHP_URL_PATH ) ?: 'recipe-image' );
        $name = sanitize_file_name( $name ) ?: 'recipe-image';
        if ( ! preg_match( '/\.(jpe?g|png|gif|webp|avif)$/i', $name ) ) {
            $name .= '.jpg';
        }

        $file_array = [ 'name' => $name, 'tmp_name' => $tmp ];
        $attachment_id = media_handle_sideload( $file_array, $post_id );
        if ( is_wp_error( $attachment_id ) ) {
            wp_delete_file( $tmp );
            return null;
        }
        set_post_thumbnail( $post_id, $attachment_id );
        return (int) $attachment_id;
    }

    private function attach_uploaded_image_as_thumbnail( int $post_id ): ?int {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload( 'image', $post_id );
        if ( is_wp_error( $attachment_id ) ) return null;
        set_post_thumbnail( $post_id, $attachment_id );
        return (int) $attachment_id;
    }

    /**
     * Save ingredient rows and sync the recipe_ingredient taxonomy.
     *
     * The original typed name is kept on the row for display; lookup is by
     * slug (sanitize_title folds case + diacritics) so "Tomatoes" and
     * "tomatoes" share a term and "Knödelbrot" stays "Knödelbrot".
     */
    private function persist_ingredients( int $post_id, array $rows ): void {
        $term_ids = [];
        $clean    = [];
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) continue;
            $name = isset( $row['name'] ) ? (string) $row['name'] : '';
            if ( $name === '' ) continue;
            $term_id = $this->resolve_ingredient_term( $name );
            $clean[] = [
                'amount'  => isset( $row['amount'] ) ? (string) $row['amount'] : '',
                'unit'    => isset( $row['unit'] ) ? (string) $row['unit'] : '',
                'name'    => $name,
                'notes'   => isset( $row['notes'] ) ? (string) $row['notes'] : '',
                'term_id' => $term_id ?: 0,
            ];
            if ( $term_id ) {
                $term_ids[ $term_id ] = true;
            }
        }
        update_post_meta( $post_id, self::META_INGREDIENTS, $clean );
        wp_set_object_terms( $post_id, array_keys( $term_ids ), self::TAX_INGREDIENT, false );
    }

    private function save_recipe_revision_snapshot( int $post_id ): void {
        if ( function_exists( 'wp_save_post_revision' ) ) {
            wp_save_post_revision( $post_id );
        }
    }

    /**
     * Find or create the recipe_ingredient term for a free-form name.
     *
     * Dedup by slug (sanitize_title): "Tomatoes"/"tomatoes" collapse, "Knödelbrot"
     * keeps its umlauts as the display name while sharing slug "knodelbrot" with
     * any future "knödelbrot" entry. No automatic singularization or stopword
     * stripping — grouping similar ingredients is a manual step via the
     * hierarchical taxonomy's parent/child UI.
     */
    private function resolve_ingredient_term( string $name ): ?int {
        $name = trim( $name );
        if ( $name === '' ) return null;
        $slug = sanitize_title( $name );
        if ( $slug === '' ) return null;

        $term = get_term_by( 'slug', $slug, self::TAX_INGREDIENT );
        if ( $term && ! is_wp_error( $term ) ) {
            return (int) $term->term_id;
        }
        $created = wp_insert_term( $name, self::TAX_INGREDIENT, [ 'slug' => $slug ] );
        if ( is_wp_error( $created ) ) {
            // Race: another request just created it, or slug collision with a different name.
            $term = get_term_by( 'slug', $slug, self::TAX_INGREDIENT );
            return $term ? (int) $term->term_id : null;
        }
        return isset( $created['term_id'] ) ? (int) $created['term_id'] : null;
    }

    private function resolve_term_ids( array $values, string $taxonomy ): array {
        $ids = [];
        $expanded = [];
        foreach ( $values as $value ) {
            if ( $value === '' ) continue;
            if ( ctype_digit( $value ) ) {
                $expanded[] = $value;
                continue;
            }
            foreach ( array_map( 'trim', explode( ',', $value ) ) as $part ) {
                if ( $part !== '' ) $expanded[] = $part;
            }
        }
        foreach ( $expanded as $value ) {
            if ( ctype_digit( $value ) ) {
                $ids[] = (int) $value;
                continue;
            }
            $term = term_exists( $value, $taxonomy );
            if ( ! $term ) {
                $term = wp_insert_term( $value, $taxonomy );
            }
            if ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) {
                $ids[] = (int) $term['term_id'];
            }
        }
        return array_values( array_unique( $ids ) );
    }

    public function handle_delete(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'delete_posts' ) ) {
            wp_die( esc_html__( 'Not allowed.', 'cookbook' ), 403 );
        }
        check_admin_referer( 'cookbook_delete' );
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $post = $id ? get_post( $id ) : null;
        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            wp_die( esc_html__( 'Recipe not found.', 'cookbook' ), 404 );
        }
        wp_trash_post( $id );
        wp_safe_redirect( home_url( '/' . $this->get_url_path() . '/' ) );
        exit;
    }

    public function handle_settings(): void {
        if ( ! is_user_logged_in() ) {
            wp_die( esc_html__( 'Not allowed.', 'cookbook' ), 403 );
        }
        check_admin_referer( 'cookbook_settings' );
        $pref = isset( $_POST['unit_preference'] ) ? sanitize_text_field( wp_unslash( $_POST['unit_preference'] ) ) : 'metric';
        if ( ! in_array( $pref, [ 'metric', 'imperial' ], true ) ) {
            $pref = 'metric';
        }
        update_user_meta( get_current_user_id(), self::USER_PREF_UNITS, $pref );
        wp_safe_redirect( home_url( '/' . $this->get_url_path() . '/settings?saved=1' ) );
        exit;
    }

    /**
     * Shared import flow used by the import form, browser extension, and abilities.
     *
     * @return int|\WP_Error Draft recipe ID on success.
     */
    private function import_recipe_to_draft( string $url = '', string $paste = '', string $image_url = '', string $html = '' ) {
        $parsed = $this->parse_recipe_input( $url, $paste, $html );
        if ( is_wp_error( $parsed ) ) {
            return $parsed;
        }

        if ( $image_url !== '' ) {
            $parsed['image_url'] = $image_url;
        }

        return $this->create_recipe_draft_from_parsed( $parsed, $url );
    }

    /**
     * Parse recipe input from captured HTML, URL, or pasted text in priority order.
     *
     * @return array|\WP_Error
     */
    private function parse_recipe_input( string $url = '', string $paste = '', string $html = '' ) {
        if ( $url === '' && trim( $paste ) === '' && trim( $html ) === '' ) {
            return new \WP_Error( 'cookbook_import_empty', __( 'Provide a source URL or pasted recipe text.', 'cookbook' ) );
        }

        $parsed = null;
        if ( $html !== '' ) {
            $parsed = Importer::from_html( $html );
        }
        if ( ! $parsed && $url !== '' ) {
            $parsed = Importer::from_url( $url );
        }
        if ( ! $parsed && trim( $paste ) !== '' ) {
            $parsed = Importer::from_text( $paste );
        }
        if ( ! $parsed ) {
            return new \WP_Error( 'cookbook_import_parse_failed', __( 'Could not parse a recipe from that input.', 'cookbook' ) );
        }

        return $parsed;
    }

    /**
     * Create a draft recipe from an already parsed payload.
     *
     * @return int|\WP_Error
     */
    private function create_recipe_draft_from_parsed( array $parsed, string $url = '' ) {
        $post_id = wp_insert_post( [
            'post_type'    => self::POST_TYPE,
            'post_status'  => 'draft',
            'post_title'   => $parsed['title'] ?: __( 'Imported recipe', 'cookbook' ),
            'post_content' => $parsed['description'] ?? '',
            'post_author'  => get_current_user_id(),
        ], true );
        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        $this->apply_parsed_payload( (int) $post_id, $parsed, $url, false );
        return (int) $post_id;
    }

    public function handle_import(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'Not allowed.', 'cookbook' ), 403 );
        }
        check_admin_referer( 'cookbook_import' );

        $url   = isset( $_POST['source_url'] ) ? esc_url_raw( wp_unslash( $_POST['source_url'] ) ) : '';
        // $paste is HTML-ish recipe text; sanitize via wp_kses_post which preserves line breaks.
        $paste = isset( $_POST['paste'] ) ? wp_kses_post( wp_unslash( $_POST['paste'] ) ) : '';

        $image_url = isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '';
        $post_id = $this->import_recipe_to_draft( $url, $paste, $image_url );
        if ( is_wp_error( $post_id ) && in_array( $post_id->get_error_code(), [ 'cookbook_import_empty', 'cookbook_import_parse_failed' ], true ) ) {
            $this->redirect_import_parse_error( $url );
        }
        if ( is_wp_error( $post_id ) ) {
            wp_die( esc_html( $post_id->get_error_message() ) );
        }

        wp_safe_redirect( home_url( '/' . $this->get_url_path() . '/recipe/' . $post_id . '/edit' ) );
        exit;
    }

    /**
     * Re-fetch a recipe from its stored source URL and overwrite parsed fields.
     *
     * Only fields the parser actually returned are touched, so that a partial
     * parse (e.g. missing prep_time) doesn't clobber the recipe's existing data.
     * Notes, taxonomy assignments and post status are always left alone.
     */
    public function handle_refetch(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'Not allowed.', 'cookbook' ), 403 );
        }
        check_admin_referer( 'cookbook_refetch' );

        $id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $post = $id ? get_post( $id ) : null;
        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            wp_die( esc_html__( 'Recipe not found.', 'cookbook' ), 404 );
        }
        $url = (string) get_post_meta( $id, self::META_SOURCE_URL, true );
        if ( $url === '' || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            wp_safe_redirect( home_url( '/' . $this->get_url_path() . '/recipe/' . $id . '?refetch=no_url' ) );
            exit;
        }

        $parsed = $this->parse_recipe_input( $url );
        if ( is_wp_error( $parsed ) ) {
            wp_safe_redirect( home_url( '/' . $this->get_url_path() . '/recipe/' . $id . '?refetch=parse_error' ) );
            exit;
        }

        $update = [ 'ID' => $id ];
        if ( ! empty( $parsed['title'] ) ) {
            $update['post_title'] = $parsed['title'];
        }
        if ( ! empty( $parsed['description'] ) ) {
            $update['post_content'] = $parsed['description'];
        }
        if ( count( $update ) > 1 ) {
            wp_update_post( $update );
        }

        $this->apply_parsed_payload( $id, $parsed, $url, true );

        wp_safe_redirect( home_url( '/' . $this->get_url_path() . '/recipe/' . $id . '?refetch=ok' ) );
        exit;
    }

    public function handle_replace_ingredient(): void {
        if ( ! is_user_logged_in() ) {
            wp_die( esc_html__( 'Not allowed.', 'cookbook' ), 403 );
        }

        $id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $index = isset( $_POST['ingredient_index'] ) ? absint( $_POST['ingredient_index'] ) : 0;
        check_admin_referer( 'cookbook_replace_ingredient_' . $id . '_' . $index );

        $post = $id ? get_post( $id ) : null;
        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            wp_die( esc_html__( 'Recipe not found.', 'cookbook' ), 404 );
        }
        if ( ! current_user_can( 'edit_post', $id ) ) {
            wp_die( esc_html__( 'Not allowed.', 'cookbook' ), 403 );
        }

        $ingredients = (array) get_post_meta( $id, self::META_INGREDIENTS, true );
        if ( ! isset( $ingredients[ $index ] ) || ! is_array( $ingredients[ $index ] ) ) {
            wp_die( esc_html__( 'Ingredient not found.', 'cookbook' ), 404 );
        }

        $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        if ( $name === '' ) {
            wp_die( esc_html__( 'Replacement ingredient is required.', 'cookbook' ), 400 );
        }

        $ingredients[ $index ] = [
            'amount' => isset( $_POST['amount'] ) ? sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : '',
            'unit'   => isset( $_POST['unit'] ) ? sanitize_text_field( wp_unslash( $_POST['unit'] ) ) : '',
            'name'   => $name,
            'notes'  => isset( $_POST['notes'] ) ? sanitize_text_field( wp_unslash( $_POST['notes'] ) ) : '',
        ];

        $this->save_recipe_revision_snapshot( $id );
        $this->persist_ingredients( $id, $ingredients );
        $this->save_recipe_revision_snapshot( $id );

        wp_safe_redirect( add_query_arg( 'replaced', '1', home_url( '/' . $this->get_url_path() . '/recipe/' . $id . '#ingredients' ) ) );
        exit;
    }

    public function handle_add_to_shopping_list(): void {
        if ( ! is_user_logged_in() ) {
            wp_die( esc_html__( 'Not allowed.', 'cookbook' ), 403 );
        }
        check_admin_referer( 'cookbook_add_to_shopping_list' );

        $recipe_id = isset( $_POST['recipe_id'] ) ? absint( $_POST['recipe_id'] ) : 0;
        $servings  = isset( $_POST['servings'] ) ? max( 1, absint( $_POST['servings'] ) ) : 0;
        $post      = $this->get_recipe_or_die( $recipe_id );

        $items = $this->collect_recipe_shopping_items( $recipe_id, $servings );
        $added = $this->add_items_to_shopping_list( $items );

        wp_safe_redirect( add_query_arg( [
            'shopping' => 'added',
            'items'    => $added,
        ], home_url( '/' . $this->get_url_path() . '/recipe/' . $post->ID ) ) );
        exit;
    }

    public function handle_update_shopping_list(): void {
        if ( ! is_user_logged_in() ) {
            wp_die( esc_html__( 'Not allowed.', 'cookbook' ), 403 );
        }
        check_admin_referer( 'cookbook_update_shopping_list' );

        $command = isset( $_POST['list_command'] ) ? sanitize_text_field( wp_unslash( $_POST['list_command'] ) ) : 'save';
        $return_mode = isset( $_POST['return_mode'] ) ? sanitize_key( wp_unslash( $_POST['return_mode'] ) ) : '';
        $redirect_args = [ 'saved' => '1' ];
        if ( $return_mode === 'shop' ) {
            $redirect_args['mode'] = 'shop';
        }
        $list_id = isset( $_POST['list_id'] ) ? absint( $_POST['list_id'] ) : 0;
        if ( ! $list_id && in_array( $command, [ 'clear_all', 'clear_checked' ], true ) ) {
            wp_safe_redirect( add_query_arg( $redirect_args, home_url( '/' . $this->get_url_path() . '/shopping-list' ) ) );
            exit;
        }
        $list_id = $list_id ?: self::get_current_user_shopping_list_id( true );
        $this->get_owned_post_or_die( $list_id, self::SHOPPING_LIST_POST_TYPE );

        if ( $command === 'clear_all' ) {
            $items = [];
        } else {
            $rows = isset( $_POST['items'] ) && is_array( $_POST['items'] )
                ? wp_unslash( $_POST['items'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                : [];
            $items = self::normalize_shopping_items( $rows );

            $new_rows = isset( $_POST['new_items'] ) && is_array( $_POST['new_items'] )
                ? wp_unslash( $_POST['new_items'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                : [];
            $items = array_merge( $items, self::normalize_shopping_items( $new_rows ) );

            if ( $command === 'clear_checked' ) {
                $items = array_values( array_filter( $items, function( $item ) {
                    return empty( $item['checked'] );
                } ) );
            }
        }

        update_post_meta( $list_id, self::META_SHOPPING_ITEMS, $items );
        wp_safe_redirect( add_query_arg( $redirect_args, home_url( '/' . $this->get_url_path() . '/shopping-list' ) ) );
        exit;
    }

    public function handle_save_planner(): void {
        if ( ! is_user_logged_in() ) {
            wp_die( esc_html__( 'Not allowed.', 'cookbook' ), 403 );
        }
        check_admin_referer( 'cookbook_save_planner' );

        $week_start = isset( $_POST['week_start'] ) ? sanitize_text_field( wp_unslash( $_POST['week_start'] ) ) : '';
        $week_start = self::normalize_week_start( $week_start );
        $plan_id    = isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0;

        if ( $plan_id ) {
            $plan = $this->get_owned_post_or_die( $plan_id, self::WEEK_PLAN_POST_TYPE );
            $stored_week_start = (string) get_post_meta( $plan->ID, self::META_WEEK_START, true );
            if ( self::normalize_week_start( $stored_week_start ) !== $week_start ) {
                $plan_id = self::get_user_week_plan_id( $week_start, true );
            }
        } else {
            $plan_id = self::get_user_week_plan_id( $week_start, true );
        }
        $this->get_owned_post_or_die( $plan_id, self::WEEK_PLAN_POST_TYPE );

        $raw_meals = isset( $_POST['meals'] ) && is_array( $_POST['meals'] )
            ? wp_unslash( $_POST['meals'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            : [];
        $raw_labels = isset( $_POST['meal_labels'] ) && is_array( $_POST['meal_labels'] )
            ? wp_unslash( $_POST['meal_labels'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            : [];
        $meals = $this->sanitize_planner_meals( $raw_meals, $week_start, $raw_labels );

        update_post_meta( $plan_id, self::META_WEEK_START, $week_start );
        update_post_meta( $plan_id, self::META_WEEK_MEALS, $meals );
        wp_safe_redirect( add_query_arg( [
            'week'  => $week_start,
            'saved' => '1',
        ], home_url( '/' . $this->get_url_path() . '/planner' ) ) );
        exit;
    }

    public function handle_add_planner_to_shopping_list(): void {
        if ( ! is_user_logged_in() ) {
            wp_die( esc_html__( 'Not allowed.', 'cookbook' ), 403 );
        }
        check_admin_referer( 'cookbook_add_planner_to_shopping_list' );

        $week_start = isset( $_POST['week_start'] ) ? sanitize_text_field( wp_unslash( $_POST['week_start'] ) ) : '';
        $week_start = self::normalize_week_start( $week_start );
        $plan_id    = self::get_user_week_plan_id( $week_start, false );
        $items      = [];

        if ( $plan_id ) {
            $this->get_owned_post_or_die( $plan_id, self::WEEK_PLAN_POST_TYPE );
            $meals = self::get_week_meals( $plan_id );
            foreach ( self::week_days( $week_start ) as $date => $day ) {
                foreach ( array_keys( self::meal_slots() ) as $slot ) {
                    $recipe_id = isset( $meals[ $date ][ $slot ] ) ? absint( $meals[ $date ][ $slot ] ) : 0;
                    if ( $recipe_id ) {
                        $items = array_merge( $items, $this->collect_recipe_shopping_items( $recipe_id, 0 ) );
                    }
                }
            }
        }

        $added = $this->add_items_to_shopping_list( $items );
        wp_safe_redirect( add_query_arg( [
            'week'     => $week_start,
            'shopping' => 'added',
            'items'    => $added,
        ], home_url( '/' . $this->get_url_path() . '/planner' ) ) );
        exit;
    }

    private function get_owned_post_or_die( int $post_id, string $post_type ): \WP_Post {
        $post = $post_id ? get_post( $post_id ) : null;
        if ( ! $post || $post->post_type !== $post_type ) {
            wp_die( esc_html__( 'Not found.', 'cookbook' ), 404 );
        }
        if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'Not allowed.', 'cookbook' ), 403 );
        }
        return $post;
    }

    private function get_recipe_or_die( int $recipe_id ): \WP_Post {
        $post = $recipe_id ? get_post( $recipe_id ) : null;
        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            wp_die( esc_html__( 'Recipe not found.', 'cookbook' ), 404 );
        }
        return $post;
    }

    private function collect_recipe_shopping_items( int $recipe_id, int $servings = 0 ): array {
        $post = $this->get_recipe_or_die( $recipe_id );
        $ingredients = (array) get_post_meta( $recipe_id, self::META_INGREDIENTS, true );
        if ( ! $ingredients ) {
            return [];
        }

        $base_servings = max( 1, (int) get_post_meta( $recipe_id, self::META_SERVINGS, true ) ?: 4 );
        $wanted_servings = $servings > 0 ? $servings : $base_servings;
        $scale = $wanted_servings / $base_servings;
        $preference = self::get_user_unit_preference();
        $items = [];

        foreach ( $ingredients as $ingredient ) {
            if ( ! is_array( $ingredient ) || empty( $ingredient['name'] ) ) {
                continue;
            }
            $rendered = Units::render_ingredient( $ingredient, $scale, $preference );
            $items[] = [
                'amount'              => (string) ( $rendered['amount'] ?? '' ),
                'unit'                => (string) ( $rendered['unit'] ?? '' ),
                'name'                => (string) ( $rendered['name'] ?? '' ),
                'notes'               => (string) ( $rendered['notes'] ?? '' ),
                'checked'             => false,
                'source_recipe_id'    => $recipe_id,
                'source_recipe_title' => get_the_title( $post ),
            ];
        }
        return self::normalize_shopping_items( $items );
    }

    private function add_items_to_shopping_list( array $incoming ): int {
        $incoming = self::normalize_shopping_items( $incoming );
        if ( ! $incoming ) {
            return 0;
        }

        $list_id = self::get_current_user_shopping_list_id( true );
        if ( ! $list_id ) {
            return 0;
        }
        $this->get_owned_post_or_die( $list_id, self::SHOPPING_LIST_POST_TYPE );

        $existing = self::get_shopping_items( $list_id );
        $items = $this->merge_shopping_items( $existing, $incoming );
        update_post_meta( $list_id, self::META_SHOPPING_ITEMS, $items );
        return count( $incoming );
    }

    private static function normalize_shopping_items( array $items ): array {
        $normalized = [];
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $name = isset( $item['name'] ) ? sanitize_text_field( (string) $item['name'] ) : '';
            if ( $name === '' ) {
                continue;
            }
            $id = isset( $item['id'] ) ? sanitize_key( (string) $item['id'] ) : '';
            if ( $id === '' ) {
                $id = wp_generate_uuid4();
            }
            $normalized[] = [
                'id'                  => $id,
                'amount'              => isset( $item['amount'] ) ? sanitize_text_field( (string) $item['amount'] ) : '',
                'unit'                => isset( $item['unit'] ) ? sanitize_text_field( (string) $item['unit'] ) : '',
                'name'                => $name,
                'notes'               => isset( $item['notes'] ) ? sanitize_text_field( (string) $item['notes'] ) : '',
                'checked'             => ! empty( $item['checked'] ),
                'source_recipe_id'    => isset( $item['source_recipe_id'] ) ? absint( $item['source_recipe_id'] ) : 0,
                'source_recipe_title' => isset( $item['source_recipe_title'] ) ? sanitize_text_field( (string) $item['source_recipe_title'] ) : '',
            ];
        }
        return $normalized;
    }

    private function merge_shopping_items( array $existing, array $incoming ): array {
        $items = self::normalize_shopping_items( $existing );
        $index = [];
        foreach ( $items as $i => $item ) {
            $index[ $this->shopping_item_key( $item ) ][] = $i;
        }

        foreach ( self::normalize_shopping_items( $incoming ) as $item ) {
            $key = $this->shopping_item_key( $item );
            $matching_index = null;
            foreach ( $index[ $key ] ?? [] as $candidate_index ) {
                if ( $this->can_combine_shopping_items( $items[ $candidate_index ], $item ) ) {
                    $matching_index = $candidate_index;
                    break;
                }
            }

            if ( $matching_index !== null ) {
                $i = $matching_index;
                $existing_amount = Units::parse_amount( $items[ $i ]['amount'] );
                $incoming_amount = Units::parse_amount( $item['amount'] );
                if ( $existing_amount !== null && $incoming_amount !== null ) {
                    $items[ $i ]['amount'] = Units::format_number( $existing_amount + $incoming_amount, 2 );
                }
                $items[ $i ]['checked'] = false;
                if (
                    $items[ $i ]['source_recipe_id']
                    && $item['source_recipe_id']
                    && $items[ $i ]['source_recipe_id'] !== $item['source_recipe_id']
                ) {
                    $items[ $i ]['source_recipe_id'] = 0;
                    $items[ $i ]['source_recipe_title'] = __( 'Multiple recipes', 'cookbook' );
                }
                continue;
            }

            $items[] = $item;
            $index[ $key ][] = count( $items ) - 1;
        }

        return array_values( $items );
    }

    private function shopping_item_key( array $item ): string {
        $name = sanitize_title( $item['name'] ?? '' );
        $unit = Units::normalize_unit( (string) ( $item['unit'] ?? '' ) );
        $notes = sanitize_title( $item['notes'] ?? '' );
        return implode( '|', [ $name, $unit, $notes ] );
    }

    private function can_combine_shopping_items( array $a, array $b ): bool {
        if ( $this->shopping_item_key( $a ) !== $this->shopping_item_key( $b ) ) {
            return false;
        }
        $amount_a = Units::parse_amount( $a['amount'] ?? '' );
        $amount_b = Units::parse_amount( $b['amount'] ?? '' );
        if ( $amount_a !== null && $amount_b !== null ) {
            return true;
        }
        return trim( (string) ( $a['amount'] ?? '' ) ) === '' && trim( (string) ( $b['amount'] ?? '' ) ) === '';
    }

    private function sanitize_planner_meals( array $raw_meals, string $week_start, array $raw_labels = [] ): array {
        $meals = [];
        $days = array_keys( self::week_days( $week_start ) );
        $slots = array_keys( self::meal_slots() );

        foreach ( $days as $date ) {
            foreach ( $slots as $slot ) {
                $recipe_id = isset( $raw_meals[ $date ][ $slot ] ) ? absint( $raw_meals[ $date ][ $slot ] ) : 0;
                $has_label = isset( $raw_labels[ $date ][ $slot ] );
                $label = $has_label ? sanitize_text_field( (string) $raw_labels[ $date ][ $slot ] ) : '';
                if ( $has_label ) {
                    if ( trim( $label ) === '' ) {
                        continue;
                    }
                    $recipe_id = $this->resolve_planner_recipe_label( $label );
                }
                if ( $recipe_id && $this->recipe_exists( $recipe_id ) ) {
                    $meals[ $date ][ $slot ] = $recipe_id;
                }
            }
        }
        return $meals;
    }

    private function resolve_planner_recipe_label( string $label ): int {
        $label = trim( $label );
        if ( $label === '' ) {
            return 0;
        }
        if ( preg_match( '/\(#(\d+)\)$/', $label, $m ) ) {
            $recipe_id = absint( $m[1] );
            return $this->recipe_exists( $recipe_id ) ? $recipe_id : 0;
        }

        $candidates = get_posts( [
            'post_type'      => self::POST_TYPE,
            'post_status'    => [ 'publish', 'draft' ],
            'posts_per_page' => 10,
            's'              => $label,
        ] );
        foreach ( $candidates as $candidate ) {
            if ( get_the_title( $candidate ) === $label ) {
                return (int) $candidate->ID;
            }
        }
        return 0;
    }

    private function recipe_exists( int $recipe_id ): bool {
        $post = $recipe_id ? get_post( $recipe_id ) : null;
        return $post && $post->post_type === self::POST_TYPE;
    }

    /**
     * Collapse one or more ingredient terms into a single target term.
     *
     * For each source term: rewrites the per-recipe `_recipe_ingredients` meta
     * rows so any reference to the source's term_id points at the target,
     * reassigns the term itself on each recipe, reparents any children of the
     * source onto the target, then deletes the source. The source ingredient
     * names are preserved in the meta rows (only the term_id link moves), so
     * recipes still display the wording the user originally typed.
     */
    public function handle_merge_ingredients(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_categories' ) ) {
            wp_die( esc_html__( 'Not allowed.', 'cookbook' ), 403 );
        }
        check_admin_referer( 'cookbook_manage_ingredients' );

        $sources = isset( $_POST['source_ids'] ) && is_array( $_POST['source_ids'] )
            ? array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST['source_ids'] ) ) ) ) )
            : [];
        $target = isset( $_POST['target_id'] ) ? absint( $_POST['target_id'] ) : 0;
        $sources = array_values( array_diff( $sources, [ $target ] ) );

        $merged = 0;
        if ( $target && get_term( $target, self::TAX_INGREDIENT ) instanceof \WP_Term && $sources ) {
            foreach ( $sources as $source_id ) {
                if ( $this->merge_ingredient_term( $source_id, $target ) ) {
                    $merged++;
                }
            }
        }

        $back = home_url( '/' . $this->get_url_path() . '/manage-ingredients' );
        wp_safe_redirect( add_query_arg( [ 'merged' => $merged ], $back ) );
        exit;
    }

    /**
     * Merge a single source term into a target term. Returns true on success.
     */
    private function merge_ingredient_term( int $source_id, int $target_id ): bool {
        if ( $source_id === $target_id ) return false;
        $source = get_term( $source_id, self::TAX_INGREDIENT );
        if ( ! $source instanceof \WP_Term ) return false;

        $posts = get_posts( [
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- one-time admin operation, exact-term filter.
            'tax_query'      => [
                [ 'taxonomy' => self::TAX_INGREDIENT, 'field' => 'term_id', 'terms' => $source_id ],
            ],
        ] );
        foreach ( $posts as $post_id ) {
            $rows    = (array) get_post_meta( $post_id, self::META_INGREDIENTS, true );
            $changed = false;
            foreach ( $rows as &$row ) {
                if ( ! is_array( $row ) ) continue;
                if ( isset( $row['term_id'] ) && (int) $row['term_id'] === $source_id ) {
                    $row['term_id'] = $target_id;
                    $changed        = true;
                }
            }
            unset( $row );
            if ( $changed ) {
                update_post_meta( $post_id, self::META_INGREDIENTS, $rows );
            }
            wp_remove_object_terms( $post_id, $source_id, self::TAX_INGREDIENT );
            wp_add_object_terms( $post_id, $target_id, self::TAX_INGREDIENT );
        }

        // Reparent children of the source onto the target so the hierarchy survives the delete.
        $children = get_terms( [
            'taxonomy'   => self::TAX_INGREDIENT,
            'hide_empty' => false,
            'parent'     => $source_id,
            'fields'     => 'ids',
        ] );
        if ( is_array( $children ) ) {
            foreach ( $children as $child_id ) {
                wp_update_term( (int) $child_id, self::TAX_INGREDIENT, [ 'parent' => $target_id ] );
            }
        }

        $deleted = wp_delete_term( $source_id, self::TAX_INGREDIENT );
        return $deleted === true;
    }

    /**
     * Re-parent one or more ingredient terms onto a target (group as hierarchy).
     */
    public function handle_group_ingredients(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_categories' ) ) {
            wp_die( esc_html__( 'Not allowed.', 'cookbook' ), 403 );
        }
        check_admin_referer( 'cookbook_manage_ingredients' );

        $sources = isset( $_POST['source_ids'] ) && is_array( $_POST['source_ids'] )
            ? array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST['source_ids'] ) ) ) ) )
            : [];
        $target = isset( $_POST['target_id'] ) ? absint( $_POST['target_id'] ) : 0;
        $sources = array_values( array_diff( $sources, [ $target ] ) );

        $grouped = 0;
        if ( $target === 0 || ( get_term( $target, self::TAX_INGREDIENT ) instanceof \WP_Term ) ) {
            foreach ( $sources as $source_id ) {
                $res = wp_update_term( $source_id, self::TAX_INGREDIENT, [ 'parent' => $target ] );
                if ( ! is_wp_error( $res ) ) $grouped++;
            }
        }

        $back = home_url( '/' . $this->get_url_path() . '/manage-ingredients' );
        wp_safe_redirect( add_query_arg( [ 'grouped' => $grouped ], $back ) );
        exit;
    }

    /**
     * Rename a single ingredient term. The slug is left untouched so existing
     * /ingredient/{slug} URLs and slug-based dedup still work.
     */
    public function handle_rename_ingredient(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_categories' ) ) {
            wp_die( esc_html__( 'Not allowed.', 'cookbook' ), 403 );
        }
        check_admin_referer( 'cookbook_manage_ingredients' );

        $term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
        $name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $renamed = 0;
        if ( $term_id && $name !== '' ) {
            $res = wp_update_term( $term_id, self::TAX_INGREDIENT, [ 'name' => $name ] );
            if ( ! is_wp_error( $res ) ) $renamed = 1;
        }

        $back = home_url( '/' . $this->get_url_path() . '/manage-ingredients' );
        wp_safe_redirect( add_query_arg( [ 'renamed' => $renamed ], $back ) );
        exit;
    }

    /**
     * Write the parts of a parsed-recipe payload that we store on the post.
     *
     * @param bool $only_if_present  When true, skip writes for fields the parser
     *                               left empty — used by refetch so a partial
     *                               parse doesn't wipe existing data.
     */
    private function apply_parsed_payload( int $post_id, array $parsed, string $url, bool $only_if_present ): void {
        if ( ! $only_if_present || ! empty( $parsed['servings'] ) ) {
            update_post_meta( $post_id, self::META_SERVINGS, (int) ( $parsed['servings'] ?? 4 ) );
        }
        if ( ! $only_if_present || ! empty( $parsed['prep_time'] ) ) {
            update_post_meta( $post_id, self::META_PREP, (int) ( $parsed['prep_time'] ?? 0 ) );
        }
        if ( ! $only_if_present || ! empty( $parsed['cook_time'] ) ) {
            update_post_meta( $post_id, self::META_COOK, (int) ( $parsed['cook_time'] ?? 0 ) );
        }
        if ( ! $only_if_present || ! empty( $parsed['ingredients'] ) ) {
            $this->persist_ingredients( $post_id, $parsed['ingredients'] ?? [] );
        }
        if ( ! $only_if_present || ! empty( $parsed['instructions'] ) ) {
            update_post_meta( $post_id, self::META_INSTRUCTIONS, $parsed['instructions'] ?? [] );
        }
        if ( $url !== '' ) {
            update_post_meta( $post_id, self::META_SOURCE_URL, $url );
        }
        if ( ! empty( $parsed['image_url'] ) ) {
            $this->sideload_image_to_post( $post_id, (string) $parsed['image_url'] );
        }
    }

    /**
     * Friends browser-extension integration.
     *
     * Adds a "Save to Recipes" action to the Friends extension popup. When the
     * user clicks it, the extension POSTs the current page's HTML to our
     * endpoint with the URL as a query arg. We parse it server-side using the
     * same Importer used for the manual import form.
     *
     * @see https://github.com/akirk/browser-extension
     */
    public function register_browser_extension_action( $actions ) {
        if ( ! is_array( $actions ) ) $actions = [];
        $actions[] = [
            'name'     => __( 'Save as Recipe', 'cookbook' ),
            'url'      => home_url( '/?cookbook-collect={current_url}' ),
            'method'   => 'POST',
            'fields'   => [ 'body' => '{page_html}' ],
            'category' => __( 'Recipes', 'cookbook' ),
        ];
        return $actions;
    }

    public function handle_extension_save(): void {
        // The browser extension authenticates via the user's logged-in session
        // (cookies); there is no nonce to verify here, hence the phpcs ignores.
        if ( empty( $_REQUEST['cookbook-collect'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        $request_method = isset( $_SERVER['REQUEST_METHOD'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
            : '';
        if ( 'POST' !== $request_method ) {
            return;
        }
        if ( ! is_user_logged_in() ) {
            auth_redirect();
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'Not allowed.', 'cookbook' ), 403 );
        }

        $url = esc_url_raw( wp_unslash( $_REQUEST['cookbook-collect'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        // Raw page HTML; passed to Importer::from_html which extracts JSON-LD or strips tags.
        $html = isset( $_POST['body'] ) ? (string) wp_unslash( $_POST['body'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        $post_id = $this->import_recipe_to_draft( $url, '', '', $html );
        if ( is_wp_error( $post_id ) && in_array( $post_id->get_error_code(), [ 'cookbook_import_empty', 'cookbook_import_parse_failed' ], true ) ) {
            $this->redirect_import_parse_error( $url );
        }
        if ( is_wp_error( $post_id ) ) {
            wp_die( esc_html( $post_id->get_error_message() ) );
        }

        wp_safe_redirect( home_url( '/' . $this->get_url_path() . '/recipe/' . $post_id . '/edit' ) );
        exit;
    }

    public function ajax_parse_url(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => __( 'Not allowed.', 'cookbook' ) ], 403 );
        }
        check_ajax_referer( 'cookbook_import' );
        $url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
        if ( $url === '' ) {
            wp_send_json_error( [ 'message' => __( 'Missing URL.', 'cookbook' ) ] );
        }
        $parsed = $this->parse_recipe_input( $url );
        if ( is_wp_error( $parsed ) ) {
            wp_send_json_error( [ 'message' => __( 'Could not parse a recipe from that URL.', 'cookbook' ) ] );
        }
        wp_send_json_success( $parsed );
    }

    public function ajax_parse_text(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => __( 'Not allowed.', 'cookbook' ) ], 403 );
        }
        check_ajax_referer( 'cookbook_import' );
        $paste = isset( $_POST['paste'] ) ? wp_kses_post( wp_unslash( $_POST['paste'] ) ) : '';
        if ( trim( $paste ) === '' ) {
            wp_send_json_error( [ 'message' => __( 'Paste recipe text to preview it.', 'cookbook' ) ] );
        }
        $parsed = $this->parse_recipe_input( '', $paste );
        if ( is_wp_error( $parsed ) ) {
            wp_send_json_error( [ 'message' => __( 'No ingredients or instructions detected yet.', 'cookbook' ) ] );
        }
        wp_send_json_success( $parsed );
    }

    private function redirect_import_parse_error( string $source_url = '' ): void {
        $args = [ 'error' => 'parse' ];
        if ( $source_url !== '' ) {
            $args['source_url'] = $source_url;
        }
        wp_safe_redirect( add_query_arg( $args, home_url( '/' . $this->get_url_path() . '/import' ) ) );
        exit;
    }
}
