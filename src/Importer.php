<?php

namespace Recipes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Recipe importer.
 *
 *   from_url()  – fetch a page and look for schema.org Recipe JSON-LD.
 *   from_text() – best-effort parser for pasted text (Ingredients/Method headers).
 *
 * Returns an associative array shaped like:
 *   [
 *     'title'        => string,
 *     'description'  => string,
 *     'servings'     => int,
 *     'prep_time'    => int (minutes),
 *     'cook_time'    => int (minutes),
 *     'ingredients'  => [ [ 'amount','unit','name','notes' ], … ],
 *     'instructions' => [ string, … ],
 *   ]
 * Or null if nothing useful could be extracted.
 */
class Importer {

    public static function from_url( string $url ): ?array {
        if ( $url === '' || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return null;
        }

        $response = wp_remote_get( $url, [
            'timeout'    => 12,
            'user-agent' => 'Mozilla/5.0 (compatible; WP-Recipes/1.0)',
            'redirection' => 5,
        ] );
        if ( is_wp_error( $response ) ) return null;
        $body = wp_remote_retrieve_body( $response );
        if ( ! $body ) return null;
        return self::from_html( $body );
    }

    /**
     * Parse a recipe from a chunk of HTML — used for the browser-extension
     * import where the page body has already been captured client-side.
     */
    public static function from_html( string $html ): ?array {
        if ( $html === '' ) return null;
        $recipe = self::extract_jsonld_recipe( $html );
        if ( $recipe ) {
            $parsed = self::normalize_jsonld( $recipe );
            if ( $parsed ) return $parsed;
        }
        return self::from_text( wp_strip_all_tags( $html ) );
    }

    public static function from_text( string $text ): ?array {
        $text = trim( $text );
        if ( $text === '' ) return null;

        $lines = preg_split( '/\r\n|\r|\n/', $text );
        $lines = array_map( 'trim', $lines );
        $lines = array_values( array_filter( $lines, function( $l ) { return $l !== ''; } ) );
        if ( ! $lines ) return null;

        $title = $lines[0];
        if ( mb_strlen( $title ) > 120 ) {
            $title = '';
        }

        $section = 'unknown';
        $ingredients_lines = [];
        $instructions_lines = [];

        foreach ( $lines as $i => $line ) {
            $lower = strtolower( $line );
            if ( preg_match( '/^(ingredients?)\b[: ]*$/i', $lower ) ) {
                $section = 'ingredients';
                continue;
            }
            if ( preg_match( '/^(method|instructions?|directions?|preparation|steps?)\b[: ]*$/i', $lower ) ) {
                $section = 'instructions';
                continue;
            }
            if ( preg_match( '/^(notes?|tips?)\b[: ]*$/i', $lower ) ) {
                $section = 'notes';
                continue;
            }
            if ( $section === 'ingredients' ) {
                $ingredients_lines[] = $line;
            } elseif ( $section === 'instructions' ) {
                $instructions_lines[] = $line;
            }
        }

        // If no headers found, guess: lines that start with a number are ingredients,
        // longer prose lines are instructions.
        if ( ! $ingredients_lines && ! $instructions_lines ) {
            foreach ( array_slice( $lines, 1 ) as $line ) {
                if ( self::looks_like_ingredient( $line ) ) {
                    $ingredients_lines[] = $line;
                } elseif ( str_word_count( $line ) >= 5 ) {
                    $instructions_lines[] = $line;
                }
            }
        }

        $ingredients = array_map( [ self::class, 'parse_ingredient_line' ], $ingredients_lines );
        $instructions = array_values( array_filter( array_map( [ self::class, 'clean_step' ], $instructions_lines ) ) );

        if ( ! $ingredients && ! $instructions ) {
            return null;
        }

        return [
            'title'        => $title,
            'description'  => '',
            'servings'     => 4,
            'prep_time'    => 0,
            'cook_time'    => 0,
            'ingredients'  => $ingredients,
            'instructions' => $instructions,
            'image_url'    => '',
        ];
    }

    private static function looks_like_ingredient( string $line ): bool {
        if ( preg_match( '/^[-*•]\s*/', $line ) ) return true;
        if ( preg_match( '/^[\d½⅓⅔¼¾⅛]/u', $line ) ) return true;
        return false;
    }

