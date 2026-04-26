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
 *   Phase 1 (write side):
 *     1. Markdown → blocks conversion produces serialised block markup
 *        with a heading and paragraph.
 *     2. HTML → blocks conversion produces equivalent shape.
 *     3. The bfb_default_format filter routes a wp_insert_post()
 *        through markdown conversion when the post type opts in.
 *   Phase 2 (read side):
 *     4. Markdown adapter from_blocks() round-trips a heading +
 *        formatted paragraph back to clean GFM.
 *     5. HTML adapter from_blocks() renders dynamic blocks via
 *        do_blocks().
 *     6. bfb_render_post() returns markdown / html for a stored post.
 *     7. REST GET /wp/v2/posts/{id}?content_format=markdown adds
 *        content.formatted without disturbing content.rendered.
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

// --- Test 3b: bfb_skip_insert_conversion vetoes the conversion ---
// Storage layers that own the canonical post_content shape (e.g. MDI in
// any mode wants markdown to stay as markdown) hook this filter to
// short-circuit the insert-time conversion. The filter receives the
// resolved format so consumers can scope the veto by source format.
add_filter( 'bfb_default_format', $filter, 10, 2 );

$skip_seen_format = null;
$skip_filter      = function ( $skip, $data, $postarr, $format ) use ( $test_post_type, &$skip_seen_format ) {
	if ( ( $data['post_type'] ?? '' ) === $test_post_type ) {
		$skip_seen_format = $format;
		return true;
	}
	return $skip;
};
add_filter( 'bfb_skip_insert_conversion', $skip_filter, 10, 4 );

$skip_post_id = wp_insert_post(
	array(
		'post_type'    => $test_post_type,
		'post_status'  => 'draft',
		'post_title'   => 'BFB Skip',
		'post_content' => "# Test\n\nBody",
	),
	true
);

remove_filter( 'bfb_skip_insert_conversion', $skip_filter, 10 );
remove_filter( 'bfb_default_format', $filter, 10 );

if ( is_wp_error( $skip_post_id ) ) {
	assert_true( 'wp_insert_post (skip path) succeeded', false, $skip_post_id->get_error_message() );
} else {
	$skipped_saved = get_post_field( 'post_content', $skip_post_id );
	assert_true(
		'bfb_skip_insert_conversion=true leaves post_content untouched',
		false === strpos( $skipped_saved, '<!-- wp:' )
			&& false !== strpos( $skipped_saved, '# Test' ),
		'stored: ' . substr( $skipped_saved, 0, 200 )
	);
	assert_true(
		'bfb_skip_insert_conversion receives the resolved format slug',
		'markdown' === $skip_seen_format,
		'got: ' . var_export( $skip_seen_format, true )
	);
	wp_delete_post( $skip_post_id, true );
}

// --- Test 4: Markdown adapter from_blocks() round-trips ---
$md_adapter = bfb_get_adapter( 'markdown' );
assert_true( 'markdown adapter resolves', $md_adapter instanceof BFB_Format_Adapter );

$source_blocks = parse_blocks(
	'<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Title</h1><!-- /wp:heading -->'
	. '<!-- wp:paragraph --><p>Body with <strong>bold</strong> and <em>italic</em>.</p><!-- /wp:paragraph -->'
);

$round_md = $md_adapter ? $md_adapter->from_blocks( $source_blocks ) : '';
assert_true(
	'markdown from_blocks() emits ATX heading',
	false !== strpos( $round_md, '# Title' ),
	'got: ' . substr( $round_md, 0, 200 )
);
assert_true(
	'markdown from_blocks() preserves bold (**) and italic (*)',
	false !== strpos( $round_md, '**bold**' ) && false !== strpos( $round_md, '*italic*' ),
	'got: ' . substr( $round_md, 0, 200 )
);

// --- Test 5: HTML adapter from_blocks() renders blocks ---
$html_adapter = bfb_get_adapter( 'html' );
$rendered     = $html_adapter ? $html_adapter->from_blocks( $source_blocks ) : '';
assert_true(
	'html from_blocks() renders heading',
	false !== strpos( $rendered, '<h1' ) && false !== strpos( $rendered, 'Title' ),
	'got: ' . substr( $rendered, 0, 200 )
);
assert_true(
	'html from_blocks() does NOT contain block-comment markup (it is rendered HTML)',
	false === strpos( $rendered, '<!-- wp:' ),
	'got: ' . substr( $rendered, 0, 200 )
);

