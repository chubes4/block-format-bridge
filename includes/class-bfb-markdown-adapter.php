<?php
/**
 * Markdown format adapter.
 *
 * Write side (`to_blocks()`):
 *   Delegates markdown conversion to the canonical blocks-engine PHP
 *   transformer after applying BFB's public markdown input filter.
 *
 * Read side (`from_blocks()`):
 *   Delegates blocks → markdown to the canonical blocks-engine PHP transformer.
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
	 * Canonical format bridge.
	 *
	 * @var \Automattic\BlocksEngine\PhpTransformer\FormatBridge\FormatBridge|null
	 */
	private $bridge;

	/**
	 * Constructor.
	 *
	 * @param \Automattic\BlocksEngine\PhpTransformer\FormatBridge\FormatBridge|null $bridge Canonical bridge.
	 */
	public function __construct( $bridge = null ) {
		$this->bridge = $bridge ? $bridge : ( function_exists( 'bfb_format_bridge' ) ? bfb_format_bridge() : null );
	}

	/**
	 * @inheritDoc
	 */
	public function slug(): string {
		return 'markdown';
	}

	/**
	 * @inheritDoc
	 */
	public function to_blocks( string $content, array $options = array() ): array {
		if ( '' === $content ) {
			return array();
		}

		/**
		 * Pre-process raw markdown before it reaches CommonMark.
		 *
		 * Useful for opinionated transformations that the bridge should
		 * not encode itself (e.g. linkifying bare domain URLs to
		 * `https://`, normalising smart quotes, etc.).
		 *
		 * @since 0.3.0
		 *
		 * @param string $markdown Markdown source.
		 */
		$content = (string) apply_filters( 'bfb_markdown_input', $content );

		if ( ! function_exists( 'bfb_transformer_convert_result' ) && ! $this->bridge ) {
			do_action(
				'bfb_diagnostic',
				'blocks_engine_markdown_transformer_unavailable',
				'blocks-engine PHP transformer is unavailable for markdown conversion.',
				array( 'adapter' => 'markdown' )
			);
			return array();
		}

		try {
			$result = function_exists( 'bfb_transformer_convert_result' ) ? bfb_transformer_convert_result( $content, 'markdown', 'blocks', $options ) : null;
			if ( is_array( $result ) && isset( $result['blocks'] ) && is_array( $result['blocks'] ) ) {
				return $result['blocks'];
			}

			return $this->bridge ? $this->bridge->toBlocks( $content, 'markdown', $options ) : array();
		} catch ( Throwable $e ) {
			do_action(
				'bfb_diagnostic',
				'blocks_engine_markdown_conversion_failed',
				'blocks-engine PHP transformer failed markdown conversion.',
				array(
					'adapter' => 'markdown',
					'error'   => $e->getMessage(),
				)
			);
			return array();
		}
	}

	/**
	 * @inheritDoc
	 *
	 * Delegates blocks → markdown to the canonical bridge.
	 *
	 * @param array $blocks Block array (parse_blocks() shape).
	 * @return string Markdown representation. Empty string on failure.
	 */
	public function from_blocks( array $blocks, array $options = array() ): string {
		if ( empty( $blocks ) ) {
			return '';
		}

		if ( ! function_exists( 'bfb_transformer_convert_result' ) && ! $this->bridge ) {
			do_action(
				'bfb_diagnostic',
				'blocks_engine_markdown_transformer_unavailable',
				'blocks-engine PHP transformer is unavailable for markdown rendering.',
				array( 'adapter' => 'markdown' )
			);
			return '';
		}

		try {
			$markdown = '';
			if ( function_exists( 'bfb_transformer_convert_result' ) ) {
				$result = bfb_transformer_convert_result( serialize_blocks( $blocks ), 'blocks', 'markdown', $options );
				if ( is_array( $result ) && isset( $result['documents'][0]['content'] ) && is_string( $result['documents'][0]['content'] ) ) {
					$markdown = $result['documents'][0]['content'];
				}
			}

			if ( '' === $markdown && $this->bridge ) {
				$markdown = $this->bridge->convert( serialize_blocks( $blocks ), 'blocks', 'markdown', $options );
			}

			return (string) apply_filters( 'bfb_markdown_output', $markdown, '', $blocks );
		} catch ( Throwable $e ) {
			do_action(
				'bfb_diagnostic',
				'blocks_engine_markdown_render_failed',
				'blocks-engine PHP transformer failed markdown rendering.',
				array(
					'adapter' => 'markdown',
					'error'   => $e->getMessage(),
				)
			);
			return '';
		}
	}
}