    public static function parse_ingredient_line( string $line ): array {
        $line = preg_replace( '#^\s*[-*•]\s*#u', '', $line );

        // Longest alternatives first so regex doesn't match a prefix (e.g. "kg" before "g").
        $units_pattern = 'kilograms|kilogram|milliliters|milliliter|millilitres|millilitre|'
            . 'tablespoons|tablespoon|teaspoons|teaspoon|fluid ounce|'
            . 'pounds|pound|ounces|ounce|gallons|gallon|quarts|quart|pints|pint|'
            . 'liters|liter|litres|litre|grams|gram|cups|tbsp|tbs|tsp|fl oz|kg|mg|ml|lb|lbs|oz|pt|qt|cup|gal|l|g|'
            . 'EL|TL|Stk|Stück|Msp|Pk|Pkg|Pck|Prise|Bund|Pkt|'
            . 'pinch|dash|cloves|clove|slices|slice|pieces|piece|cans|can|bunch';

        $amount_pattern = '(?:\d+(?:[.,]\d+)?\s+\d+/\d+|\d+/\d+|\d+(?:[.,]\d+)?|[½⅓⅔¼¾⅕⅖⅗⅘⅙⅚⅛⅜⅝⅞])';

        if ( preg_match( '#^(' . $amount_pattern . ')\s*(' . $units_pattern . ')\b\.?\s+(.+)$#u', $line, $m ) ) {
            return self::ingredient_row( $m[1], $m[2], $m[3] );
        }
        if ( preg_match( '#^(' . $amount_pattern . ')\s+(.+)$#u', $line, $m ) ) {
            return self::ingredient_row( $m[1], '', $m[2] );
        }
        return self::ingredient_row( '', '', $line );
    }

    private static function ingredient_row( string $amount, string $unit, string $rest ): array {
        $notes = '';
        if ( preg_match( '/^(.*?)[,(](.*)$/u', $rest, $m ) ) {
            $rest = trim( $m[1] );
            $notes = trim( rtrim( $m[2], ')' ) );
        }
        return [
            'amount' => trim( $amount ),
            'unit'   => Units::normalize_unit( $unit ),
            'name'   => trim( $rest ),
            'notes'  => $notes,
        ];
    }

    private static function extract_jsonld_recipe( string $html ): ?array {
        if ( ! preg_match_all( '#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#si', $html, $matches ) ) {
            return null;
        }
        foreach ( $matches[1] as $json ) {
            $json = trim( html_entity_decode( $json, ENT_QUOTES, 'UTF-8' ) );
            // Strip control chars that break json_decode.
            $json = preg_replace( '/[\x00-\x09\x0B\x0C\x0E-\x1F]/', ' ', $json );
            $data = json_decode( $json, true );
            if ( ! $data ) continue;
            $found = self::find_recipe_node( $data );
            if ( $found ) return $found;
        }
        return null;
    }

    private static function find_recipe_node( $node ): ?array {
        if ( ! is_array( $node ) ) return null;
        $type = $node['@type'] ?? null;
        if ( $type ) {
            $types = (array) $type;
            foreach ( $types as $t ) {
                if ( strcasecmp( $t, 'Recipe' ) === 0 ) return $node;
            }
        }
        if ( isset( $node['@graph'] ) && is_array( $node['@graph'] ) ) {
            foreach ( $node['@graph'] as $sub ) {
                $r = self::find_recipe_node( $sub );
                if ( $r ) return $r;
            }
        }
        // Some sites put it as a top-level array.
        foreach ( $node as $v ) {
            if ( is_array( $v ) ) {
                $r = self::find_recipe_node( $v );
                if ( $r ) return $r;
            }
        }
        return null;
    }

    private static function normalize_jsonld( array $r ): ?array {
        $title = is_string( $r['name'] ?? null ) ? trim( $r['name'] ) : '';
        $description = is_string( $r['description'] ?? null ) ? trim( $r['description'] ) : '';
        $image_url = self::extract_image_url( $r['image'] ?? '' );

        $servings = 0;
        if ( isset( $r['recipeYield'] ) ) {
            $y = is_array( $r['recipeYield'] ) ? reset( $r['recipeYield'] ) : $r['recipeYield'];
            if ( is_numeric( $y ) ) {
                $servings = (int) $y;
            } elseif ( is_string( $y ) && preg_match( '/(\d+)/', $y, $m ) ) {
                $servings = (int) $m[1];
            }
        }
        if ( ! $servings ) $servings = 4;

        $ingredients = [];
        $raw_ingredients = $r['recipeIngredient'] ?? ( $r['ingredients'] ?? [] );
        if ( is_array( $raw_ingredients ) ) {
            foreach ( $raw_ingredients as $line ) {
                if ( is_string( $line ) && trim( $line ) !== '' ) {
                    $ingredients[] = self::parse_ingredient_line( $line );
                }
            }
        }

        $instructions = [];
        if ( isset( $r['recipeInstructions'] ) ) {
            $instructions = self::flatten_instructions( $r['recipeInstructions'] );
        }

        return [
            'title'        => $title,
            'description'  => $description,
            'servings'     => $servings,
            'prep_time'    => self::iso8601_to_minutes( $r['prepTime'] ?? '' ),
            'cook_time'    => self::iso8601_to_minutes( $r['cookTime'] ?? '' ),
            'ingredients'  => $ingredients,
            'instructions' => $instructions,
            'image_url'    => $image_url,
        ];
    }

