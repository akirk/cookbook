<?php

namespace Recipes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WpApp\WpApp;
use WpApp\BaseApp;

class App extends BaseApp {
    const POST_TYPE     = 'recipe';
    const TAX_CATEGORY  = 'recipe_category';
    const TAX_CUISINE   = 'recipe_cuisine';
    const TAX_TAG       = 'recipe_tag';

    const META_SERVINGS    = '_recipe_servings';
    const META_PREP        = '_recipe_prep_time';
    const META_COOK        = '_recipe_cook_time';
    const META_INGREDIENTS = '_recipe_ingredients';
    const META_INSTRUCTIONS = '_recipe_instructions';
    const META_SOURCE_URL  = '_recipe_source_url';
    const META_NOTES       = '_recipe_notes';

    const USER_PREF_UNITS = 'recipes_unit_preference';

    public function __construct() {
        $this->app = new WpApp( $this->get_template_dir(), $this->get_url_path(), [
            'require_login' => true,
            'app_name'      => __( 'Recipes', 'recipes' ),
        ] );
    }

    protected function get_url_path(): string {
        return 'recipes';
    }

    protected function get_template_dir(): string {
        return dirname( __DIR__ ) . '/templates';
    }

    public function init() {
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_action( 'init', [ $this, 'register_taxonomies' ] );
        add_action( 'admin_post_recipes_save', [ $this, 'handle_save' ] );
        add_action( 'admin_post_recipes_delete', [ $this, 'handle_delete' ] );
        add_action( 'admin_post_recipes_settings', [ $this, 'handle_settings' ] );
        add_action( 'admin_post_recipes_import', [ $this, 'handle_import' ] );
        add_action( 'wp_ajax_recipes_parse_url', [ $this, 'ajax_parse_url' ] );

        add_action( 'wp_loaded', [ $this, 'handle_extension_save' ], 100 );
        add_filter( 'friends_browser_extension_actions', [ $this, 'register_browser_extension_action' ] );

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
        $this->app->route( 'settings' );
        $this->app->route( 'category/{slug}' );
        $this->app->route( 'tag/{slug}' );
    }

    protected function setup_menu(): void {
        $home = home_url( '/' . $this->get_url_path() . '/' );
        $this->app->add_menu_item( 'all', __( 'All recipes', 'recipes' ), $home );
        $this->app->add_menu_item( 'new', __( 'New recipe', 'recipes' ), $home . 'new' );
        $this->app->add_menu_item( 'import', __( 'Import from web', 'recipes' ), $home . 'import' );
        $this->app->add_menu_item( 'settings', __( 'Settings', 'recipes' ), $home . 'settings' );
    }

    public function activate(): void {
        $this->register_post_type();
        $this->register_taxonomies();
        flush_rewrite_rules();
    }

