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

echo "==> Build complete: BFB ships without bundled transformer dependencies."
