#!/usr/bin/env bash
#
# Build the distributable form of block-format-bridge:
#
#   1. Run php-scoper to vendor-prefix league/commonmark and its transitive
#      dependency graph into vendor_prefixed/ under the BlockFormatBridge\Vendor
#      namespace.
#   2. Generate a self-contained autoloader at vendor_prefixed/autoload.php
#      built from the per-package composer.json autoload entries.
#
# After this completes, the main plugin file loads vendor_prefixed/autoload.php
# preferentially over vendor/autoload.php at runtime.
#
# Run via `composer build` (also wired up in composer.json scripts).

set -euo pipefail

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
cd "$SCRIPT_DIR"

if [[ ! -x vendor/bin/php-scoper ]]; then
	echo "vendor/bin/php-scoper missing — run 'composer install' first." >&2
	exit 1
fi

echo "==> Running php-scoper..."
vendor/bin/php-scoper add-prefix --force --quiet

echo "==> Generating vendor_prefixed/autoload.php..."
php tools/build-autoloader.php

echo "==> Build complete: vendor_prefixed/autoload.php"