    public function register_post_type(): void {
        register_post_type( self::POST_TYPE, [
            'labels' => [
                'name'               => __( 'Recipes', 'recipes' ),
                'singular_name'      => __( 'Recipe', 'recipes' ),
                'add_new'            => __( 'New recipe', 'recipes' ),
                'add_new_item'       => __( 'Add new recipe', 'recipes' ),
                'edit_item'          => __( 'Edit recipe', 'recipes' ),
                'view_item'          => __( 'View recipe', 'recipes' ),
                'search_items'       => __( 'Search recipes', 'recipes' ),
                'not_found'          => __( 'No recipes yet', 'recipes' ),
                'not_found_in_trash' => __( 'No recipes in trash', 'recipes' ),
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
    }

    public function register_taxonomies(): void {
        register_taxonomy( self::TAX_CATEGORY, self::POST_TYPE, [
            'labels' => [
                'name'          => __( 'Categories', 'recipes' ),
                'singular_name' => __( 'Category', 'recipes' ),
            ],
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => false,
        ] );
        register_taxonomy( self::TAX_CUISINE, self::POST_TYPE, [
            'labels' => [
                'name'          => __( 'Cuisines', 'recipes' ),
                'singular_name' => __( 'Cuisine', 'recipes' ),
            ],
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => false,
        ] );
        register_taxonomy( self::TAX_TAG, self::POST_TYPE, [
            'labels' => [
                'name'          => __( 'Tags', 'recipes' ),
                'singular_name' => __( 'Tag', 'recipes' ),
            ],
            'hierarchical'      => false,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => false,
        ] );
    }

    public static function get_user_unit_preference( int $user_id = 0 ): string {
        $user_id = $user_id ?: get_current_user_id();
        $pref    = get_user_meta( $user_id, self::USER_PREF_UNITS, true );
        return in_array( $pref, [ 'metric', 'imperial' ], true ) ? $pref : 'metric';
    }

    public function handle_save(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'Not allowed.', 'recipes' ), 403 );
        }
        check_admin_referer( 'recipes_save' );

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $description = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
        $servings = isset( $_POST['servings'] ) ? max( 1, absint( $_POST['servings'] ) ) : 4;
        $prep = isset( $_POST['prep_time'] ) ? max( 0, absint( $_POST['prep_time'] ) ) : 0;
        $cook = isset( $_POST['cook_time'] ) ? max( 0, absint( $_POST['cook_time'] ) ) : 0;
        $source_url = isset( $_POST['source_url'] ) ? esc_url_raw( wp_unslash( $_POST['source_url'] ) ) : '';
        $notes = isset( $_POST['notes'] ) ? wp_kses_post( wp_unslash( $_POST['notes'] ) ) : '';

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
            'post_title'   => $title !== '' ? $title : __( 'Untitled recipe', 'recipes' ),
            'post_content' => $description,
            'post_author'  => get_current_user_id(),
        ];
        if ( $id ) {
            $existing = get_post( $id );
            if ( ! $existing || $existing->post_type !== self::POST_TYPE ) {
                wp_die( esc_html__( 'Recipe not found.', 'recipes' ), 404 );
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
        update_post_meta( $post_id, self::META_INGREDIENTS, $ingredients );
        update_post_meta( $post_id, self::META_INSTRUCTIONS, $instructions );
        update_post_meta( $post_id, self::META_SOURCE_URL, $source_url );
        update_post_meta( $post_id, self::META_NOTES, $notes );

        if ( ! empty( $_POST['remove_image'] ) ) {
            delete_post_thumbnail( $post_id );
        }
        if ( ! empty( $_FILES['image']['name'] ) && empty( $_FILES['image']['error'] ) ) {
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

        wp_safe_redirect( home_url( '/' . $this->get_url_path() . '/recipe/' . $post_id ) );
        exit;
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
            wp_die( esc_html__( 'Not allowed.', 'recipes' ), 403 );
        }
        check_admin_referer( 'recipes_delete' );
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $post = $id ? get_post( $id ) : null;
        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            wp_die( esc_html__( 'Recipe not found.', 'recipes' ), 404 );
        }
        wp_trash_post( $id );
        wp_safe_redirect( home_url( '/' . $this->get_url_path() . '/' ) );
        exit;
    }

    public function handle_settings(): void {
        if ( ! is_user_logged_in() ) {
            wp_die( esc_html__( 'Not allowed.', 'recipes' ), 403 );
        }
        check_admin_referer( 'recipes_settings' );
        $pref = isset( $_POST['unit_preference'] ) ? sanitize_text_field( wp_unslash( $_POST['unit_preference'] ) ) : 'metric';
        if ( ! in_array( $pref, [ 'metric', 'imperial' ], true ) ) {
            $pref = 'metric';
        }
        update_user_meta( get_current_user_id(), self::USER_PREF_UNITS, $pref );
        wp_safe_redirect( home_url( '/' . $this->get_url_path() . '/settings?saved=1' ) );
        exit;
    }

