<?php
/**
 * Smoke coverage for the public conversion API.
 *
 * @package BlockFormatBridge
 */

/**
 * Exercises the conversion paths BFB exposes through bfb_convert().
 */
class BFBConversionUnitTest extends WP_UnitTestCase {

	/**
	 * HTML input should route through html-to-blocks-converter for core block transforms.
	 */
	public function test_html_to_blocks_covers_core_transforms(): void {
		foreach ( range( 1, 6 ) as $level ) {
			$blocks = $this->blocks_from( "<h{$level}>Heading {$level}</h{$level}>", 'html' );

			$this->assertSame( 'core/heading', $blocks[0]['blockName'] ?? null, "h{$level} converts to heading block." );
			$this->assertSame( $level, $blocks[0]['attrs']['level'] ?? null, "h{$level} preserves heading level." );
		}

		$paragraph = $this->blocks_from( '<p>Text with <strong>bold</strong>, <em>emphasis</em>, and <a href="https://example.com">a link</a>.</p>', 'html' );
		$this->assertSame( 'core/paragraph', $paragraph[0]['blockName'] ?? null );
		$this->assertStringContainsString( '<strong>bold</strong>', $paragraph[0]['innerHTML'] ?? '' );
		$this->assertStringContainsString( '<em>emphasis</em>', $paragraph[0]['innerHTML'] ?? '' );
		$this->assertStringContainsString( '<a href="https://example.com">a link</a>', $paragraph[0]['innerHTML'] ?? '' );

		$unordered = $this->blocks_from( '<ul><li>One</li><li>Two</li></ul>', 'html' );
		$this->assertSame( 'core/list', $unordered[0]['blockName'] ?? null );
		$this->assertFalse( $unordered[0]['attrs']['ordered'] ?? true );
		$this->assertSame( 'core/list-item', $unordered[0]['innerBlocks'][0]['blockName'] ?? null );

		$ordered = $this->blocks_from( '<ol><li>First</li><li>Second</li></ol>', 'html' );
		$this->assertSame( 'core/list', $ordered[0]['blockName'] ?? null );
		$this->assertTrue( $ordered[0]['attrs']['ordered'] ?? false );
		$this->assertSame( 'core/list-item', $ordered[0]['innerBlocks'][0]['blockName'] ?? null );

		$quote = $this->blocks_from( '<blockquote><p>Quoted text</p></blockquote>', 'html' );
		$this->assertSame( 'core/quote', $quote[0]['blockName'] ?? null );
		$this->assertSame( 'core/paragraph', $quote[0]['innerBlocks'][0]['blockName'] ?? null );
		$this->assertStringContainsString( 'Quoted text', $quote[0]['innerBlocks'][0]['innerHTML'] ?? '' );

		$nested_quote = $this->blocks_from( '<blockquote><blockquote><p>Deep quote</p></blockquote></blockquote>', 'html' );
		$this->assertSame( 'core/quote', $nested_quote[0]['blockName'] ?? null );
		$this->assertSame( 'core/quote', $nested_quote[0]['innerBlocks'][0]['blockName'] ?? null );
		$this->assertSame( 'core/paragraph', $nested_quote[0]['innerBlocks'][0]['innerBlocks'][0]['blockName'] ?? null );

		$code = $this->blocks_from( '<pre><code class="language-php">echo "hi";</code></pre>', 'html' );
		$this->assertSame( 'core/code', $code[0]['blockName'] ?? null );
		$this->assertSame( 'language-php', $code[0]['attrs']['className'] ?? null );
		$this->assertStringContainsString( 'echo "hi";', html_entity_decode( $code[0]['innerHTML'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

		$table = $this->blocks_from( '<table><thead><tr><th>Name</th></tr></thead><tbody><tr><td>BFB</td></tr></tbody></table>', 'html' );
		$this->assertSame( 'core/table', $table[0]['blockName'] ?? null );
		$this->assertStringContainsString( '<th>Name</th>', $table[0]['innerHTML'] ?? '' );
		$this->assertStringContainsString( '<td>BFB</td>', $table[0]['innerHTML'] ?? '' );
	}

	/**
	 * Markdown input should use CommonMark/GFM, then the same HTML adapter path.
	 */
	public function test_markdown_to_blocks_covers_commonmark_and_gfm_paths(): void {
		$markdown = <<<MARKDOWN
# Markdown Heading

Paragraph with **bold**, *emphasis*, [a link](https://example.com), ~~strike~~, and <https://example.com/auto>.

- One
- Two

1. First
2. Second

> Quote text
>
> > Nested quote

```php
echo "hi";
```

| Name | Value |
| ---- | ----- |
| BFB  | Works |
MARKDOWN;

		$blocks = $this->blocks_from( $markdown, 'markdown' );
		$flat   = $this->flatten_blocks( $blocks );

		$this->assertContains( 'core/heading', $flat );
		$this->assertContains( 'core/paragraph', $flat );
		$this->assertContains( 'core/list', $flat );
		$this->assertContains( 'core/list-item', $flat );
		$this->assertContains( 'core/quote', $flat );
		$this->assertContains( 'core/code', $flat );
		$this->assertContains( 'core/table', $flat );

		$serialized = bfb_convert( $markdown, 'markdown', 'blocks' );
		$this->assertStringContainsString( '<strong>bold</strong>', $serialized );
		$this->assertStringContainsString( '<em>emphasis</em>', $serialized );
		$this->assertStringContainsString( '<del>strike</del>', $serialized );
		$this->assertStringContainsString( 'https://example.com/auto', $serialized );
		$this->assertStringContainsString( 'language-php', $serialized );
	}

	/**
	 * Blocks should render to HTML through WordPress' real render_block() path.
	 */
	public function test_blocks_to_html_renders_static_and_dynamic_blocks(): void {
		$static_blocks = '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Rendered Heading</h1><!-- /wp:heading -->'
			. '<!-- wp:paragraph --><p class="wp-block-paragraph">Rendered paragraph.</p><!-- /wp:paragraph -->';

		$html = bfb_convert( $static_blocks, 'blocks', 'html' );
		$this->assertStringContainsString( '<h1 class="wp-block-heading">Rendered Heading</h1>', $html );
		$this->assertStringContainsString( '<p class="wp-block-paragraph">Rendered paragraph.</p>', $html );

		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Dynamic BFB Post',
				'post_status' => 'publish',
			)
		);
		$this->assertIsInt( $post_id );

		$dynamic = bfb_convert( '<!-- wp:latest-posts {"postsToShow":1} /-->', 'blocks', 'html' );
		$this->assertStringContainsString( 'wp-block-latest-posts', $dynamic );
		$this->assertStringContainsString( 'Dynamic BFB Post', $dynamic );
	}

	/**
	 * Blocks should render to markdown through the read-side markdown adapter.
	 */
	public function test_blocks_to_markdown_covers_structural_elements_and_lossy_expectations(): void {
		$blocks = ''
			. '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Markdown Heading</h1><!-- /wp:heading -->'
			. '<!-- wp:paragraph --><p>Paragraph with <strong>bold</strong>.</p><!-- /wp:paragraph -->'
			. '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>One</li><!-- /wp:list-item --></ul><!-- /wp:list -->'
			. '<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>Quote text</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote -->'
			. '<!-- wp:code --><pre class="wp-block-code language-php"><code>echo &quot;hi&quot;;</code></pre><!-- /wp:code -->'
			. '<!-- wp:table --><figure class="wp-block-table"><table><tbody><tr><td>Name</td><td>BFB</td></tr></tbody></table></figure><!-- /wp:table -->';

		$markdown = bfb_convert( $blocks, 'blocks', 'markdown' );

		$this->assertStringContainsString( '# Markdown Heading', $markdown );
		$this->assertStringContainsString( 'Paragraph with **bold**.', $markdown );
		$this->assertStringContainsString( '- One', $markdown );
		$this->assertStringContainsString( '> Quote text', $markdown );
		$this->assertStringContainsString( 'echo "hi";', $markdown );
		$this->assertStringContainsString( '| Name | BFB |', $markdown );

		// league/html-to-markdown currently preserves code content but not the language hint.
		$this->assertStringNotContainsString( '```php', $markdown );
	}

	/**
	 * Non-block formats should compose through the block pivot in both directions.
	 */
	public function test_composition_paths_route_through_blocks_pivot(): void {
		$markdown_to_html = bfb_convert( "# Composed Heading\n\n> Composed quote", 'markdown', 'html' );
		$this->assertStringContainsString( '<h1 class="wp-block-heading">Composed Heading</h1>', $markdown_to_html );
		$this->assertStringContainsString( '<blockquote class="wp-block-quote', $markdown_to_html );
		$this->assertStringContainsString( 'Composed quote', $markdown_to_html );

		$html_to_markdown = bfb_convert( '<h1>HTML Heading</h1><blockquote><p>HTML quote</p></blockquote>', 'html', 'markdown' );
		$this->assertStringContainsString( '# HTML Heading', $html_to_markdown );
		$this->assertStringContainsString( '> HTML quote', $html_to_markdown );
	}

	/**
	 * Convert content into parsed blocks through BFB's public API.
	 *
	 * @param string $content Source content.
	 * @param string $from    Source format.
	 * @return array<int, array<string, mixed>> Parsed block list.
	 */
	private function blocks_from( string $content, string $from ): array {
		$serialized = bfb_convert( $content, $from, 'blocks' );
		$this->assertNotSame( '', $serialized, "{$from} conversion should produce serialized blocks." );

		return parse_blocks( $serialized );
	}

	/**
	 * Return block names from a parsed block tree.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array<int, string> Block names.
	 */
	private function flatten_blocks( array $blocks ): array {
		$names = array();
		foreach ( $blocks as $block ) {
			if ( ! empty( $block['blockName'] ) ) {
				$names[] = $block['blockName'];
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$names = array_merge( $names, $this->flatten_blocks( $block['innerBlocks'] ) );
			}
		}

		return $names;
	}
}
