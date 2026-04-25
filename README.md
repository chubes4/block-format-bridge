# Block Format Bridge

A WordPress plugin that orchestrates bidirectional content format conversion (HTML, Blocks, Markdown) through a unified
adapter API.

The bridge owns no parsing logic of its own. It composes existing tools — [`chubes4/html-to-blocks-converter`](https://github.com/chubes4/html-to-blocks-converter),
WordPress core's `serialize_blocks()` / `do_blocks()`, and [`league/commonmark`](https://github.com/thephpleague/commonmark) — behind one
contract. New formats become available by registering a new adapter; the bridge core never grows.

> **Status:** Phase 1 (write side). Markdown → Blocks and HTML → Blocks both work end-to-end. The read side
> (Blocks → Markdown) is Phase 2.

## What it does

| Conversion direction | Underlying tool                       |
|----------------------|---------------------------------------|
| HTML → Blocks        | `chubes4/html-to-blocks-converter`    |
| Blocks → HTML        | `serialize_blocks()` (core)           |
| Markdown → HTML      | `league/commonmark` (vendor-prefixed) |
| Markdown → Blocks    | composition: Markdown → HTML → Blocks |
| Blocks → Markdown    | _Phase 2 — not yet implemented_       |
| HTML → Markdown      | _Phase 2 — not yet implemented_       |

## Architecture

Two adapters ship in v0.1.0:

- **`BFB_HTML_Adapter`** — delegates `to_blocks()` to `html_to_blocks_raw_handler()` from `html-to-blocks-converter`,
  and `from_blocks()` to `serialize_blocks()`.
- **`BFB_Markdown_Adapter`** — runs CommonMark + GFM via `league/commonmark`, then routes the resulting HTML through
  the HTML adapter to land in block form. `from_blocks()` is a no-op until Phase 2.

Both implement the `BFB_Format_Adapter` interface:

```php
interface BFB_Format_Adapter {
    public function slug(): string;
    public function to_blocks( string $content ): array;
    public function from_blocks( array $blocks ): string;
    public function detect( string $content ): bool; // reserved for future use
}
```

Every cross-format conversion routes through the block array pivot:

```
$blocks = $from_adapter->to_blocks( $content );
return    $to_adapter->from_blocks( $blocks );
```

## Install

The plugin is distributed via [wp-packages.org](https://wp-packages.org). Add it to a Composer-managed WordPress site:

```bash
composer config repositories.wp-packages composer https://wp-packages.org
composer require chubes4/block-format-bridge
```

For full HTML → Blocks support you also need [`chubes4/html-to-blocks-converter`](https://github.com/chubes4/html-to-blocks-converter)
installed and active alongside the bridge. The bridge fails soft (returns a `core/freeform` block) when it isn't
present, but you'll get much better block fidelity with it active.

### Build from source

```bash
git clone https://github.com/chubes4/block-format-bridge.git
cd block-format-bridge
composer install
composer build  # runs php-scoper to vendor-prefix league/commonmark into vendor_prefixed/
```

## Usage

### `bfb_convert( $content, $from, $to ): string`

Universal conversion. Routes through the block pivot via the adapter registry.

```php
// Markdown → blocks (serialised block markup)
$blocks = bfb_convert( "# Hello\n\nWorld", 'markdown', 'blocks' );

// HTML → blocks
$blocks = bfb_convert( '<h1>Hello</h1><p>World</p>', 'html', 'blocks' );

// Blocks → HTML
$html = bfb_convert( $serialised_blocks, 'blocks', 'html' );
```

### `bfb_get_adapter( $slug ): ?BFB_Format_Adapter`

Look up a registered adapter directly. Useful when you want to skip the universal router and do block-array work
without re-serialising.

### `bfb_default_format` filter

Declare which format a post type writes in by default. Hooks into `wp_insert_post_data` so any code path that calls
`wp_insert_post()` — REST, WP-CLI, code abilities, plugin internals — gets the same conversion behaviour.

```php
add_filter( 'bfb_default_format', function ( $format, $post_type, $content ) {
    if ( $post_type === 'wiki' ) {
        return 'markdown';
    }
    return $format;
}, 10, 3 );
```

### `_bfb_format` per-call hint

Bypass the filter for a single insert by setting the `_bfb_format` key on the `$postarr` argument:

```php
wp_insert_post( array(
    'post_type'    => 'post',
    'post_content' => "# Markdown content here",
    '_bfb_format'  => 'markdown',
) );
```

### Adapter registration

Third-party adapters can register at any point before they are looked up. Either eager-register on
`bfb_adapters_registered`:

```php
add_action( 'bfb_adapters_registered', function () {
    BFB_Adapter_Registry::register( new My_AsciiDoc_Adapter() );
} );
```

Or lazy-register via the lookup filter:

```php
add_filter( 'bfb_register_format_adapter', function ( $adapter, $slug ) {
    if ( $slug === 'asciidoc' && ! $adapter ) {
        return new My_AsciiDoc_Adapter();
    }
    return $adapter;
}, 10, 2 );
```

## Design

The full architectural rationale — including why DM stays substrate-agnostic, why the bridge is a separate plugin
instead of a feature of `html-to-blocks-converter`, and the prior-art evaluation for each conversion direction — lives
in the design doc on the author's personal wiki under
`projects/block-format-bridge-bidirectional-content-format-plugin-design`.

## License

GPL-2.0-or-later.
