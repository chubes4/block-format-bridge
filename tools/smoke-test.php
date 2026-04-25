<?php
/**
 * Smoke test for block-format-bridge.
 *
 * Run via WP-CLI so the autoloader, hooks, and serialize_blocks() are all
 * available:
 *
 *   studio wp eval-file ~/Developer/block-format-bridge/tools/smoke-test.php
 *
 * Verifies:
 *   1. Markdown → blocks conversion produces serialised block markup with
 *      a heading and paragraph.
 *   2. HTML → blocks conversion produces equivalent shape.
 *   3. The bfb_default_format filter routes a wp_insert_post() through
 *      markdown conversion when the post type opts in.
 *
 * @package BlockFormatBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( "Run via wp eval-file.\n" );
}

if ( ! function_exists( 'bfb_convert' ) ) {
	exit( "block-format-bridge is not active. Activate it first.\n" );
}

echo "== block-format-bridge smoke test ==\n";

$failures = 0;

/**
 * @param string $label
 * @param bool   $ok
 * @param string $detail
 */
function assert_true( string $label, bool $ok, string $detail = '' ): void {
	global $failures;
	if ( $ok ) {
		echo '  ✓ ' . $label . "\n";
	} else {
		echo '  ✗ ' . $label . ( '' !== $detail ? "\n      " . $detail : '' ) . "\n";
		++$failures;
	}
}

// --- Test 1: Markdown → blocks ---
$md     = "# Hello\n\nWorld";
$result = bfb_convert( $md, 'markdown', 'blocks' );

assert_true(
	'Markdown → blocks returns non-empty serialised markup',
	'' !== $result,
	'got empty string'
);
assert_true(
	'Markdown → blocks contains a heading block',
	false !== strpos( $result, '<!-- wp:heading' ) || false !== strpos( $result, 'core/heading' ),
	'output: ' . substr( $result, 0, 200 )
);
assert_true(
	'Markdown → blocks contains a paragraph block',
	false !== strpos( $result, '<!-- wp:paragraph' ) || false !== strpos( $result, 'core/paragraph' ),
	'output: ' . substr( $result, 0, 200 )
);

// --- Test 2: HTML → blocks parity ---
$html         = '<h1>Hello</h1><p>World</p>';
$html_result  = bfb_convert( $html, 'html', 'blocks' );

assert_true(
	'HTML → blocks returns non-empty serialised markup',
	'' !== $html_result,
	'got empty string'
);
assert_true(
	'HTML → blocks contains a heading block',
	false !== strpos( $html_result, '<!-- wp:heading' ) || false !== strpos( $html_result, 'core/heading' ),
	'output: ' . substr( $html_result, 0, 200 )
);

// --- Test 3: bfb_default_format routes wp_insert_post through markdown ---
$test_post_type = 'bfb_smoke_test';
register_post_type(
	$test_post_type,
	array(
		'public'       => false,
		'show_in_rest' => false,
		'supports'     => array( 'title', 'editor' ),
	)
);

$filter = function ( $format, $post_type ) use ( $test_post_type ) {
	return $post_type === $test_post_type ? 'markdown' : $format;
};
add_filter( 'bfb_default_format', $filter, 10, 2 );

$post_id = wp_insert_post(
	array(
		'post_type'    => $test_post_type,
		'post_status'  => 'draft',
		'post_title'   => 'BFB Smoke',
		'post_content' => "# Test\n\nBody",
	),
	true
);

remove_filter( 'bfb_default_format', $filter, 10 );

if ( is_wp_error( $post_id ) ) {
	assert_true( 'wp_insert_post succeeded', false, $post_id->get_error_message() );
} else {
	$saved = get_post_field( 'post_content', $post_id );
	assert_true(
		'wp_insert_post stored block markup (filter-driven markdown route)',
		false !== strpos( $saved, '<!-- wp:' ),
		'stored: ' . substr( $saved, 0, 200 )
	);
	wp_delete_post( $post_id, true );
}

// --- Summary ---
echo "\n";
if ( 0 === $failures ) {
	echo "All checks passed.\n";
	exit( 0 );
}
echo $failures . " failure(s).\n";
exit( 1 );
