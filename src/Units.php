<?php

namespace Recipes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Unit conversion + display formatting.
 *
 * Mass and volume units are convertible across the metric/imperial divide.
 * Anything else (piece, slice, clove, pinch, can, …) is left as-is.
 */
class Units {

    // Canonical mass in grams.
    private const MASS = [
        'g'  => 1.0,
        'kg' => 1000.0,
        'oz' => 28.3495,
        'lb' => 453.592,
    ];

    // Canonical volume in millilitres.
    private const VOLUME = [
        'ml'   => 1.0,
        'l'    => 1000.0,
        'tsp'  => 4.92892,
        'tbsp' => 14.7868,
        'floz' => 29.5735,
        'cup'  => 236.588,
        'pt'   => 473.176,
        'qt'   => 946.353,
        'gal'  => 3785.41,
    ];

    private const UNIT_ALIASES = [
        'gram'        => 'g',
        'grams'       => 'g',
        'kilogram'    => 'kg',
        'kilograms'   => 'kg',
        'ounce'       => 'oz',
        'ounces'      => 'oz',
        'pound'       => 'lb',
        'pounds'      => 'lb',
        'lbs'         => 'lb',
        'millilitre'  => 'ml',
        'millilitres' => 'ml',
        'milliliter'  => 'ml',
        'milliliters' => 'ml',
        'litre'       => 'l',
        'litres'      => 'l',
        'liter'       => 'l',
        'liters'      => 'l',
        'teaspoon'    => 'tsp',
        'teaspoons'   => 'tsp',
        'tablespoon'  => 'tbsp',
        'tablespoons' => 'tbsp',
        'tbs'         => 'tbsp',
        'fluid ounce' => 'floz',
        'fl oz'       => 'floz',
        'cups'        => 'cup',
        'pint'        => 'pt',
        'pints'       => 'pt',
        'quart'       => 'qt',
        'quarts'      => 'qt',
        'gallon'      => 'gal',
        'gallons'     => 'gal',

        // German.
        'el'          => 'tbsp',
        'tl'          => 'tsp',
        'esslöffel'   => 'tbsp',
        'teelöffel'   => 'tsp',
        'stk'         => 'piece',
        'stk.'        => 'piece',
        'stück'       => 'piece',
        'msp'         => 'pinch',
        'msp.'        => 'pinch',
        'messerspitze' => 'pinch',
        'prise'       => 'pinch',
        'bund'        => 'bunch',
        'pk'          => 'packet',
        'pkg'         => 'packet',
        'pck'         => 'packet',
        'pkt'         => 'packet',
        'packung'     => 'packet',
    ];

    public const COMMON_UNITS = [
        'metric'   => [ 'g', 'kg', 'ml', 'l', 'piece', 'pinch', 'clove', 'slice' ],
        'imperial' => [ 'oz', 'lb', 'tsp', 'tbsp', 'cup', 'floz', 'pt', 'qt', 'piece', 'pinch', 'clove', 'slice' ],
    ];

    public static function normalize_unit( string $unit ): string {
        $unit = strtolower( trim( $unit ) );
        return self::UNIT_ALIASES[ $unit ] ?? $unit;
    }

    public static function unit_kind( string $unit ): ?string {
        $unit = self::normalize_unit( $unit );
        if ( isset( self::MASS[ $unit ] ) )   return 'mass';
        if ( isset( self::VOLUME[ $unit ] ) ) return 'volume';
        return null;
    }

    /**
     * Parse an amount that may include a unicode fraction or "1 1/2" mixed form.
     * Returns float, or null if blank/non-numeric.
     */
    public static function parse_amount( $value ): ?float {
        if ( is_numeric( $value ) ) return (float) $value;
        if ( ! is_string( $value ) ) return null;
        $v = trim( $value );
        if ( $v === '' ) return null;

        $fractions = [
            '½' => 0.5, '⅓' => 1/3, '⅔' => 2/3, '¼' => 0.25, '¾' => 0.75,
            '⅕' => 0.2, '⅖' => 0.4, '⅗' => 0.6, '⅘' => 0.8,
            '⅙' => 1/6, '⅚' => 5/6, '⅛' => 0.125, '⅜' => 0.375, '⅝' => 0.625, '⅞' => 0.875,
        ];
        foreach ( $fractions as $g => $f ) {
            $v = str_replace( $g, ' ' . $f, $v );
        }
        $v = trim( preg_replace( '/\s+/', ' ', $v ) );

        // "1 1/2" => 1.5
        if ( preg_match( '#^(\d+)\s+(\d+)/(\d+)$#', $v, $m ) ) {
            return (float) $m[1] + ( (float) $m[2] / (float) $m[3] );
        }
        // "1/2"
        if ( preg_match( '#^(\d+)/(\d+)$#', $v, $m ) ) {
            return (float) $m[1] / (float) $m[2];
        }
        // "1.5" or "1,5"
        $v = str_replace( ',', '.', $v );
        if ( is_numeric( $v ) ) return (float) $v;
        // "1.5 something" — take the leading number
        if ( preg_match( '/^[\d\.]+/', $v, $m ) ) {
            return (float) $m[0];
        }
        return null;
    }

