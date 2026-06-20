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
	 * @var \Automattic\BlocksEngine\PhpTransformer\FormatBridge\FormatBridge
	 */
	private $bridge;

	/**
	 * Constructor.
	 *
	 * @param \Automattic\BlocksEngine\PhpTransformer\FormatBridge\FormatBridge|null $bridge Canonical bridge.
	 */
	public function __construct( $bridge = null ) {
		$this->bridge = $bridge ?: ( function_exists( 'bfb_format_bridge' ) ? bfb_format_bridge() : null );
	}

	/**
	 * @inheritDoc
	 */
	public function slug(): string {
		return 'html';
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

		$pre_result = apply_filters( 'bfb_html_to_blocks_pre_result', null, $content, $options, $args );
		if ( is_array( $pre_result ) ) {
			return bfb_filter_html_to_blocks_result( $pre_result, $content, $options, $args );
		}

		if ( ! $this->bridge ) {
			do_action(
				'bfb_diagnostic',
				'blocks_engine_html_transformer_unavailable',
				'blocks-engine PHP transformer is unavailable for HTML conversion.',
				array( 'adapter' => 'html' )
			);
			return array();
		}

		try {
			$blocks = $this->bridge->toBlocks( $content, 'html', $args );
			return bfb_filter_html_to_blocks_result( is_array( $blocks ) ? $blocks : array(), $content, $options, $args );
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

		if ( ! $this->bridge ) {
			do_action(
				'bfb_diagnostic',
				'blocks_engine_html_transformer_unavailable',
				'blocks-engine PHP transformer is unavailable for HTML rendering.',
				array( 'adapter' => 'html' )
			);
			return '';
		}

		try {
			return $this->bridge->convert( serialize_blocks( $blocks ), 'blocks', 'html', $options );
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
