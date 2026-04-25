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
	'finders'    => [
		Finder::create()
			->files()
			->name( [ '*.php', 'composer.json', 'LICENSE*' ] )
			->in(
				[
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
