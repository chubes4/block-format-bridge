<?php
/**
 * Smoke coverage for active Blocks Engine transformer class discovery.
 *
 * @package BlockFormatBridge
 */

declare(strict_types=1);

namespace Automattic\BlocksEngine\PhpTransformer\FormatBridge {
	final class FormatBridge {
		public function supportedFormats(): array {
			return array( 'blocks', 'html', 'markdown' );
		}
	}
}

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/' );
	}

	function bfb_transformer_class_assert( bool $condition, string $message ): void {
		if ( ! $condition ) {
			fwrite( STDERR, "FAIL: {$message}\n" );
			exit( 1 );
		}
	}

	require_once __DIR__ . '/../includes/api.php';

	$report = bfb_transformer_capabilities();

	bfb_transformer_class_assert( true === $report['available'], 'Transformer class should be available.' );
	bfb_transformer_class_assert( 'class' === $report['integration'], 'Class bridge should be the selected integration.' );
	bfb_transformer_class_assert( in_array( 'html', $report['formats'], true ), 'Supported formats should come from FormatBridge.' );

	echo "PASS: transformer class capability discovery\n";
}