    public function handle_import(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'Not allowed.', 'recipes' ), 403 );
        }
        check_admin_referer( 'recipes_import' );

        $url   = isset( $_POST['source_url'] ) ? esc_url_raw( wp_unslash( $_POST['source_url'] ) ) : '';
        // $paste is HTML-ish recipe text; sanitize via wp_kses_post which preserves line breaks.
        $paste = isset( $_POST['paste'] ) ? wp_kses_post( wp_unslash( $_POST['paste'] ) ) : '';

        $parsed = null;
        if ( $url !== '' ) {
            $parsed = Importer::from_url( $url );
        }
        if ( ! $parsed && $paste !== '' ) {
            $parsed = Importer::from_text( $paste );
        }
        if ( ! $parsed ) {
            wp_safe_redirect( home_url( '/' . $this->get_url_path() . '/import?error=parse' ) );
            exit;
        }

        $post_id = wp_insert_post( [
            'post_type'    => self::POST_TYPE,
            'post_status'  => 'draft',
            'post_title'   => $parsed['title'] ?: __( 'Imported recipe', 'recipes' ),
            'post_content' => $parsed['description'] ?? '',
            'post_author'  => get_current_user_id(),
        ], true );
        if ( is_wp_error( $post_id ) ) {
            wp_die( esc_html( $post_id->get_error_message() ) );
        }
        update_post_meta( $post_id, self::META_SERVINGS, $parsed['servings'] ?? 4 );
        update_post_meta( $post_id, self::META_PREP, $parsed['prep_time'] ?? 0 );
        update_post_meta( $post_id, self::META_COOK, $parsed['cook_time'] ?? 0 );
        update_post_meta( $post_id, self::META_INGREDIENTS, $parsed['ingredients'] ?? [] );
        update_post_meta( $post_id, self::META_INSTRUCTIONS, $parsed['instructions'] ?? [] );
        update_post_meta( $post_id, self::META_SOURCE_URL, $url );

        if ( ! empty( $parsed['image_url'] ) ) {
            $this->sideload_image_to_post( $post_id, (string) $parsed['image_url'] );
        }

        wp_safe_redirect( home_url( '/' . $this->get_url_path() . '/recipe/' . $post_id . '/edit' ) );
        exit;
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
            'name'   => __( 'Save as Recipe', 'recipes' ),
            'url'    => home_url( '/?recipes-collect={current_url}' ),
            'method' => 'POST',
            'fields' => [ 'body' => '{page_html}' ],
        ];
        return $actions;
    }

    public function handle_extension_save(): void {
        // The browser extension authenticates via the user's logged-in session
        // (cookies); there is no nonce to verify here, hence the phpcs ignores.
        if ( empty( $_REQUEST['recipes-collect'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
            wp_die( esc_html__( 'Not allowed.', 'recipes' ), 403 );
        }

        $url = esc_url_raw( wp_unslash( $_REQUEST['recipes-collect'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        // Raw page HTML; passed to Importer::from_html which extracts JSON-LD or strips tags.
        $html = isset( $_POST['body'] ) ? (string) wp_unslash( $_POST['body'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        $parsed = null;
        if ( $html !== '' ) {
            $parsed = Importer::from_html( $html );
        }
        if ( ! $parsed && $url ) {
            $parsed = Importer::from_url( $url );
        }
        if ( ! $parsed ) {
            wp_safe_redirect( home_url( '/' . $this->get_url_path() . '/import?error=parse' ) );
            exit;
        }

        $post_id = wp_insert_post( [
            'post_type'    => self::POST_TYPE,
            'post_status'  => 'draft',
            'post_title'   => $parsed['title'] ?: __( 'Imported recipe', 'recipes' ),
            'post_content' => $parsed['description'] ?? '',
            'post_author'  => get_current_user_id(),
        ], true );
        if ( is_wp_error( $post_id ) ) {
            wp_die( esc_html( $post_id->get_error_message() ) );
        }
        update_post_meta( $post_id, self::META_SERVINGS, $parsed['servings'] ?? 4 );
        update_post_meta( $post_id, self::META_PREP, $parsed['prep_time'] ?? 0 );
        update_post_meta( $post_id, self::META_COOK, $parsed['cook_time'] ?? 0 );
        update_post_meta( $post_id, self::META_INGREDIENTS, $parsed['ingredients'] ?? [] );
        update_post_meta( $post_id, self::META_INSTRUCTIONS, $parsed['instructions'] ?? [] );
        update_post_meta( $post_id, self::META_SOURCE_URL, $url );

        if ( ! empty( $parsed['image_url'] ) ) {
            $this->sideload_image_to_post( $post_id, (string) $parsed['image_url'] );
        }

        wp_safe_redirect( home_url( '/' . $this->get_url_path() . '/recipe/' . $post_id . '/edit' ) );
        exit;
    }

    public function ajax_parse_url(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => __( 'Not allowed.', 'recipes' ) ], 403 );
        }
        check_ajax_referer( 'recipes_import' );
        $url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
        if ( $url === '' ) {
            wp_send_json_error( [ 'message' => __( 'Missing URL.', 'recipes' ) ] );
        }
        $parsed = Importer::from_url( $url );
        if ( ! $parsed ) {
            wp_send_json_error( [ 'message' => __( 'Could not parse a recipe from that URL.', 'recipes' ) ] );
        }
        wp_send_json_success( $parsed );
    }
}
