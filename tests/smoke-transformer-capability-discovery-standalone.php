<?php
/**
 * Smoke coverage for active Blocks Engine transformer function discovery.
 *
 * @package BlockFormatBridge
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

function blocks_engine_php_transformer_version(): string {
	return 'standalone-test-version';
}

function blocks_engine_php_transformer_path(): string {
	return __DIR__;
}

function blocks_engine_php_transformer_convert_format( string $content, string $from, string $to, array $options = array() ): array {
	unset( $content, $from, $options );

	return array(
		'schema'            => 'blocks-engine/php-transformer/result/v1',
		'status'            => 'success',
		'blocks'            => array(
			array(
				'blockName'   => 'core/paragraph',
				'attrs'       => array(),
				'innerBlocks' => array(),
				'innerHTML'   => '<p>Standalone result</p>',
				'innerContent' => array( '<p>Standalone result</p>' ),
			),
		),
		'serialized_blocks' => '<!-- wp:paragraph --><p>Standalone result</p><!-- /wp:paragraph -->',
		'documents'         => array(
			array(
				'format'  => $to,
				'content' => 'standalone document content',
			),
		),
	);
}

function bfb_transformer_standalone_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

require_once __DIR__ . '/../includes/api.php';

$report = bfb_transformer_capabilities();

bfb_transformer_standalone_assert( true === $report['available'], 'Transformer helper should be available.' );
bfb_transformer_standalone_assert( 'standalone-test-version' === $report['version'], 'Transformer version should come from active helper.' );
bfb_transformer_standalone_assert( 'function' === $report['integration'], 'Function helper should be the selected integration.' );
bfb_transformer_standalone_assert( 'blocks_engine_php_transformer_convert_format' === $report['convert_format'], 'Function helper should be reported.' );

$result = bfb_transformer_convert_result( '<p>Standalone result</p>', 'html', 'blocks' );
bfb_transformer_standalone_assert( is_array( $result ), 'Transformer helper should return a canonical result array.' );
bfb_transformer_standalone_assert( 'core/paragraph' === ( bfb_transformer_result_blocks( $result )[0]['blockName'] ?? null ), 'Canonical result block extraction should work.' );
bfb_transformer_standalone_assert( '<!-- wp:paragraph --><p>Standalone result</p><!-- /wp:paragraph -->' === bfb_transformer_result_content( $result, 'blocks' ), 'Canonical serialized block extraction should work.' );
bfb_transformer_standalone_assert( 'standalone document content' === bfb_transformer_result_content( $result, 'html' ), 'Canonical document content extraction should work.' );

echo "PASS: standalone transformer capability discovery\n";
