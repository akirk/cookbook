<?php

use Cookbook\RecipeService;
use PHPUnit\Framework\TestCase;

class RecipeServiceTest extends TestCase {

    private RecipeService $recipes;

    protected function setUp(): void {
        $this->recipes = ( new ReflectionClass( RecipeService::class ) )->newInstanceWithoutConstructor();
    }

    public function test_submitted_ingredient_parts_ignore_empty_sections_with_empty_ingredients(): void {
        $parts = $this->invoke( 'sanitize_submitted_ingredient_parts', [
            [
                'title'       => 'Sauce',
                'ingredients' => [
                    [ 'amount' => '', 'unit' => '', 'name' => '', 'notes' => '' ],
                ],
            ],
            [
                'title'       => 'Dough',
                'ingredients' => [
                    [ 'amount' => '200', 'unit' => 'g', 'name' => 'flour', 'notes' => '' ],
                    [ 'amount' => '', 'unit' => '', 'name' => '', 'notes' => '' ],
                ],
            ],
        ] );

        $this->assertCount( 1, $parts );
        $this->assertSame( 'Dough', $parts[0]['title'] );
        $this->assertSame( 'flour', $parts[0]['ingredients'][0]['name'] );
        $this->assertCount( 1, $parts[0]['ingredients'] );
    }

    public function test_normalized_recipe_parts_ignore_title_only_sections(): void {
        $parts = $this->invoke( 'normalize_recipe_parts_array', [
            [
                'title'       => 'Empty',
                'ingredients' => [
                    [ 'amount' => '', 'unit' => '', 'name' => '', 'notes' => '' ],
                ],
                'instructions' => [ '' ],
            ],
            [
                'title'        => 'Steps',
                'ingredients'  => [],
                'instructions' => [ '1. Mix everything' ],
            ],
        ], false );

        $this->assertCount( 1, $parts );
        $this->assertSame( 'Steps', $parts[0]['title'] );
        $this->assertSame( [ 'Mix everything' ], $parts[0]['instructions'] );
    }

    private function invoke( string $method, ...$args ) {
        $reflection = new ReflectionMethod( RecipeService::class, $method );
        $reflection->setAccessible( true );
        return $reflection->invokeArgs( $this->recipes, $args );
    }
}
