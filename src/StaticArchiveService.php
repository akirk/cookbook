<?php

namespace Cookbook;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class StaticArchiveService extends AbstractService {
    /**
     * Opt Cookbook recipes into Static Archive without making the CPT public.
     */
    public function add_static_archive_post_type( array $post_types ): array {
        if ( ! in_array( App::POST_TYPE, $post_types, true ) ) {
            $post_types[] = App::POST_TYPE;
        }

        return $post_types;
    }

    /**
     * Render Cookbook's structured recipe meta as Static Archive HTML.
     */
    public function static_archive_recipe_html( string $html, $post, $generator = null ): string {
        if ( ! $post instanceof \WP_Post || $post->post_type !== App::POST_TYPE ) {
            return $html;
        }

        return $this->render_static_archive_recipe_html( $post );
    }

    /**
     * Render Cookbook's structured recipe meta as Static Archive Markdown.
     *
     * The incoming value is null when Static Archive would otherwise derive
     * Markdown from HTML.
     */
    public function static_archive_recipe_markdown( $markdown, $post, $generator = null ) {
        if ( ! $post instanceof \WP_Post || $post->post_type !== App::POST_TYPE ) {
            return $markdown;
        }

        return $this->render_static_archive_recipe_markdown( $post );
    }

    private function render_static_archive_recipe_html( \WP_Post $post ): string {
        $id           = (int) $post->ID;
        $servings     = (int) get_post_meta( $id, App::META_SERVINGS, true );
        $prep         = (int) get_post_meta( $id, App::META_PREP, true );
        $cook         = (int) get_post_meta( $id, App::META_COOK, true );
        $source_url   = (string) get_post_meta( $id, App::META_SOURCE_URL, true );
        $ingredients  = (array) get_post_meta( $id, App::META_INGREDIENTS, true );
        $instructions = $this->clean_static_archive_instructions( (array) get_post_meta( $id, App::META_INSTRUCTIONS, true ) );
        $notes        = (string) get_post_meta( $id, App::META_NOTES, true );

        $html = '';

        if ( has_post_thumbnail( $id ) ) {
            $html .= '<figure class="recipe-photo">' . get_the_post_thumbnail(
                $id,
                'large',
                [
                    'style' => 'max-width:100%;height:auto',
                    'alt'   => esc_attr( get_the_title( $post ) ),
                ]
            ) . '</figure>';
        }

        $meta = [];
        if ( $servings ) {
            $meta[] = sprintf(
                /* translators: %d: servings */
                _n( '%d serving', '%d servings', $servings, 'cookbook' ),
                $servings
            );
        }
        if ( $prep ) {
            $meta[] = sprintf(
                /* translators: %d: prep time in minutes */
                __( 'Prep: %d min', 'cookbook' ),
                $prep
            );
        }
        if ( $cook ) {
            $meta[] = sprintf(
                /* translators: %d: cook time in minutes */
                __( 'Cook: %d min', 'cookbook' ),
                $cook
            );
        }
        if ( $source_url ) {
            $source_label = wp_parse_url( $source_url, PHP_URL_HOST ) ?: $source_url;
            $meta[] = sprintf(
                '%s <a href="%s">%s</a>',
                esc_html__( 'Source:', 'cookbook' ),
                esc_url( $source_url ),
                esc_html( $source_label )
            );
        }
        if ( $meta ) {
            $html .= '<ul class="recipe-meta"><li>' . implode( '</li><li>', array_map( 'wp_kses_post', $meta ) ) . '</li></ul>';
        }

        $term_groups = [
            __( 'Categories', 'cookbook' ) => wp_get_object_terms( $id, App::TAX_CATEGORY ),
            __( 'Cuisines', 'cookbook' )   => wp_get_object_terms( $id, App::TAX_CUISINE ),
            __( 'Tags', 'cookbook' )       => wp_get_object_terms( $id, App::TAX_TAG ),
        ];
        $term_lines = [];
        foreach ( $term_groups as $label => $terms ) {
            if ( is_wp_error( $terms ) || ! $terms ) {
                continue;
            }
            $term_lines[] = '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) ) . '</dd>';
        }
        if ( $term_lines ) {
            $html .= '<dl class="recipe-terms">' . implode( '', $term_lines ) . '</dl>';
        }

        if ( $post->post_content ) {
            $html .= '<div class="recipe-description">' . wp_kses_post( wpautop( $post->post_content ) ) . '</div>';
        }

        $html .= '<h2>' . esc_html__( 'Ingredients', 'cookbook' ) . '</h2>';
        if ( $ingredients ) {
            $items = [];
            foreach ( $ingredients as $ingredient ) {
                if ( ! is_array( $ingredient ) ) {
                    continue;
                }
                $line = $this->static_archive_ingredient_text( $ingredient );
                if ( $line !== '' ) {
                    $items[] = '<li>' . esc_html( $line ) . '</li>';
                }
            }
            $html .= $items ? '<ul>' . implode( '', $items ) . '</ul>' : '<p>' . esc_html__( 'No ingredients yet.', 'cookbook' ) . '</p>';
        } else {
            $html .= '<p>' . esc_html__( 'No ingredients yet.', 'cookbook' ) . '</p>';
        }

        $html .= '<h2>' . esc_html__( 'Instructions', 'cookbook' ) . '</h2>';
        if ( $instructions ) {
            $html .= '<ol><li>' . implode( '</li><li>', array_map( 'wp_kses_post', $instructions ) ) . '</li></ol>';
        } else {
            $html .= '<p>' . esc_html__( 'No instructions yet.', 'cookbook' ) . '</p>';
        }

        if ( $notes ) {
            $html .= '<h2>' . esc_html__( 'Notes', 'cookbook' ) . '</h2>';
            $html .= '<div class="recipe-notes">' . wp_kses_post( wpautop( $notes ) ) . '</div>';
        }

        return $html;
    }

