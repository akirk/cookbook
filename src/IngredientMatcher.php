<?php

namespace Recipes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Canonicalize free-form ingredient names so that "Tomatoes", "ripe tomato"
 * and "fresh tomatoes" all collapse to a single taxonomy term ("tomato").
 *
 *   tokenize()      – break a phrase into comparable lowercase tokens, drop modifiers.
 *   canonicalize()  – join tokens back into the canonical term name (e.g. "olive oil").
 */
class IngredientMatcher {

    /**
     * Words that describe preparation/state/size rather than the ingredient itself.
     * Tokens listed here are stripped before matching so "fresh basil" and
     * "basil leaves" both reduce to "basil".
     */
    const STOPWORDS = [
        'a', 'an', 'and', 'or', 'of', 'the', 'to', 'taste',
        'fresh', 'dried', 'frozen', 'canned', 'jarred',
        'ground', 'whole', 'crushed', 'minced', 'grated', 'shredded',
        'chopped', 'diced', 'sliced', 'cubed', 'halved', 'quartered',
        'peeled', 'washed', 'rinsed', 'drained', 'cooked', 'raw',
        'large', 'small', 'medium', 'big', 'little',
        'ripe', 'cold', 'warm', 'hot', 'boiling', 'lukewarm',
        'plain', 'unsalted', 'optional', 'extra', 'virgin',
        'leaves', 'leaf', 'bunch', 'bunches', 'sprig', 'sprigs',
        'clove', 'cloves', 'piece', 'pieces',
        'finely', 'roughly', 'thinly', 'thickly', 'lightly',
        'for', 'serving', 'garnish',
    ];

    /**
     * Break a free-form ingredient phrase into lowercase, singularized tokens
     * with stopwords and very short fragments removed.
     *
     * @return string[]
     */
    public static function tokenize( string $text ): array {
        $text = self::fold( $text );
        $parts = preg_split( '/[^a-z0-9]+/u', $text ) ?: [];
        $stop = array_flip( self::STOPWORDS );
        $out  = [];
        foreach ( $parts as $p ) {
            if ( $p === '' || strlen( $p ) < 2 ) continue;
            if ( isset( $stop[ $p ] ) ) continue;
            $out[] = self::singularize( $p );
        }
        return $out;
    }

    /**
     * Canonical taxonomy-term name for an ingredient phrase.
     * Falls back to the lowercased+trimmed input if every token was filtered.
     */
    public static function canonicalize( string $text ): string {
        $tokens = self::tokenize( $text );
        if ( $tokens ) {
            return implode( ' ', $tokens );
        }
        return trim( self::fold( $text ) );
    }

    /**
     * Naive English singularization for the suffixes that come up in recipes.
     * Not exhaustive — "knives" → "knive" — but covers tomatoes, berries, dishes,
     * onions, eggs and similar everyday cases.
     */
    private static function singularize( string $token ): string {
        $len = strlen( $token );
        if ( $len < 4 ) return $token;
        if ( substr( $token, -3 ) === 'ies' ) return substr( $token, 0, -3 ) . 'y';
        if ( substr( $token, -3 ) === 'oes' ) return substr( $token, 0, -2 );
        foreach ( [ 'shes', 'ches', 'xes', 'ses' ] as $suffix ) {
            $sl = strlen( $suffix );
            if ( $len > $sl && substr( $token, -$sl ) === $suffix ) {
                return substr( $token, 0, -2 );
            }
        }
        if ( substr( $token, -1 ) === 's' && substr( $token, -2 ) !== 'ss' ) {
            return substr( $token, 0, -1 );
        }
        return $token;
    }

    /**
     * Lowercase + best-effort diacritic fold so "Crème" and "creme" tokenize alike.
     */
    private static function fold( string $text ): string {
        $text = strtolower( $text );
        if ( function_exists( 'iconv' ) ) {
            $folded = @iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $text );
            if ( is_string( $folded ) && $folded !== '' ) {
                $text = $folded;
            }
        }
        return $text;
    }
}