// --- Test 6: bfb_render_post() ---
$render_post_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_title'   => 'Render Smoke',
		'post_content' => '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Render</h2><!-- /wp:heading -->'
			. '<!-- wp:paragraph --><p>Para.</p><!-- /wp:paragraph -->',
	),
	true
);
if ( is_wp_error( $render_post_id ) ) {
	assert_true( 'render-test post inserted', false, $render_post_id->get_error_message() );
} else {
	$rendered_html = bfb_render_post( $render_post_id, 'html' );
	$rendered_md   = bfb_render_post( $render_post_id, 'markdown' );

	assert_true(
		'bfb_render_post(html) returns rendered HTML (no block-comments)',
		false !== strpos( $rendered_html, '<h2' ) && false === strpos( $rendered_html, '<!-- wp:' ),
		'got: ' . substr( $rendered_html, 0, 200 )
	);
	assert_true(
		'bfb_render_post(markdown) returns clean GFM',
		false !== strpos( $rendered_md, '## Render' ) && false !== strpos( $rendered_md, 'Para.' ),
		'got: ' . substr( $rendered_md, 0, 200 )
	);
	wp_delete_post( $render_post_id, true );
}

// --- Test 7a: TableConverter round-trips GFM tables ---
$table_md       = "| col1 | col2 |\n| --- | --- |\n| a | b |\n";
$table_round    = bfb_convert( bfb_convert( $table_md, 'markdown', 'blocks' ), 'blocks', 'markdown' );
assert_true(
	'Tables round-trip through markdown→blocks→markdown',
	false !== strpos( $table_round, '| col1 | col2 |' ),
	'got: ' . $table_round
);

// --- Test 7b: bfb_markdown_input filter pre-processes raw markdown ---
$linkify = static function ( string $md ): string {
	return preg_replace( '#(?<![:/])(?<![a-zA-Z0-9])(example\.com)#', 'https://$1', $md );
};
add_filter( 'bfb_markdown_input', $linkify );
$linkified_blocks = bfb_convert( 'See example.com for details.', 'markdown', 'blocks' );
remove_filter( 'bfb_markdown_input', $linkify );
assert_true(
	'bfb_markdown_input filter linkified bare URL',
	false !== strpos( $linkified_blocks, 'href="https://example.com"' ),
	'got: ' . substr( $linkified_blocks, 0, 200 )
);

// --- Test 8: REST ?content_format=markdown ---
$rest_post_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'REST Smoke',
		'post_content' => '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">REST</h1><!-- /wp:heading -->'
			. '<!-- wp:paragraph --><p>Body.</p><!-- /wp:paragraph -->',
	),
	true
);
if ( is_wp_error( $rest_post_id ) ) {
	assert_true( 'rest-test post inserted', false, $rest_post_id->get_error_message() );
} else {
	$req = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $rest_post_id );
	$req->set_param( 'content_format', 'markdown' );
	$resp = rest_do_request( $req );
	$data = $resp->get_data();

	assert_true(
		'REST adds content.formatted when content_format=markdown',
		isset( $data['content']['formatted'] ) && '' !== $data['content']['formatted'],
		isset( $data['content']['formatted'] ) ? 'got: ' . substr( $data['content']['formatted'], 0, 200 ) : 'missing'
	);
	assert_true(
		'REST sets content.format = markdown',
		( $data['content']['format'] ?? '' ) === 'markdown'
	);
	assert_true(
		'REST leaves content.rendered intact',
		isset( $data['content']['rendered'] ) && false !== strpos( $data['content']['rendered'], '<h1' )
	);

	$req2  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $rest_post_id );
	$resp2 = rest_do_request( $req2 );
	$data2 = $resp2->get_data();

	assert_true(
		'REST does NOT add content.formatted when no query param given',
		! isset( $data2['content']['formatted'] )
	);

	wp_delete_post( $rest_post_id, true );
}

// --- Summary ---
echo "\n";
if ( 0 === $failures ) {
	echo "All checks passed.\n";
	exit( 0 );
}
echo $failures . " failure(s).\n";
exit( 1 );
