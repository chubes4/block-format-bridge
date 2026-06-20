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
	unset( $content, $from, $to, $options );
	return array( 'status' => 'success' );
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

echo "PASS: standalone transformer capability discovery\n";
