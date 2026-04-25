<?php
/**
 * HTML format adapter.
 *
 * `to_blocks()` delegates to `html_to_blocks_raw_handler()` from the
 * `chubes4/html-to-blocks-converter` plugin, which must be installed
 * and active for HTML conversion to work. If the function is missing,
 * the adapter falls back to `parse_blocks()` so the system fails soft
 * (returning a single core/freeform block) rather than hard.
 *
 * `from_blocks()` returns serialized block markup. Phase 1 does not
 * call `do_blocks()`; that is reserved for the read-side API in
 * Phase 2.
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

		if ( function_exists( 'html_to_blocks_raw_handler' ) ) {
			$blocks = html_to_blocks_raw_handler( array( 'HTML' => $content ) );
			return is_array( $blocks ) ? $blocks : array();
		}

		// html-to-blocks-converter is not installed; fail soft by
		// returning a single freeform block carrying the raw HTML.
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
	 */
	public function from_blocks( array $blocks ): string {
		if ( empty( $blocks ) ) {
			return '';
		}

		return serialize_blocks( $blocks );
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
