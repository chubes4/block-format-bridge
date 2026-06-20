<?php
/**
 * php-scoper configuration for block-format-bridge.
 *
 * Vendor-prefixes the blocks-engine PHP transformer and its transitive
 * conversion dependencies into the BlockFormatBridge\Vendor namespace.
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
	// Disable php-scoper's default class_alias emission so the build does
	// NOT write `\class_alias('BlockFormatBridge\Vendor\Automattic\\...',
	// 'Automattic\\...', false)` shims back to the bare global names.
	//
	// Bundling via vendor_prefixed/ is meant to insulate BFB's bundled
	// copies of blocks-engine, league/commonmark, etc. from
	// every other plugin on the site. The default global aliases re-couple
	// the bundled copies to the global symbol surface — when two BFB
	// consumers (e.g. Intelligence + MDI) ship their own vendor_prefixed/,
	// both try to register the same transformer `class_alias` lines
	// and the second-loaded copy fatals with "Cannot declare class".
	//
	// BFB itself resolves transformer classes through bfb_transformer_class(),
	// preferring the unscoped class in dev and the scoped class in package mode.
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
					// automattic/blocks-engine-php-transformer owns format conversion.
					'vendor/automattic/blocks-engine-php-transformer',
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
