<?php
/**
 * Markdown format adapter.
 *
 * Write side (`to_blocks()`):
 *   Runs CommonMark + GFM via league/commonmark to convert the markdown
 *   source to HTML, then routes the HTML through the registered HTML
 *   adapter to land in block form.
 *
 * Read side (`from_blocks()`):
 *   Renders blocks → HTML via `do_blocks()`, then converts HTML → markdown
 *   via league/html-to-markdown. Both libraries are vendor-prefixed under
 *   the `BlockFormatBridge\Vendor` namespace by the build pipeline; the
 *   adapter prefers the prefixed namespace and falls back to unprefixed
 *   (dev-mode `composer install` without the build step).
 *
 * @package BlockFormatBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Markdown ↔ Blocks adapter.
 */
class BFB_Markdown_Adapter implements BFB_Format_Adapter {

	/**
	 * @inheritDoc
	 */
	public function slug(): string {
		return 'markdown';
	}

	/**
	 * @inheritDoc
	 */
	public function to_blocks( string $content ): array {
		if ( '' === $content ) {
			return array();
		}

		$html = $this->markdown_to_html( $content );
		if ( '' === $html ) {
			return array();
		}

		$html_adapter = BFB_Adapter_Registry::get( 'html' );
		if ( ! $html_adapter ) {
			return array();
		}

		return $html_adapter->to_blocks( $html );
	}

	/**
	 * @inheritDoc
	 *
	 * Renders blocks → HTML (via `do_blocks()` + `serialize_blocks()`)
	 * and converts the resulting HTML to markdown via
	 * league/html-to-markdown.
	 *
	 * Dynamic blocks render through their PHP callback, so server-side
	 * blocks (latest-posts, navigation, query loop, etc.) appear in the
	 * markdown as their rendered HTML output rather than block-comment
	 * markup.
	 *
	 * @param array $blocks Block array (parse_blocks() shape).
	 * @return string Markdown representation. Empty string on failure.
	 */
	public function from_blocks( array $blocks ): string {
		if ( empty( $blocks ) ) {
			return '';
		}

		// Render dynamic blocks through their server-side callbacks, then
		// serialise. We pass the rendered HTML straight to the html-to-md
		// converter so dynamic content shows up as resolved HTML.
		$html = '';
		foreach ( $blocks as $block ) {
			$html .= render_block( $block );
		}

		if ( '' === trim( $html ) ) {
			return '';
		}

		$markdown = $this->html_to_markdown( $html );

		/**
		 * Filters the markdown output produced by the markdown adapter.
		 *
		 * Mirrors `roots/post-content-to-markdown`'s
		 * `post_content_to_markdown/markdown_output` filter so consumers
		 * can swap in a richer implementation without changing the
		 * bridge contract.
		 *
		 * @since 0.2.0
		 *
		 * @param string $markdown Markdown produced by the converter.
		 * @param string $html     Source HTML that was converted.
		 * @param array  $blocks   Original block array.
		 */
		return (string) apply_filters( 'bfb_markdown_output', $markdown, $html, $blocks );
	}

	/**
	 * @inheritDoc
	 */
	public function detect( string $content ): bool {
		// Reserved for future use. v0.1.0 doesn't auto-detect.
		unset( $content );
		return false;
	}

	/**
	 * Render markdown to HTML using league/commonmark with GFM extensions.
	 *
	 * Picks the prefixed namespace from the build distribution when
	 * available, falling back to the unprefixed namespace for dev mode.
	 *
	 * @param string $markdown Raw markdown source.
	 * @return string HTML. Empty string on failure.
	 */
	protected function markdown_to_html( string $markdown ): string {
		$prefixed_converter = '\\BlockFormatBridge\\Vendor\\League\\CommonMark\\GithubFlavoredMarkdownConverter';
		$unprefixed         = '\\League\\CommonMark\\GithubFlavoredMarkdownConverter';

		$class = null;
		if ( class_exists( $prefixed_converter ) ) {
			$class = $prefixed_converter;
		} elseif ( class_exists( $unprefixed ) ) {
			$class = $unprefixed;
		}

		if ( null === $class ) {
			error_log( '[Block Format Bridge] league/commonmark is not loaded; markdown conversion unavailable.' );
			return '';
		}

		try {
			$converter = new $class();
			$result    = $converter->convert( $markdown );
			return (string) $result;
		} catch ( \Throwable $e ) {
			error_log( sprintf( '[Block Format Bridge] CommonMark conversion failed: %s', $e->getMessage() ) );
			return '';
		}
	}

	/**
	 * Convert HTML to markdown using league/html-to-markdown.
	 *
	 * Picks the prefixed namespace from the build distribution when
	 * available, falling back to the unprefixed namespace for dev mode.
	 *
	 * Default converter options:
	 *   - header_style: 'atx'    (`#` prefix instead of underline)
	 *   - strip_tags:   true     drop unsupported HTML
	 *   - remove_nodes: 'script style'
	 *   - hard_break:   true     `<br>` → newline
	 *
	 * Filterable via `bfb_html_to_markdown_options`.
	 *
	 * @param string $html Source HTML.
	 * @return string Markdown. Empty string on failure.
	 */
	protected function html_to_markdown( string $html ): string {
		$prefixed   = '\\BlockFormatBridge\\Vendor\\League\\HTMLToMarkdown\\HtmlConverter';
		$unprefixed = '\\League\\HTMLToMarkdown\\HtmlConverter';

		$class = null;
		if ( class_exists( $prefixed ) ) {
			$class = $prefixed;
		} elseif ( class_exists( $unprefixed ) ) {
			$class = $unprefixed;
		}

		if ( null === $class ) {
			error_log( '[Block Format Bridge] league/html-to-markdown is not loaded; HTML→markdown conversion unavailable.' );
			return '';
		}

		$defaults = array(
			'header_style' => 'atx',
			'strip_tags'   => true,
			'remove_nodes' => 'script style',
			'hard_break'   => true,
		);

		/**
		 * Filters the option array passed to league/html-to-markdown.
		 *
		 * Mirrors `roots/post-content-to-markdown`'s
		 * `post_content_to_markdown/converter_options`.
		 *
		 * @since 0.2.0
		 *
		 * @param array  $options Converter options.
		 * @param string $html    Source HTML.
		 */
		$options = (array) apply_filters( 'bfb_html_to_markdown_options', $defaults, $html );

		try {
			$converter = new $class( $options );
			return (string) $converter->convert( $html );
		} catch ( \Throwable $e ) {
			error_log( sprintf( '[Block Format Bridge] HTML→markdown conversion failed: %s', $e->getMessage() ) );
			return '';
		}
	}
}