    /**
     * Convert (amount, unit) to the user's preferred system, picking a sensible
     * sub-unit (e.g. >=1000 g becomes kg). Non-convertible units pass through.
     */
    public static function to_preference( $amount, string $unit, string $preference ): array {
        $original = [ 'amount' => $amount, 'unit' => $unit ];
        $kind = self::unit_kind( $unit );
        if ( $kind === null ) return $original;

        $value = self::parse_amount( $amount );
        if ( $value === null ) return $original;

        $unit = self::normalize_unit( $unit );
        $current_system = self::system_of( $unit );
        if ( $current_system === $preference ) {
            return self::pretty_in_system( $value, $unit, $kind, $preference );
        }

        // Convert to canonical, then to preferred.
        $canonical = ( $kind === 'mass' )
            ? $value * self::MASS[ $unit ]
            : $value * self::VOLUME[ $unit ];

        return self::from_canonical( $canonical, $kind, $preference );
    }

    private static function system_of( string $unit ): string {
        $imperial = [ 'oz', 'lb', 'tsp', 'tbsp', 'cup', 'floz', 'pt', 'qt', 'gal' ];
        return in_array( $unit, $imperial, true ) ? 'imperial' : 'metric';
    }

    private static function from_canonical( float $canonical, string $kind, string $preference ): array {
        if ( $kind === 'mass' ) {
            if ( $preference === 'metric' ) {
                return $canonical >= 1000
                    ? [ 'amount' => self::format_number( $canonical / 1000, 2 ), 'unit' => 'kg' ]
                    : [ 'amount' => self::format_number( $canonical, 0 ), 'unit' => 'g' ];
            }
            $oz = $canonical / self::MASS['oz'];
            return $oz >= 16
                ? [ 'amount' => self::format_number( $canonical / self::MASS['lb'], 2 ), 'unit' => 'lb' ]
                : [ 'amount' => self::format_number( $oz, 1 ), 'unit' => 'oz' ];
        }
        // volume
        if ( $preference === 'metric' ) {
            return $canonical >= 1000
                ? [ 'amount' => self::format_number( $canonical / 1000, 2 ), 'unit' => 'l' ]
                : [ 'amount' => self::format_number( $canonical, 0 ), 'unit' => 'ml' ];
        }
        $cups = $canonical / self::VOLUME['cup'];
        if ( $cups >= 0.25 ) {
            return [ 'amount' => self::format_number( $cups, 2 ), 'unit' => 'cup' ];
        }
        $tbsp = $canonical / self::VOLUME['tbsp'];
        if ( $tbsp >= 1 ) {
            return [ 'amount' => self::format_number( $tbsp, 1 ), 'unit' => 'tbsp' ];
        }
        return [ 'amount' => self::format_number( $canonical / self::VOLUME['tsp'], 1 ), 'unit' => 'tsp' ];
    }

    private static function pretty_in_system( float $value, string $unit, string $kind, string $system ): array {
        // Promote g->kg or ml->l when large; otherwise leave as-is.
        if ( $system === 'metric' ) {
            if ( $unit === 'g' && $value >= 1000 ) {
                return [ 'amount' => self::format_number( $value / 1000, 2 ), 'unit' => 'kg' ];
            }
            if ( $unit === 'ml' && $value >= 1000 ) {
                return [ 'amount' => self::format_number( $value / 1000, 2 ), 'unit' => 'l' ];
            }
        }
        return [ 'amount' => self::format_number( $value, $value < 10 ? 2 : 0 ), 'unit' => $unit ];
    }

    public static function format_number( float $n, int $max_decimals = 2 ): string {
        if ( abs( $n - round( $n ) ) < 0.05 ) {
            return (string) (int) round( $n );
        }
        $s = number_format( $n, $max_decimals, '.', '' );
        return rtrim( rtrim( $s, '0' ), '.' );
    }

    public static function display_unit( string $unit ): string {
        $unit = self::normalize_unit( $unit );
        $labels = [
            'g'    => 'g',
            'kg'   => 'kg',
            'ml'   => 'ml',
            'l'    => 'l',
            'oz'   => 'oz',
            'lb'   => 'lb',
            'tsp'  => 'tsp',
            'tbsp' => 'tbsp',
            'floz' => 'fl oz',
            'cup'  => 'cup',
            'pt'   => 'pt',
            'qt'   => 'qt',
            'gal'  => 'gal',
        ];
        return $labels[ $unit ] ?? $unit;
    }

    /**
     * Scale + convert an ingredient row for display.
     */
    public static function render_ingredient( array $ingredient, float $scale, string $preference ): array {
        $amount_raw = $ingredient['amount'] ?? '';
        $unit       = $ingredient['unit']   ?? '';
        $name       = $ingredient['name']   ?? '';
        $notes      = $ingredient['notes']  ?? '';

        $value = self::parse_amount( $amount_raw );
        if ( $value === null ) {
            return [
                'amount' => $amount_raw,
                'unit'   => self::display_unit( $unit ),
                'name'   => $name,
                'notes'  => $notes,
            ];
        }

        $scaled = $value * $scale;
        $converted = self::to_preference( $scaled, $unit, $preference );
        return [
            'amount' => $converted['amount'],
            'unit'   => self::display_unit( $converted['unit'] ),
            'name'   => $name,
            'notes'  => $notes,
        ];
    }
}