    /**
     * schema.org "image" can be a string URL, an array of URLs, or an
     * ImageObject (or array of those). Walk it and return the first usable URL.
     */
    private static function extract_image_url( $image ): string {
        if ( is_string( $image ) ) {
            $image = trim( $image );
            return filter_var( $image, FILTER_VALIDATE_URL ) ? $image : '';
        }
        if ( ! is_array( $image ) ) return '';
        if ( isset( $image['url'] ) && is_string( $image['url'] ) ) {
            return self::extract_image_url( $image['url'] );
        }
        if ( isset( $image['contentUrl'] ) && is_string( $image['contentUrl'] ) ) {
            return self::extract_image_url( $image['contentUrl'] );
        }
        foreach ( $image as $candidate ) {
            $url = self::extract_image_url( $candidate );
            if ( $url !== '' ) return $url;
        }
        return '';
    }

    private static function flatten_instructions( $instructions ): array {
        $out = [];
        if ( is_string( $instructions ) ) {
            $parts = preg_split( '/(?:\r?\n)+|(?<=[.!?])\s+(?=[A-Z])/', $instructions );
            foreach ( $parts as $p ) {
                $p = self::clean_step( $p );
                if ( $p !== '' ) $out[] = $p;
            }
            return $out;
        }
        if ( ! is_array( $instructions ) ) return $out;

        foreach ( $instructions as $step ) {
            if ( is_string( $step ) ) {
                $step = self::clean_step( $step );
                if ( $step !== '' ) $out[] = $step;
                continue;
            }
            if ( ! is_array( $step ) ) continue;
            $type = $step['@type'] ?? '';
            if ( strcasecmp( (string) $type, 'HowToSection' ) === 0 ) {
                $name = isset( $step['name'] ) ? trim( $step['name'] ) : '';
                if ( $name !== '' ) $out[] = $name . ':';
                $sub = self::flatten_instructions( $step['itemListElement'] ?? [] );
                $out = array_merge( $out, $sub );
                continue;
            }
            if ( isset( $step['text'] ) && is_string( $step['text'] ) ) {
                $t = self::clean_step( $step['text'] );
                if ( $t !== '' ) $out[] = $t;
            } elseif ( isset( $step['name'] ) && is_string( $step['name'] ) ) {
                $t = self::clean_step( $step['name'] );
                if ( $t !== '' ) $out[] = $t;
            }
        }
        return $out;
    }

    /**
     * Strip leading enumerators ("1.", "1)", "Step 1:", "- ", "• ") so we don't
     * double up with the <ol> numbering on the recipe view.
     */
    public static function clean_step( string $step ): string {
        $step = trim( $step );
        if ( $step === '' ) return '';
        // Loop so combined prefixes ("- 1. text") get peeled off in any order.
        for ( $i = 0; $i < 4; $i++ ) {
            $before = $step;
            $step = preg_replace( '/^(?:step\s+)?\d+\s*[\.\)\:\-]\s*/iu', '', $step );
            $step = preg_replace( '/^[-*•]\s*/u', '', $step );
            $step = trim( $step );
            if ( $step === $before ) break;
        }
        return $step;
    }

    private static function iso8601_to_minutes( $duration ): int {
        if ( ! is_string( $duration ) || $duration === '' ) return 0;
        if ( ! preg_match( '/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $duration, $m ) ) return 0;
        $h = isset( $m[1] ) && $m[1] !== '' ? (int) $m[1] : 0;
        $i = isset( $m[2] ) && $m[2] !== '' ? (int) $m[2] : 0;
        return $h * 60 + $i;
    }
}
