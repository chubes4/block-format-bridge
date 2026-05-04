<?php
/**
 * php-scoper configuration for block-format-bridge.
 *
 * Vendor-prefixes league/commonmark and its full transitive dependency
 * graph into the BlockFormatBridge\Vendor namespace so multiple
 * plugins can ship their own copy of CommonMark on the same WordPress
 * install without namespace collisions.
 *
 * @package BlockFormatBridge
 */

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

return [
	'prefix'     => 'BlockFormatBridge\\Vendor',
	'output-dir' => 'vendor_prefixed',
	'exclude-classes' => [
		'WP_Block_Type',
		'WP_Block_Type_Registry',
		'WP_HTML_Processor',
		'WP_HTML_Tag_Processor',
		'WP_Post',
		'WP_REST_Request',
		'WP_REST_Response',
	],
	'exclude-functions' => [
		'add_action',
		'add_filter',
		'do_action',
		'has_action',
		'has_filter',
	],
	'patchers' => [
		static function ( string $file_path, string $prefix, string $contents ): string {
			if ( ! str_contains( $file_path, 'html-to-blocks-converter' ) ) {
				return $contents;
			}

			foreach ( array( 'add_action', 'add_filter', 'do_action', 'has_action', 'has_filter' ) as $function_name ) {
				$contents = str_replace(
					" && !\\function_exists('{$prefix}\\{$function_name}')",
					'',
					$contents
				);
			}

			$contents = str_replace(
				"'html_to_blocks_raw_handler'",
				"'{$prefix}\\html_to_blocks_raw_handler'",
				$contents
			);

			return $contents;
		},
	],
	// Disable php-scoper's default class_alias emission so the build does
	// NOT write `\class_alias('BlockFormatBridge\Vendor\HTML_To_Blocks_*',
	// 'HTML_To_Blocks_*', false)` shims back to the bare global names.
	//
	// Bundling via vendor_prefixed/ is meant to insulate BFB's bundled
	// copies of html-to-blocks-converter, league/commonmark, etc. from
	// every other plugin on the site. The default global aliases re-couple
	// the bundled copies to the global symbol surface — when two BFB
	// consumers (e.g. Intelligence + MDI) ship their own vendor_prefixed/,
	// both try to register the same `class_alias HTML_To_Blocks_*` lines
	// and the second-loaded copy fatals with "Cannot declare class".
	//
	// BFB itself never references the bare global names; library.php and
	// includes/class-bfb-html-adapter.php both call the scoped FQNs
	// (`\BlockFormatBridge\Vendor\HTML_To_Blocks_Versions`,
	// `\BlockFormatBridge\Vendor\html_to_blocks_raw_handler`) and only
	// fall through to the bare names when the standalone h2bc plugin is
	// the source of truth (i.e. BFB has no vendor_prefixed/ build).
	//
	// We only flip `expose-global-classes` — leaving `expose-global-functions`
	// and `expose-global-constants` at their `true` defaults so scoper still
	// knows global functions/constants like `defined()`, `function_exists()`,
	// `'ABSPATH'`, etc. are global references, not symbols belonging to the
	// prefix. Flipping all three at once mis-prefixes the `defined('ABSPATH')`
	// guard in vendored library files (the string scalar gets rewritten to
	// `'BlockFormatBridge\Vendor\ABSPATH'`, an unreachable constant), which
	// makes those library.php entrypoints early-return and silently disables
	// the bundled package.
	//
	// See: https://github.com/chubes4/block-format-bridge/issues/10
	'expose-global-classes' => false,
	'finders'    => [
		Finder::create()
			->files()
			->name( [ '*.php', 'composer.json', 'LICENSE*' ] )
			->in(
				[
					// chubes4/html-to-blocks-converter (HTML → Blocks).
					'vendor/chubes4/html-to-blocks-converter',
					// league/commonmark + transitive deps (Markdown → HTML).
					'vendor/league/commonmark',
					'vendor/league/config',
					'vendor/dflydev/dot-access-data',
					'vendor/nette/schema',
					'vendor/nette/utils',
					'vendor/psr/event-dispatcher',
					'vendor/symfony/deprecation-contracts',
					'vendor/symfony/polyfill-php80',
					// league/html-to-markdown (HTML → Markdown). No further composer deps.
					'vendor/league/html-to-markdown',
				]
			),
	],
];
