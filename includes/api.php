<?php
/**
 * Public API.
 *
 * These functions form the public Phase 1 surface:
 *
 *   bfb_convert( $content, $from, $to )     — universal conversion
 *   bfb_normalize( $content, $format )      — declared-format validation
 *   bfb_get_adapter( $slug )                — registry lookup
 *
 * Both route through the block pivot via the adapter registry. There
 * is no parsing logic in this file; everything is delegation.
 *
 * @package BlockFormatBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'bfb_get_adapter' ) ) {
	/**
	 * Resolve an adapter by slug.
	 *
	 * @param string $slug Adapter slug (e.g. 'html', 'markdown').
	 * @return BFB_Format_Adapter|null
	 */
	function bfb_get_adapter( string $slug ): ?BFB_Format_Adapter {
		return BFB_Adapter_Registry::get( $slug );
	}
}

if ( ! function_exists( 'bfb_convert' ) ) {
	/**
	 * Convert content from one format to another.
	 *
	 * Routing always passes through the block pivot:
	 *
	 *   $blocks = $from_adapter->to_blocks( $content );
	 *   return    $to_adapter->from_blocks( $blocks );
	 *
	 * Special cases:
	 *   - $from === $to                → returns $content unchanged
	 *   - $from === 'blocks'           → skips the to_blocks() hop and
	 *                                    treats $content as serialized
	 *                                    block markup, parsing it first
	 *   - $to === 'blocks'             → returns serialized block markup
	 *
	 * @param string $content Source content.
	 * @param string $from    Source format slug.
	 * @param string $to      Target format slug.
	 * @return string Converted content. Empty string on failure.
	 */
	function bfb_convert( string $content, string $from, string $to ): string {
		if ( $from === $to ) {
			return $content;
		}

		// Resolve the block-array intermediate.
		if ( 'blocks' === $from ) {
			$blocks = parse_blocks( $content );
			if ( ! is_array( $blocks ) ) {
				$blocks = array();
			}
		} else {
			$from_adapter = bfb_get_adapter( $from );
			if ( ! $from_adapter ) {
				error_log( sprintf( '[Block Format Bridge] No adapter registered for source format "%s".', $from ) );
				return '';
			}
			$blocks = $from_adapter->to_blocks( $content );
		}

		// Render the intermediate into the target format.
		if ( 'blocks' === $to ) {
			return serialize_blocks( $blocks );
		}

		$to_adapter = bfb_get_adapter( $to );
		if ( ! $to_adapter ) {
			error_log( sprintf( '[Block Format Bridge] No adapter registered for target format "%s".', $to ) );
			return '';
		}

		return $to_adapter->from_blocks( $blocks );
	}
}

if ( ! function_exists( 'bfb_render_post' ) ) {
	/**
	 * Render a post's `post_content` in the requested format.
	 *
	 * Reads the raw `post_content` and routes it through `bfb_convert`
	 * with `'blocks'` as the source format (so dynamic blocks render
	 * via their server-side callbacks).
	 *
	 * Returns an empty string when the post is missing, the post type
	 * does not support content, or the requested format is unknown.
	 *
	 * @param int|WP_Post $post   Post ID or WP_Post.
	 * @param string      $format Target format slug (e.g. 'html', 'markdown').
	 * @return string Rendered content. Empty string on failure.
	 */
	function bfb_render_post( $post, string $format ): string {
		$post_obj = get_post( $post );
		if ( ! $post_obj ) {
			return '';
		}

		$content = (string) $post_obj->post_content;
		if ( '' === $content ) {
			return '';
		}

		// post_content is always serialised block markup (or raw HTML on
		// pre-Gutenberg installs); route through the 'blocks' source so
		// dynamic blocks resolve.
		return bfb_convert( $content, 'blocks', $format );
	}
}
