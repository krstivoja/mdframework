<?php

declare(strict_types=1);

use FrontPress\BlockRegistry;
use FrontPress\BlockRenderer;
use PHPUnit\Framework\TestCase;

defined('FRONTPRESS_BOOT') || define('FRONTPRESS_BOOT', true);

/**
 * Covers the block-tree → HTML pipeline that backs the visual page
 * builder. Uses the framework's actual built-in blocks (cms/blocks/) so
 * the test exercises real templates, not stubs.
 */
class BlockRendererTest extends TestCase
{
    private BlockRegistry $registry;
    private BlockRenderer $renderer;

    protected function setUp(): void
    {
        $this->registry = new BlockRegistry(__DIR__ . '/../blocks');
        $this->renderer = new BlockRenderer($this->registry);
    }

    public function testHeadingBlockRenders(): void
    {
        $out = $this->renderer->render([
            ['type' => 'heading', 'data' => ['text' => 'Hello', 'level' => 'h1', 'align' => 'center']],
        ]);
        $this->assertStringContainsString('<h1', $out);
        $this->assertStringContainsString('text-align:center', $out);
        $this->assertStringContainsString('>Hello</h1>', $out);
        // data-fp-text marks the text node contenteditable in the visual canvas.
        $this->assertStringContainsString('data-fp-text="text"', $out);
    }

    public function testParagraphBlockRenders(): void
    {
        $out = $this->renderer->render([
            ['type' => 'paragraph', 'data' => ['text' => 'A test paragraph.']],
        ]);
        $this->assertStringContainsString('<p', $out);
        $this->assertStringContainsString('text-align:left', $out);
        $this->assertStringContainsString('>A test paragraph.</p>', $out);
    }

    public function testEditorModeWrapsBlocksWithDataAttributes(): void
    {
        $this->renderer->setEditorMode(true);
        $out = $this->renderer->render([
            ['id' => 'b-1', 'type' => 'heading', 'data' => ['text' => 'Hi', 'level' => 'h2']],
        ]);
        $this->assertStringContainsString('class="fp-block"',          $out);
        $this->assertStringContainsString('data-block-id="b-1"',        $out);
        $this->assertStringContainsString('data-block-type="heading"',  $out);
        $this->assertStringContainsString('data-block-container="0"',   $out);
    }

    public function testPublicModeOmitsEditorWrappers(): void
    {
        $out = $this->renderer->render([
            ['id' => 'b-1', 'type' => 'heading', 'data' => ['text' => 'Hi', 'level' => 'h2']],
        ]);
        $this->assertStringNotContainsString('data-block-id', $out);
        $this->assertStringNotContainsString('fp-block',      $out);
    }

    public function testUnknownBlockRendersAsComment(): void
    {
        $out = $this->renderer->render([
            ['type' => 'kitchen-sink', 'data' => []],
        ]);
        $this->assertStringContainsString('<!-- unknown block: kitchen-sink -->', $out);
    }

    public function testSectionRendersChildren(): void
    {
        $out = $this->renderer->render([
            [
                'type' => 'section',
                'data' => ['padding' => 'sm', 'maxWidth' => 'narrow'],
                'children' => [
                    ['type' => 'heading',   'data' => ['text' => 'Nested', 'level' => 'h2']],
                    ['type' => 'paragraph', 'data' => ['text' => 'Inside the section.']],
                ],
            ],
        ]);
        $this->assertStringContainsString('<section', $out);
        $this->assertStringContainsString('Nested', $out);
        $this->assertStringContainsString('Inside the section.', $out);
    }

