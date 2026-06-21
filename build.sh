#!/usr/bin/env bash
#
# Build the distributable form of block-format-bridge.
#
# BFB is a thin compatibility wrapper around the canonical Blocks Engine PHP
# Transformer plugin/classes. It intentionally does not vendor-prefix or bundle
# the transformer package.
#
# Run via `composer build` (also wired up in composer.json scripts).

set -euo pipefail

blocked_paths=(
	"vendor_prefixed/automattic/blocks-engine-php-transformer"
	"vendor_prefixed/chubes4/html-to-blocks-converter"
	"vendor_prefixed/chubes4/block-artifact-compiler"
	"includes/blocks-engine-php-transformer"
	"includes/html-to-blocks-converter"
	"includes/block-artifact-compiler"
)

for blocked_path in "${blocked_paths[@]}"; do
	if [[ -e "${blocked_path}" ]]; then
		echo "Refusing to build: bundled transformer artifact found at ${blocked_path}" >&2
		exit 1
	fi
done

echo "==> Build complete: BFB ships without bundled transformer dependencies."
