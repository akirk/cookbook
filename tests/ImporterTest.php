<?php

use PHPUnit\Framework\TestCase;
use Cookbook\Importer;

class ImporterTest extends TestCase {

    private function fixture( string $name ): string {
        return file_get_contents( __DIR__ . '/fixtures/' . $name );
    }

    public function test_jsonld_gutekueche_schinkenfleckerln(): void {
        $parsed = Importer::from_html( $this->fixture( 'gutekueche-schinkenfleckerln.html' ) );

        $this->assertIsArray( $parsed );
        $this->assertSame( 'Cremige Schinkenfleckerln', $parsed['title'] );
        $this->assertSame( 2, $parsed['servings'] );
        $this->assertSame( 10, $parsed['prep_time'] );
        $this->assertSame( 20, $parsed['cook_time'] );
        $this->assertNotEmpty( $parsed['image_url'] );

        $this->assertCount( 5, $parsed['ingredients'] );
        $this->assertSame(
            [ 'amount' => '200', 'unit' => 'g', 'name' => 'Mascarpone', 'notes' => '' ],
            $parsed['ingredients'][0]
        );
        // German "EL" should normalize to tbsp.
        $this->assertSame( 'tbsp',  $parsed['ingredients'][2]['unit'] );
        $this->assertSame( '2',     $parsed['ingredients'][2]['amount'] );
        $this->assertSame( 'Öl',    $parsed['ingredients'][2]['name'] );
        // German "Stk" should normalize to piece.
        $this->assertSame( 'piece', $parsed['ingredients'][4]['unit'] );
    }

    public function test_microdata_ichkoche_kaesespaetzle(): void {
        $parsed = Importer::from_html( $this->fixture( 'ichkoche-kaesespaetzle.html' ) );

        $this->assertIsArray( $parsed );
        $this->assertSame( 'Käsespätzle', $parsed['title'] );
        $this->assertSame( 4, $parsed['servings'] );
        $this->assertSame( 30, $parsed['cook_time'] );
        $this->assertStringContainsString( 'ichkoche.at', $parsed['image_url'] );

        // Should not contain rating counts ("1422 Bewertungen") or comment timestamps.
        foreach ( $parsed['ingredients'] as $ing ) {
            $this->assertStringNotContainsString( 'Bewertungen', $ing['name'] );
            $this->assertStringNotContainsString( 'Kommentare', $ing['name'] );
            $this->assertStringNotContainsString( 'MIN', $ing['name'] );
            $this->assertStringNotContainsString( 'Uhr', $ing['name'] );
        }

        $names = array_column( $parsed['ingredients'], 'name' );
        $this->assertContains( 'Bergkäse',     $names );
        $this->assertContains( 'Schnittlauch', $names );
        $this->assertContains( 'Eier',         $names );
        $this->assertContains( 'Mehl',         $names );
        $this->assertContains( 'Salz',         $names );

        $this->assertNotEmpty( $parsed['instructions'] );
        $this->assertStringContainsString( 'Salzwasser', $parsed['instructions'][0] );
    }

    public function test_html_with_no_recipe_returns_null_not_garbage(): void {
        $html = '<html><body><h1>Some article</h1>'
              . '<p>1422 Bewertungen</p>'
              . '<p>5–15 MIN</p>'
              . '<p>— 8.2.2017 um 09:03 Uhr</p>'
              . '<p>Some prose paragraph with no recipe content here at all.</p>'
              . '</body></html>';
        $this->assertNull(
            Importer::from_html( $html ),
            'from_html should refuse to make up a recipe out of unrelated HTML.'
        );
    }

    public function test_paste_text_with_section_headers(): void {
        $text = "Pancakes\n\nIngredients\n200 g flour\n2 eggs\n300 ml milk\n1 pinch salt\n\nMethod\nMix everything\nFry in a pan\nServe hot";
        $parsed = Importer::from_text( $text );

        $this->assertIsArray( $parsed );
        $this->assertSame( 'Pancakes', $parsed['title'] );
        $this->assertCount( 4, $parsed['ingredients'] );
        $this->assertSame( '200', $parsed['ingredients'][0]['amount'] );
        $this->assertSame( 'g',   $parsed['ingredients'][0]['unit'] );
        $this->assertSame( 'flour', $parsed['ingredients'][0]['name'] );
        $this->assertCount( 3, $parsed['instructions'] );
    }

    public function test_simplehomeedit_recipe_card_sections_are_preserved_when_jsonld_is_flat(): void {
        $parsed = Importer::from_html( $this->fixture( 'simplehomeedit-dijon-salmon-sections.html' ) );

        $this->assertIsArray( $parsed );
        $this->assertSame( 'Dijon Salmon and Crispy Potatoes', $parsed['title'] );
        $this->assertCount( 4, $parsed['parts'] );
        $this->assertSame(
            [ 'POTATOES', 'SALMON', 'CREAMY LEMON DILL SAUCE', 'TO SERVE' ],
            array_column( $parsed['parts'], 'title' )
        );

        $this->assertSame( 'baby potatoes', $parsed['parts'][0]['ingredients'][0]['name'] );
        $this->assertSame( 'washed - no need to peel', $parsed['parts'][0]['ingredients'][0]['notes'] );
        $this->assertSame( 'salmon fillets', $parsed['parts'][1]['ingredients'][0]['name'] );
        $this->assertSame( 'whole-egg mayonnaise', $parsed['parts'][2]['ingredients'][0]['name'] );
        $this->assertSame( 'Green leafy salad', $parsed['parts'][3]['ingredients'][0]['name'] );

        // The compatibility field stays flat for existing recipe storage,
        // shopping-list code, and callers that do not understand parts yet.
        $this->assertCount( 5, $parsed['ingredients'] );
        $this->assertSame( 'baby potatoes', $parsed['ingredients'][0]['name'] );
        $this->assertSame( 'Green leafy salad', $parsed['ingredients'][4]['name'] );
    }