    public function testColumnsRendersChildrenInGrid(): void
    {
        $out = $this->renderer->render([
            [
                'type' => 'columns',
                'data' => ['count' => '3', 'gap' => 'lg'],
                'children' => [
                    ['type' => 'paragraph', 'data' => ['text' => 'A']],
                    ['type' => 'paragraph', 'data' => ['text' => 'B']],
                    ['type' => 'paragraph', 'data' => ['text' => 'C']],
                ],
            ],
        ]);
        $this->assertMatchesRegularExpression('/grid-template-columns:repeat\(3,/', $out);
        $this->assertStringContainsString('>A</p>', $out);
        $this->assertStringContainsString('>C</p>', $out);
    }

    public function testInterpolatesMetaPlaceholders(): void
    {
        $out = $this->renderer->render(
            [['type' => 'heading', 'data' => ['text' => 'Hello, {{ meta.author }}!', 'level' => 'h2']]],
            ['author' => 'Marko'],
        );
        $this->assertStringContainsString('Hello, Marko!', $out);
        $this->assertStringNotContainsString('{{ meta.author }}', $out);
    }

    public function testMissingMetaPlaceholderRendersEmpty(): void
    {
        $out = $this->renderer->render(
            [['type' => 'paragraph', 'data' => ['text' => 'Hi {{ meta.missing }}.']]],
            [],
        );
        $this->assertStringContainsString('Hi .', $out);
    }

    public function testImageSkipsWhenSrcIsEmpty(): void
    {
        $out = $this->renderer->render([
            ['type' => 'image', 'data' => ['src' => '', 'alt' => 'nothing']],
        ]);
        $this->assertStringNotContainsString('<img', $out);
        $this->assertStringContainsString('<!-- image block: no src set -->', $out);
    }

    public function testRegistryListsAllBuiltInBlocks(): void
    {
        $blocks = $this->registry->all();
        $slugs  = array_keys($blocks);
        $this->assertContains('heading',   $slugs);
        $this->assertContains('paragraph', $slugs);
        $this->assertContains('image',     $slugs);
        $this->assertContains('section',   $slugs);
        $this->assertContains('columns',   $slugs);
    }

    public function testSectionAndColumnsAreContainerBlocks(): void
    {
        $blocks = $this->registry->all();
        $this->assertTrue($blocks['section']['hasChildren']);
        $this->assertTrue($blocks['columns']['hasChildren']);
        $this->assertFalse($blocks['heading']['hasChildren']);
    }

    public function testCodeBlockCompilesTwigSourceAtRenderTime(): void
    {
        $out = $this->renderer->render(
            [['type' => 'code', 'data' => ['source' => '<span>Hello, {{ page.title }}!</span>']]],
            ['title' => 'World'],
        );
        $this->assertStringContainsString('<span>Hello, World!</span>', $out);
    }

    public function testCodeBlockEmptySourceProducesNothingInPublic(): void
    {
        $out = $this->renderer->render(
            [['type' => 'code', 'data' => ['source' => '']]],
            [],
        );
        $this->assertSame('', trim($out));
    }

    public function testCodeBlockTwigErrorRendersAsHtmlCommentInPublic(): void
    {
        $out = $this->renderer->render(
            [['type' => 'code', 'data' => ['source' => '{% for x in %}']]],
            [],
        );
        $this->assertStringContainsString('<!-- code block error:', $out);
    }

    public function testCodeBlockTwigErrorRendersAsRedPanelInEditor(): void
    {
        $this->renderer->setEditorMode(true);
        $out = $this->renderer->render(
            [['id' => 'b-1', 'type' => 'code', 'data' => ['source' => '{% for x in %}']]],
            [],
        );
        $this->assertStringContainsString('Twig error:', $out);
        $this->assertStringContainsString('<pre', $out);
    }

    public function testCodeBlockOutputDoesNotAutoescape(): void
    {
        // The user is writing raw HTML; <strong> must survive intact.
        $out = $this->renderer->render(
            [['type' => 'code', 'data' => ['source' => '<strong>bold</strong>']]],
            [],
        );
        $this->assertStringContainsString('<strong>bold</strong>', $out);
        $this->assertStringNotContainsString('&lt;strong&gt;', $out);
    }
}
