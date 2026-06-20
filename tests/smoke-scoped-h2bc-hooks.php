<?php
/**
 * Smoke coverage for scoped transformer packaging.
 *
 * @package BlockFormatBridge
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/../' );

function bfb_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$autoload_file = __DIR__ . '/../vendor_prefixed/autoload.php';
bfb_smoke_assert( is_readable( $autoload_file ), 'Scoped transformer autoloader should be readable.' );

require_once $autoload_file;

$scoped_bridge = 'BlockFormatBridge\\Vendor\\Automattic\\BlocksEngine\\PhpTransformer\\FormatBridge\\FormatBridge';
$global_bridge = 'Automattic\\BlocksEngine\\PhpTransformer\\FormatBridge\\FormatBridge';

bfb_smoke_assert( class_exists( $scoped_bridge ), 'Scoped FormatBridge should autoload.' );
bfb_smoke_assert( ! class_exists( $global_bridge, false ), 'Scoped package should not expose the global FormatBridge class.' );
bfb_smoke_assert( ! function_exists( 'BlockFormatBridge\\Vendor\\html_to_blocks_raw_handler' ), 'Scoped package should not expose the old h2bc raw handler.' );

echo "PASS: scoped transformer package stays isolated\n";
