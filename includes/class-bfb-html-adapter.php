<?php
/**
 * HTML format adapter.
 *
 * `to_blocks()` delegates to `html_to_blocks_raw_handler()` from
 * `chubes4/html-to-blocks-converter`, which BFB bundles as a Composer
 * dependency. Built distributions call the vendor-prefixed function;
 * dev-mode/plugin installs can still call the unprefixed global.
 *
 * `from_blocks()` renders blocks through `do_blocks()` so dynamic
 * blocks resolve to their server-side HTML output.
 *
 * @package BlockFormatBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTML ↔ Blocks adapter.
 */
class BFB_HTML_Adapter implements BFB_Format_Adapter {

	/**
	 * @inheritDoc
	 */
	public function slug(): string {
		return 'html';
	}

	/**
	 * @inheritDoc
	 */
	public function to_blocks( string $content ): array {
		if ( '' === $content ) {
			return array();
		}

		// Already block markup — parse and return.
		if ( false !== strpos( $content, '<!-- wp:' ) ) {
			$parsed = parse_blocks( $content );
			return is_array( $parsed ) ? $parsed : array();
		}

		if ( function_exists( '\BlockFormatBridge\Vendor\html_to_blocks_raw_handler' ) ) {
			$blocks = \BlockFormatBridge\Vendor\html_to_blocks_raw_handler( array( 'HTML' => $content ) );
			return is_array( $blocks ) ? $blocks : array();
		}

		if ( function_exists( 'html_to_blocks_raw_handler' ) ) {
			$blocks = html_to_blocks_raw_handler( array( 'HTML' => $content ) );
			return is_array( $blocks ) ? $blocks : array();
		}

		// Should only happen in a broken build: BFB requires
		// chubes4/html-to-blocks-converter and built distributions ship
		// the prefixed function above.
		error_log( '[Block Format Bridge] html-to-blocks-converter is unavailable; falling back to a freeform block.' );
		return array(
			array(
				'blockName'    => 'core/freeform',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => $content,
				'innerContent' => array( $content ),
			),
		);
	}

	/**
	 * @inheritDoc
	 *
	 * Renders each block through `render_block()` so dynamic blocks
	 * resolve to their server-side HTML output. Static blocks pass
	 * through their inner HTML untouched.
	 */
	public function from_blocks( array $blocks ): string {
		if ( empty( $blocks ) ) {
			return '';
		}

		$html = '';
		foreach ( $blocks as $block ) {
			$html .= render_block( $block );
		}
		return $html;
	}

	/**
	 * @inheritDoc
	 */
	public function detect( string $content ): bool {
		// Reserved for future use. v0.1.0 doesn't auto-detect.
		unset( $content );
		return false;
	}
}
