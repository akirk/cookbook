<?php

use Cookbook\StaticArchiveService;
use PHPUnit\Framework\TestCase;

class StaticArchiveServiceTest extends TestCase {

    private StaticArchiveService $archive;

    protected function setUp(): void {
        $this->archive = ( new ReflectionClass( StaticArchiveService::class ) )->newInstanceWithoutConstructor();
    }

    public function test_static_archive_renders_ingredient_sections_as_html(): void {
        $parts = $this->invoke( 'clean_static_archive_parts', [
            [
                'title'       => 'Sauce',
                'ingredients' => [
                    [ 'amount' => '1', 'unit' => 'tbsp', 'name' => 'Dijon mustard', 'notes' => '' ],
                ],
            ],
            [
                'title'       => 'Fish',
                'ingredients' => [
                    [ 'amount' => '4', 'unit' => '', 'name' => 'salmon fillets', 'notes' => 'skin on' ],
                ],
            ],
        ] );

        $html = $this->invoke( 'render_static_archive_ingredient_parts_html', $parts );

        $this->assertStringContainsString( '<section class="recipe-part"><h3>Sauce</h3><ul><li>1 tbsp Dijon mustard</li></ul></section>', $html );
        $this->assertStringContainsString( '<section class="recipe-part"><h3>Fish</h3><ul><li>4 salmon fillets (skin on)</li></ul></section>', $html );
    }

    public function test_static_archive_renders_instruction_sections_as_markdown(): void {
        $parts = $this->invoke( 'clean_static_archive_parts', [
            [
                'title'        => 'Potatoes',
                'instructions' => [
                    '1. Boil potatoes.',
                    '<strong>Roast until crisp.</strong>',
                ],
            ],
            [
                'title'        => 'Sauce',
                'instructions' => [
                    'Mix sauce ingredients.',
                ],
            ],
        ] );

        $markdown = $this->invoke( 'render_static_archive_instruction_parts_markdown', $parts );

        $this->assertSame(
            "### Potatoes\n\n1. Boil potatoes.\n2. Roast until crisp.\n\n### Sauce\n\n1. Mix sauce ingredients.",
            $markdown
        );
    }

    private function invoke( string $method, ...$args ) {
        $reflection = new ReflectionMethod( StaticArchiveService::class, $method );
        $reflection->setAccessible( true );
        return $reflection->invokeArgs( $this->archive, $args );
    }
}