    private function render_static_archive_recipe_markdown( \WP_Post $post ): string {
        $id           = (int) $post->ID;
        $servings     = (int) get_post_meta( $id, App::META_SERVINGS, true );
        $prep         = (int) get_post_meta( $id, App::META_PREP, true );
        $cook         = (int) get_post_meta( $id, App::META_COOK, true );
        $source_url   = (string) get_post_meta( $id, App::META_SOURCE_URL, true );
        $ingredients  = (array) get_post_meta( $id, App::META_INGREDIENTS, true );
        $instructions = $this->clean_static_archive_instructions( (array) get_post_meta( $id, App::META_INSTRUCTIONS, true ) );
        $notes        = (string) get_post_meta( $id, App::META_NOTES, true );

        $sections = [];

        $thumbnail_url = get_the_post_thumbnail_url( $id, 'large' );
        if ( $thumbnail_url ) {
            $sections[] = '![' . $this->static_archive_markdown_text( get_the_title( $post ) ) . '](' . esc_url_raw( $thumbnail_url ) . ')';
        }

        $meta = [];
        if ( $servings ) {
            $meta[] = sprintf(
                /* translators: %d: servings */
                _n( '%d serving', '%d servings', $servings, 'cookbook' ),
                $servings
            );
        }
        if ( $prep ) {
            $meta[] = sprintf(
                /* translators: %d: prep time in minutes */
                __( 'Prep: %d min', 'cookbook' ),
                $prep
            );
        }
        if ( $cook ) {
            $meta[] = sprintf(
                /* translators: %d: cook time in minutes */
                __( 'Cook: %d min', 'cookbook' ),
                $cook
            );
        }
        if ( $source_url ) {
            $meta[] = __( 'Source:', 'cookbook' ) . ' ' . $source_url;
        }
        if ( $meta ) {
            $sections[] = implode( "\n", array_map( fn( $line ) => '- ' . $this->static_archive_markdown_text( $line ), $meta ) );
        }

        $description = $this->static_archive_markdown_text( $post->post_content );
        if ( $description !== '' ) {
            $sections[] = $description;
        }

        $ingredient_lines = [];
        foreach ( $ingredients as $ingredient ) {
            if ( ! is_array( $ingredient ) ) {
                continue;
            }
            $line = $this->static_archive_ingredient_text( $ingredient );
            if ( $line !== '' ) {
                $ingredient_lines[] = '- ' . $this->static_archive_markdown_text( $line );
            }
        }
        $sections[] = "## " . __( 'Ingredients', 'cookbook' ) . "\n\n" . ( $ingredient_lines ? implode( "\n", $ingredient_lines ) : __( 'No ingredients yet.', 'cookbook' ) );

        $instruction_lines = [];
        foreach ( $instructions as $index => $step ) {
            $text = $this->static_archive_markdown_text( $step );
            if ( $text !== '' ) {
                $instruction_lines[] = ( $index + 1 ) . '. ' . $text;
            }
        }
        $sections[] = "## " . __( 'Instructions', 'cookbook' ) . "\n\n" . ( $instruction_lines ? implode( "\n", $instruction_lines ) : __( 'No instructions yet.', 'cookbook' ) );

        $notes_text = $this->static_archive_markdown_text( $notes );
        if ( $notes_text !== '' ) {
            $sections[] = "## " . __( 'Notes', 'cookbook' ) . "\n\n" . $notes_text;
        }

        return trim( implode( "\n\n", array_filter( $sections ) ) );
    }

    private function clean_static_archive_instructions( array $instructions ): array {
        $clean = [];
        foreach ( $instructions as $step ) {
            $step = Importer::clean_step( (string) $step );
            if ( $step !== '' ) {
                $clean[] = $step;
            }
        }

        return $clean;
    }

    private function static_archive_ingredient_text( array $ingredient ): string {
        $rendered = Units::render_ingredient( $ingredient, 1.0, 'metric' );
        $quantity = trim( trim( (string) $rendered['amount'] ) . ' ' . trim( (string) $rendered['unit'] ) );
        $line     = trim( $quantity . ' ' . trim( (string) $rendered['name'] ) );

        if ( ! empty( $rendered['notes'] ) ) {
            $line .= ' (' . trim( (string) $rendered['notes'] ) . ')';
        }

        return trim( preg_replace( '/\s+/', ' ', $line ) );
    }

    private function static_archive_markdown_text( string $value ): string {
        return trim( html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
    }
}
