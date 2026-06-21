<?php
/**
 * HTML format adapter.
 *
 * `to_blocks()` delegates to the canonical blocks-engine PHP transformer while
 * preserving BFB's public filter surface.
 *
 * `from_blocks()` delegates block rendering to the canonical bridge so dynamic
 * block behavior stays owned by the transformer package.
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
		return 'html';
	}

	/**
	 * Convert through the injected or global canonical result surface.
	 *
	 * @param string               $content Source content.
	 * @param string               $from    Source format slug.
	 * @param string               $to      Target format slug.
	 * @param array<string, mixed> $options Conversion options.
	 * @return array<string, mixed>|null
	 */
	private function convert_result( string $content, string $from, string $to, array $options = array() ): ?array {
		if ( $this->bridge && method_exists( $this->bridge, 'convertResult' ) ) {
			$result = $this->bridge->convertResult( $content, $from, $to, $options );
			return is_object( $result ) && method_exists( $result, 'toArray' ) ? $result->toArray() : ( is_array( $result ) ? $result : null );
		}

		return function_exists( 'bfb_transformer_convert_result' ) ? bfb_transformer_convert_result( $content, $from, $to, $options ) : null;
	}

	/**
	 * @inheritDoc
	 */
	public function to_blocks( string $content, array $options = array() ): array {
		if ( '' === $content ) {
			return array();
		}

		// Already block markup — parse and return.
		if ( false !== strpos( $content, '<!-- wp:' ) ) {
			return parse_blocks( $content );
		}

		$args = array_merge( $options, array( 'HTML' => $content ) );

		/**
		 * Filters the argument array passed to the HTML transformer.
		 *
		 * BFB reserves the `HTML` key for source content. Per-call conversion
		 * options, such as `context` and `mode`, are forwarded alongside it for
		 * the transformer to consume when supported.
		 *
		 * @since 0.5.0
		 *
		 * @param array<string, mixed> $args    Raw handler arguments.
		 * @param string               $content Source HTML.
		 * @param array<string, mixed> $options Per-call conversion options.
		 */
		$args         = (array) apply_filters( 'bfb_html_to_blocks_args', $args, $content, $options );
		$args['HTML'] = $content;

		if ( ! function_exists( 'bfb_transformer_convert_result' ) ) {
			do_action(
				'bfb_diagnostic',
				'blocks_engine_html_transformer_unavailable',
				'blocks-engine PHP transformer is unavailable for HTML conversion.',
				array( 'adapter' => 'html' )
			);
			return array();
		}

		try {
			$result = $this->convert_result( $content, 'html', 'blocks', $args );
			$blocks = is_array( $result ) && function_exists( 'bfb_transformer_result_blocks' ) ? bfb_transformer_result_blocks( $result ) : array();
			return bfb_filter_html_to_blocks_result( $blocks, $content, $options, $args );
		} catch ( Throwable $e ) {
			do_action(
				'bfb_diagnostic',
				'blocks_engine_html_conversion_failed',
				'blocks-engine PHP transformer failed HTML conversion.',
				array(
					'adapter' => 'html',
					'error'   => $e->getMessage(),
				)
			);
			return array();
		}
	}

	/**
	 * @inheritDoc
	 *
	 * Delegates block rendering to the canonical bridge.
	 */
	public function from_blocks( array $blocks, array $options = array() ): string {
		if ( empty( $blocks ) ) {
			return '';
		}

		if ( ! function_exists( 'bfb_transformer_convert_result' ) ) {
			do_action(
				'bfb_diagnostic',
				'blocks_engine_html_transformer_unavailable',
				'blocks-engine PHP transformer is unavailable for HTML rendering.',
				array( 'adapter' => 'html' )
			);
			return '';
		}

		try {
			$result = $this->convert_result( serialize_blocks( $blocks ), 'blocks', 'html', $options );
			return is_array( $result ) && function_exists( 'bfb_transformer_result_content' ) ? bfb_transformer_result_content( $result, 'html' ) : '';
		} catch ( Throwable $e ) {
			do_action(
				'bfb_diagnostic',
				'blocks_engine_html_render_failed',
				'blocks-engine PHP transformer failed HTML rendering.',
				array(
					'adapter' => 'html',
					'error'   => $e->getMessage(),
				)
			);
			return '';
		}
	}
}
