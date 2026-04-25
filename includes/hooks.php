<?php
/**
 * WordPress hook integration.
 *
 * Phase 1 plumbing:
 *
 *   - `_bfb_format` per-call hint on $postarr is honoured by
 *     wp_insert_post_data.
 *   - `bfb_default_format` filter declares which format a CPT writes
 *     in by default. Defaults to 'html'.
 *   - Runs at priority 5 — BEFORE the standalone html-to-blocks-converter
 *     plugin (which fires at priority 10) — so every supported format is
 *     normalised to block markup first, then html-to-blocks-converter sees
 *     nothing to do (its `<!-- wp:` short-circuit triggers).
 *
 * @package BlockFormatBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the format hint for a given post insert.
 *
 * Resolution order:
 *   1. `_bfb_format` key on $postarr (per-call override)
 *   2. `bfb_default_format` filter result for the post type
 *   3. 'html' (default source format)
 *
 * @param array $data    Sanitized post data destined for wp_posts.
 * @param array $postarr Original post-insert array.
 * @return string Format slug.
 */
function bfb_resolve_format_for_insert( array $data, array $postarr ): string {
	if ( ! empty( $postarr['_bfb_format'] ) && is_string( $postarr['_bfb_format'] ) ) {
		return $postarr['_bfb_format'];
	}

	$post_type = isset( $data['post_type'] ) ? (string) $data['post_type'] : 'post';
	$content   = isset( $data['post_content'] ) ? (string) $data['post_content'] : '';

	/**
	 * Filters the default format a post type writes in.
	 *
	 * Return one of the registered adapter slugs ('html', 'markdown',
	 * or any third-party registered slug). Return 'html' (or skip the
	 * filter) for the default HTML source format.
	 *
	 * @since 0.1.0
	 *
	 * @param string $format    Default format slug. 'html' to opt out.
	 * @param string $post_type Post type being inserted.
	 * @param string $content   Raw post content (already wp_unslash'd? NO — still slashed).
	 */
	$format = apply_filters( 'bfb_default_format', 'html', $post_type, $content );

	return is_string( $format ) && '' !== $format ? $format : 'html';
}

/**
 * Check whether automatic HTML → blocks conversion should run for a post type.
 *
 * Mirrors html-to-blocks-converter's standalone plugin support policy so BFB's
 * bundled package mode has the same default write behavior without loading
 * h2bc's hook file.
 *
 * @param string $post_type Post type slug.
 * @return bool Whether the post type is supported for automatic HTML writes.
 */
function bfb_html_insert_supported_post_type( string $post_type ): bool {
	$default_types   = array_keys( get_post_types( array( 'show_in_rest' => true, 'public' => true ) ) );
	$supported_types = apply_filters( 'html_to_blocks_supported_post_types', $default_types );

	return in_array( $post_type, (array) $supported_types, true );
}

/**
 * Convert post_content from the resolved source format to serialised block
 * markup before WordPress writes it.
 *
 * Skips when:
 *   - post_content is empty
 *   - the resolved format is 'html' and the post type is not supported by
 *     html-to-blocks-converter's automatic write policy
 *   - post_content is already block markup (`<!-- wp:`)
 *   - no adapter is registered for the resolved format
 *
 * @param array $data    Sanitized post data.
 * @param array $postarr Original post-insert array.
 * @return array Modified post data.
 */
function bfb_convert_on_insert( $data, $postarr ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}

	if ( empty( $data['post_content'] ) ) {
		return $data;
	}

	$format    = bfb_resolve_format_for_insert( $data, $postarr );
	$post_type = isset( $data['post_type'] ) ? (string) $data['post_type'] : 'post';

	if ( 'html' === $format && ! bfb_html_insert_supported_post_type( $post_type ) ) {
		return $data;
	}

	$content = wp_unslash( $data['post_content'] );

	// Already block markup — leave it alone.
	if ( false !== strpos( $content, '<!-- wp:' ) ) {
		return $data;
	}

	$adapter = bfb_get_adapter( $format );
	if ( ! $adapter ) {
		error_log( sprintf( '[Block Format Bridge] No adapter for format "%s" on insert; skipping.', $format ) );
		return $data;
	}

	$serialized = bfb_convert( $content, $format, 'blocks' );
	if ( '' === $serialized ) {
		return $data;
	}

	$data['post_content'] = wp_slash( $serialized );
	return $data;
}
add_filter( 'wp_insert_post_data', 'bfb_convert_on_insert', 5, 2 );
