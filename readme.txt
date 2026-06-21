=== Block Format Bridge ===
Contributors: chubes4
Tags: blocks, markdown, html, conversion, gutenberg, rest-api, wp-cli
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.5.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Server-side content format conversion between HTML, WordPress blocks, and Markdown through one deterministic bridge API.

== Description ==

Block Format Bridge is a WordPress plugin and Composer package for converting content between HTML, WordPress block markup, and Markdown.

The bridge owns format routing and orchestration. It delegates conversion to the canonical Blocks Engine PHP Transformer `FormatBridge` class instead of adding a separate parser or fallback converter.

The public PHP APIs are:

* `bfb_convert( $content, $from, $to )` for universal string conversion.
* `bfb_to_blocks( $content, $from )` for parsed block arrays.
* `bfb_normalize( $content, $format )` for validating and normalizing declared-format input.
* `bfb_render_post( $post, $format )` for reading stored post content in another format.

BFB also includes a thin WP-CLI wrapper and a REST read surface using `?content_format=<slug>`.

This plugin does not use AI, telemetry, remote services, or external API calls. Conversion runs locally inside WordPress.

== Installation ==

= WordPress plugin =

1. Upload the `block-format-bridge` directory to `/wp-content/plugins/`.
2. Activate Block Format Bridge from the Plugins screen.
3. Use the PHP APIs, WP-CLI command, or REST `content_format` query parameter from your integration code.

= Composer =

BFB is not currently published on Packagist, WordPress.org, or wp-packages.org. Composer consumers can install tagged GitHub releases through a VCS repository:

`
composer config repositories.bfb vcs https://github.com/chubes4/block-format-bridge
composer require chubes4/block-format-bridge:^0.5
`

Composer autoloads `library.php`, which registers the same bridge APIs and hooks as the standalone plugin.

= From source =

`
git clone https://github.com/chubes4/block-format-bridge.git
cd block-format-bridge
composer install
composer build
`

`composer build` verifies the thin wrapper build has no bundled transformer artifact.

== Frequently Asked Questions ==

= Is this plugin already available on WordPress.org? =

No. This `readme.txt` prepares the repository for a future WordPress.org plugin-directory submission, but the plugin has not been submitted from this repository yet.

= Is this package already available on Packagist or wp-packages.org? =

No. GitHub VCS installation is the current Composer path. wp-packages.org mirrors plugins from WordPress.org under `wp-plugin/<slug>`, so BFB will only appear there after a WordPress.org plugin-directory listing exists.

= Does the plugin require Blocks Engine PHP Transformer? =

Yes. BFB is a thin public API wrapper and requires the active Blocks Engine PHP Transformer `FormatBridge` class at runtime.

= Why does the plugin not include a vendor_prefixed directory? =

BFB does not bundle transformer dependencies. Conversion behavior belongs to the canonical Blocks Engine PHP Transformer runtime.

= Does BFB infer full block-theme or Site Editor structure from arbitrary HTML? =

No. BFB is a deterministic content-format conversion substrate. Template hierarchy, template parts, patterns, persistent navigation entities, Styles, and `theme.json` decisions belong in compiler or site-generation layers above BFB.

= Does BFB call external services? =

No. BFB performs local conversion only. It does not send content to remote APIs, telemetry systems, or AI services.

== Screenshots ==

No screenshots are included. BFB is an integration and conversion substrate rather than an end-user settings UI.

== Changelog ==

= 0.5.0 =

* Adds conversion capability surfaces and machine-readable conversion operations.
* Expands block-theme compiler integration documentation.
* Keeps conversion local and deterministic across PHP APIs, WP-CLI, and REST surfaces.

= 0.4.1 =

* Adds conversion matrix and normalization coverage for the public API.

= 0.4.0 =

* Introduces the normalized content-format substrate with bundled scoped dependencies.
