<?php
/**
 * Markdown format adapter.
 *
 * `to_blocks()` runs CommonMark + GFM via league/commonmark to convert
 * the markdown source to HTML, then routes the HTML through the
 * registered HTML adapter to land in block form.
 *
 * The `BlockFormatBridge\Vendor` namespace is preferred (php-scoped
 * build distribution); the unprefixed `League\CommonMark` namespace is
 * used in development mode when the package is installed via
 * `composer install` without running the build script.
 *
 * `from_blocks()` is reserved for Phase 2 (read side) — see the design
 * doc at `projects/block-format-bridge-bidirectional-content-format-plugin-design`.
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
	 * Phase 2 will route through roots/post-content-to-markdown (or a
	 * direct league/html-to-markdown integration). Phase 1 returns an
	 * empty string — the read side is intentionally out of scope until
	 * the dependency choice is finalised.
	 */
	public function from_blocks( array $blocks ): string {
		// TODO(phase-2): convert blocks → HTML (do_blocks) → markdown
		// using roots/post-content-to-markdown or a direct
		// league/html-to-markdown integration. See the design doc:
		// projects/block-format-bridge-bidirectional-content-format-plugin-design
		unset( $blocks );
		return '';
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
}
