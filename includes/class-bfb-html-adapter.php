<?php
/**
 * HTML format adapter.
 *
 * `to_blocks()` delegates to the canonical blocks-engine PHP transformer while
 * preserving BFB's public filter surface.
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
		 * Filters the argument array passed to html-to-blocks-converter.
		 *
		 * BFB reserves the `HTML` key for source content. Per-call conversion
		 * options, such as `context` and `mode`, are forwarded alongside it for
		 * h2bc to consume when supported.
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

		if ( $this->bridge ) {
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
			}
		}

		do_action(
			'bfb_diagnostic',
			'blocks_engine_html_transformer_unavailable',
			'blocks-engine PHP transformer is unavailable; falling back to a freeform block.',
			array( 'adapter' => 'html' )
		);
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
	public function from_blocks( array $blocks, array $options = array() ): string {
		if ( empty( $blocks ) ) {
			return '';
		}

		if ( $this->bridge ) {
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
			}
		}

		$html = '';
		foreach ( $blocks as $block ) {
			$html .= render_block( $block );
		}

		return $html;
	}
}
