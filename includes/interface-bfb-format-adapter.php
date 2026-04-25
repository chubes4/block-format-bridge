<?php
/**
 * Format adapter contract.
 *
 * Each adapter knows how to convert between its named format and the
 * canonical block representation (block arrays as produced by
 * parse_blocks() / consumed by serialize_blocks()).
 *
 * Blocks are the pivot — every cross-format conversion in the bridge
 * routes `from_format → blocks → to_format`. New formats become
 * available by registering a new adapter; the bridge core never grows.
 *
 * @package BlockFormatBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract every format adapter implements.
 */
interface BFB_Format_Adapter {

	/**
	 * Unique slug for this format.
	 *
	 * Used as the `$from` / `$to` argument to `bfb_convert()` and as the
	 * registry key. Lowercase, no spaces.
	 *
	 * @return string
	 */
	public function slug(): string;

	/**
	 * Convert content from this format into a block array.
	 *
	 * The returned array must be parse_blocks()-compatible — i.e. an
	 * array of arrays each with `blockName`, `attrs`, `innerBlocks`,
	 * `innerHTML`, and `innerContent` keys.
	 *
	 * @param string $content Source content in this adapter's format.
	 * @return array Block array.
	 */
	public function to_blocks( string $content ): array;

	/**
	 * Convert a block array back into this adapter's format.
	 *
	 * @param array $blocks Block array (parse_blocks() shape).
	 * @return string Content in this adapter's format.
	 */
	public function from_blocks( array $blocks ): string;

	/**
	 * Best-effort detection of whether $content is in this format.
	 *
	 * Reserved for future use. v0.1.0 does not consult detect() from
	 * any production path — auto-detection is opt-in via filters and
	 * per-call hints. Implementations may return false until the
	 * detection rules are designed.
	 *
	 * @param string $content Content to test.
	 * @return bool
	 */
	public function detect( string $content ): bool;
}
