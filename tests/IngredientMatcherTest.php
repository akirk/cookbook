<?php

use PHPUnit\Framework\TestCase;
use Recipes\IngredientMatcher;

class IngredientMatcherTest extends TestCase {

    public function test_tokenize_drops_modifiers_and_short_fragments(): void {
        $this->assertSame( [ 'basil' ], IngredientMatcher::tokenize( 'fresh basil leaves' ) );
        $this->assertSame( [ 'tomato' ], IngredientMatcher::tokenize( '2 large ripe tomatoes' ) );
        $this->assertSame( [ 'olive', 'oil' ], IngredientMatcher::tokenize( 'extra-virgin olive oil' ) );
    }

    public function test_tokenize_folds_diacritics(): void {
        $tokens = IngredientMatcher::tokenize( 'Crème fraîche' );
        $this->assertContains( 'creme', $tokens );
        $this->assertContains( 'fraiche', $tokens );
    }

    /** @dataProvider canonicalCases */
    public function test_canonicalize( string $input, string $expected ): void {
        $this->assertSame( $expected, IngredientMatcher::canonicalize( $input ) );
    }

    public static function canonicalCases(): array {
        return [
            'plural -oes'         => [ '2 large ripe tomatoes',     'tomato' ],
            'plural -oes potato'  => [ 'Potatoes',                  'potato' ],
            'plural -ies'         => [ 'fresh berries',             'berry' ],
            'plural -s'           => [ '3 onions, chopped',         'onion' ],
            'plural -shes'        => [ 'small dishes',              'dish' ],
            'multi word'          => [ 'extra-virgin olive oil',    'olive oil' ],
            'modifiers stripped'  => [ 'fresh basil leaves',        'basil' ],
            'diacritics'          => [ 'Crème fraîche',             'creme fraiche' ],
            'short word kept'     => [ 'egg',                       'egg' ],
            'ss not stripped'     => [ 'cress',                     'cress' ],
        ];
    }

    public function test_canonicalize_falls_back_when_only_modifiers(): void {
        // "fresh" alone has no real noun — fall back so we still produce *something*.
        $this->assertSame( 'fresh', IngredientMatcher::canonicalize( 'Fresh' ) );
    }

    public function test_modifier_words_in_have_list_do_not_cause_false_positive(): void {
        $tokens = IngredientMatcher::tokenize( 'fresh' );
        $this->assertSame( [], $tokens );
    }
}
