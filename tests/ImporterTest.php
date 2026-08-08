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

    public function test_jsonld_total_time_only_falls_back_to_cook_time(): void {
        $html = '<script type="application/ld+json">' . json_encode( [
            '@context' => 'https://schema.org',
            '@type' => 'Recipe',
            'name' => 'Total Time Only',
            'totalTime' => 'PT25M',
            'recipeIngredient' => [ '200 g flour' ],
            'recipeInstructions' => [ [ '@type' => 'HowToStep', 'text' => 'Mix everything.' ] ],
        ] ) . '</script>';

        $parsed = Importer::from_html( $html );

        $this->assertIsArray( $parsed );
        $this->assertSame( 0, $parsed['prep_time'] );
        $this->assertSame(
            25,
            $parsed['cook_time'],
            'A source that supplies only totalTime should not import with no time at all.'
        );
    }

    public function test_explicit_prep_and_cook_times_win_over_total_time(): void {
        $html = '<script type="application/ld+json">' . json_encode( [
            '@context' => 'https://schema.org',
            '@type' => 'Recipe',
            'name' => 'All Three Times',
            'prepTime'  => 'PT5M',
            'cookTime'  => 'PT10M',
            'totalTime' => 'PT15M',
            'recipeIngredient' => [ '200 g flour' ],
            'recipeInstructions' => [ [ '@type' => 'HowToStep', 'text' => 'Mix everything.' ] ],
        ] ) . '</script>';

        $parsed = Importer::from_html( $html );

        $this->assertIsArray( $parsed );
        $this->assertSame( 5,  $parsed['prep_time'] );
        $this->assertSame( 10, $parsed['cook_time'] );
    }

    public function test_hellofresh_real_world_page(): void {
        $parsed = Importer::from_html( $this->fixture( 'hellofresh-speedy-prawn-rigatoni.html' ) );

        $this->assertIsArray( $parsed );

        // The page publishes only totalTime, and that value is the total the
        // site itself displays ("Total: 25 minutes"). Keep it rather than
        // discarding it, and do not invent a prep/cook split — the page's
        // __NEXT_DATA__ carries a second, larger "totalTime" that does not
        // correspond to anything the reader is shown.
        $this->assertSame( 0,  $parsed['prep_time'] );
        $this->assertSame( 25, $parsed['cook_time'] );

        // The unit(s) / sachet(s) idiom must keep the real ingredient name.
        $names = array_column( $parsed['ingredients'], 'name' );
        $this->assertContains( 'Courgette', $names );
        $this->assertContains( 'Vegetable Stock', $names );

        // Instruction markup must not survive into stored content.
        $this->assertStringNotContainsString( '<li>', $parsed['instructions'][0] );
        $this->assertStringNotContainsString( '<strong>', $parsed['instructions'][0] );
        $this->assertStringContainsString( 'Boil a large pot of salted water', $parsed['instructions'][0] );
    }

    public function test_clean_step_strips_html_markup(): void {
        $this->assertSame(
            'Boil a large pot of salted water. Cook until softened, 12 mins.',
            Importer::clean_step(
                '<ul><li>Boil a large pot of <strong>salted water</strong>.</li>'
                . '<li>Cook until softened, 12 mins.</li></ul>'
            )
        );
        $this->assertSame( 'Mix everything', Importer::clean_step( '<p>Mix everything</p>' ) );
        $this->assertSame( 'Whisk eggs then fold in flour', Importer::clean_step( 'Whisk eggs<br>then fold in flour' ) );
        // Enumerator stripping still applies once the tags are gone.
        $this->assertSame( 'Mix everything', Importer::clean_step( '<p>1. Mix everything</p>' ) );
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
            // Meal-kit pluralisation idiom: the "(s)" must not be read as the
            // start of a parenthetical note, which would leave name = "unit".
            'unit(s) idiom'   => [ '1 unit(s) Courgette',           [ 'amount' => '1', 'unit' => 'piece',  'name' => 'Courgette',       'notes' => '' ] ],
            'unit(s) frac'    => [ '½ unit(s) Lemon',               [ 'amount' => '½', 'unit' => 'piece',  'name' => 'Lemon',           'notes' => '' ] ],
            'sachet(s) idiom' => [ '1 sachet(s) Vegetable Stock',   [ 'amount' => '1', 'unit' => 'packet', 'name' => 'Vegetable Stock', 'notes' => '' ] ],
            'pack(s) idiom'   => [ '1 pack(s) Coconut Milk',        [ 'amount' => '1', 'unit' => 'packet', 'name' => 'Coconut Milk',    'notes' => '' ] ],
        ];
    }
}