    public function test_jsonld_howto_sections_are_preserved_as_parts(): void {
        $html = '<script type="application/ld+json">' . json_encode( [
            '@context' => 'https://schema.org',
            '@type' => 'Recipe',
            'name' => 'Sectioned Instructions',
            'recipeIngredient' => [
                '200 g flour',
            ],
            'recipeInstructions' => [
                [
                    '@type' => 'HowToSection',
                    'name' => 'Dough',
                    'itemListElement' => [
                        [
                            '@type' => 'HowToStep',
                            'text' => 'Mix the flour and water.',
                        ],
                    ],
                ],
                [
                    '@type' => 'HowToSection',
                    'name' => 'Bake',
                    'itemListElement' => [
                        [
                            '@type' => 'HowToStep',
                            'text' => 'Bake until golden.',
                        ],
                    ],
                ],
            ],
        ] ) . '</script>';

        $parsed = Importer::from_html( $html );

        $this->assertIsArray( $parsed );
        $this->assertCount( 2, $parsed['parts'] );
        $this->assertSame( 'Dough', $parsed['parts'][0]['title'] );
        $this->assertSame( [ 'Mix the flour and water.' ], $parsed['parts'][0]['instructions'] );
        $this->assertSame( 'Bake', $parsed['parts'][1]['title'] );
        $this->assertSame( [ 'Bake until golden.' ], $parsed['parts'][1]['instructions'] );
        $this->assertSame( [ 'Mix the flour and water.', 'Bake until golden.' ], $parsed['instructions'] );
    }

    public function test_flat_jsonld_does_not_invent_parts(): void {
        $html = '<script type="application/ld+json">' . json_encode( [
            '@context' => 'https://schema.org',
            '@type' => 'Recipe',
            'name' => 'Flat Recipe',
            'recipeIngredient' => [
                '200 g flour',
                '2 eggs',
            ],
            'recipeInstructions' => [
                [
                    '@type' => 'HowToStep',
                    'text' => 'Mix everything.',
                ],
            ],
        ] ) . '</script>';

        $parsed = Importer::from_html( $html );

        $this->assertIsArray( $parsed );
        $this->assertSame( [], $parsed['parts'] );
        $this->assertCount( 2, $parsed['ingredients'] );
        $this->assertSame( [ 'Mix everything.' ], $parsed['instructions'] );
    }

    public function test_clean_step_strips_enumerators(): void {
        $this->assertSame( 'Mix everything', Importer::clean_step( '1. Mix everything' ) );
        $this->assertSame( 'Mix everything', Importer::clean_step( '1) Mix everything' ) );
        $this->assertSame( 'Mix everything', Importer::clean_step( 'Step 3: Mix everything' ) );
        $this->assertSame( 'Mix everything', Importer::clean_step( '- 4. Mix everything' ) );
        $this->assertSame( 'Mix everything', Importer::clean_step( '1. 1. Mix everything' ) );
        $this->assertSame( 'Whisk eggs',     Importer::clean_step( '• Whisk eggs' ) );
    }

    /** @dataProvider ingredientLines */
    public function test_parse_ingredient_line( string $line, array $expected ): void {
        $this->assertSame( $expected, Importer::parse_ingredient_line( $line ) );
    }

    public static function ingredientLines(): array {
        return [
            'metric mass'   => [ '200 g Mascarpone',  [ 'amount' => '200', 'unit' => 'g',     'name' => 'Mascarpone',  'notes' => '' ] ],
            'imperial cup'  => [ '1 cup flour',       [ 'amount' => '1',   'unit' => 'cup',   'name' => 'flour',       'notes' => '' ] ],
            'fraction'      => [ '1/2 tsp salt',      [ 'amount' => '1/2', 'unit' => 'tsp',   'name' => 'salt',        'notes' => '' ] ],
            'mixed number'  => [ '1 1/2 cups water',  [ 'amount' => '1 1/2', 'unit' => 'cup', 'name' => 'water',       'notes' => '' ] ],
            'unicode frac'  => [ '½ tsp pepper',      [ 'amount' => '½',   'unit' => 'tsp',   'name' => 'pepper',      'notes' => '' ] ],
            'german el'     => [ '2 EL Öl',           [ 'amount' => '2',   'unit' => 'tbsp',  'name' => 'Öl',          'notes' => '' ] ],
            'german stk'    => [ '1 Stk Zwiebel',     [ 'amount' => '1',   'unit' => 'piece', 'name' => 'Zwiebel',     'notes' => '' ] ],
            'paren note'    => [ '200 g Nudeln (Fleckerl)', [ 'amount' => '200', 'unit' => 'g', 'name' => 'Nudeln', 'notes' => 'Fleckerl' ] ],
            'alternate unit' => [ '700 g (1 1/2 lb) baby potatoes, washed', [ 'amount' => '700', 'unit' => 'g', 'name' => 'baby potatoes', 'notes' => 'washed' ] ],
            'no number'     => [ 'Salt to taste',     [ 'amount' => '',    'unit' => '',      'name' => 'Salt to taste', 'notes' => '' ] ],
        ];
    }
}
