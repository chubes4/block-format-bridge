# Block Format Bridge

A WordPress plugin **and Composer package** that orchestrates bidirectional content format conversion (HTML, Blocks,
Markdown) through a unified adapter API.

The bridge owns no parsing logic of its own. It composes existing libraries — [`chubes4/html-to-blocks-converter`](https://github.com/chubes4/html-to-blocks-converter),
WordPress core's `serialize_blocks()` / `parse_blocks()` / `render_block()`, [`league/commonmark`](https://github.com/thephpleague/commonmark),
and [`league/html-to-markdown`](https://github.com/thephpleague/html-to-markdown) — behind one contract. New formats
become available by registering a new adapter; the bridge core never grows.

> **Status:** Both write (HTML/Markdown → Blocks) and read (Blocks → HTML/Markdown) directions work end-to-end, plus
> a `?content_format=` REST query param. The documented `bfb_convert()` directions below are covered by
> Playground-backed PHPUnit tests via `homeboy test`.

## What it does

| Conversion direction | Underlying tool                        |
|----------------------|----------------------------------------|
| HTML → Blocks        | `chubes4/html-to-blocks-converter`     |
| Blocks → HTML        | `parse_blocks()` + `render_block()` (WordPress core) |
| Markdown → HTML      | `league/commonmark` (vendor-prefixed)  |
| Markdown → Blocks    | composition: Markdown → HTML → Blocks  |
| Blocks → Markdown    | `parse_blocks()` + `render_block()` + `league/html-to-markdown` (vendor-prefixed) |
| HTML → Markdown      | composition: HTML → Blocks → Markdown  |

## Architecture

Every adapter implements the `BFB_Format_Adapter` contract:

```php
interface BFB_Format_Adapter {
    public function slug(): string;
    public function to_blocks( string $content ): array;
    public function from_blocks( array $blocks ): string;
    public function detect( string $content ): bool; // reserved for future use
}
```

Two built-in adapters ship today:

- **`BFB_HTML_Adapter`** — `to_blocks()` delegates to `html_to_blocks_raw_handler()` from `html-to-blocks-converter`;
  `from_blocks()` returns rendered HTML via `render_block()` (so dynamic blocks resolve to their server-side output).
- **`BFB_Markdown_Adapter`** — `to_blocks()` runs CommonMark + GFM and routes the resulting HTML through the HTML
  adapter. `from_blocks()` renders blocks via `render_block()` and pipes the HTML through league/html-to-markdown.

Every cross-format conversion routes through the block-array pivot:

```
$blocks = $from_adapter->to_blocks( $content );
return    $to_adapter->from_blocks( $blocks );
```

### BFB and h2bc responsibility split

BFB owns format routing and orchestration. It decides which adapter handles a source format, normalises non-block
formats through the block-array pivot, and exposes one public API for callers that do not want to know which lower-level
library performs a specific conversion. It does **not** own per-block raw transforms.

HTML → core block transforms belong to [`chubes4/html-to-blocks-converter`](https://github.com/chubes4/html-to-blocks-converter)
(h2bc). BFB inherits h2bc support through `BFB_HTML_Adapter::to_blocks()`, so new h2bc transforms become available to
BFB after the bundled dependency is updated and rebuilt.

The explicit API path is:

```php
bfb_convert( $html, 'html', 'blocks' )
    -> BFB_HTML_Adapter::to_blocks()
    -> html_to_blocks_raw_handler();
```

The insert/update hook path is split by source format:

- **BFB priority 5:** `wp_insert_post_data` handles non-HTML source formats, such as Markdown, before WordPress stores
  the post. The adapter path normalises those formats to block markup.
- **h2bc priority 10:** `wp_insert_post_data` handles HTML source content and converts it to core block markup.

Both paths are server-side and deterministic. There is no AI conversion pass in BFB or h2bc.

FSE and template blocks are a higher-level concern. Raw HTML can describe markup, but it often cannot encode intent such
as template areas, patterns, block locking, global style relationships, or theme-specific structure. When that intent is
required, use a compiler or generation layer above BFB/h2bc, then pass the resulting block markup through the normal
storage/rendering path.

## Install

Install it as a standalone plugin, or bundle it as a Composer package.

The package is not yet published to a Composer mirror, so today the install path is a VCS repository pointing at
GitHub:

```bash
composer config repositories.bfb vcs https://github.com/chubes4/block-format-bridge
composer require chubes4/block-format-bridge:dev-main
```

Composer autoloads `library.php`, which registers the bridge through an Action-Scheduler-style version registry.
Package mode loads the full bridge service: adapters, `bfb_convert()`, `bfb_render_post()`, the write-side
`wp_insert_post_data` integration, and the REST `?content_format=` integration. If multiple plugins bundle BFB while
the standalone plugin is also active, the registry initializes the highest loaded version once.

HTML → Blocks support is bundled via [`chubes4/html-to-blocks-converter`](https://github.com/chubes4/html-to-blocks-converter)
as a Composer package. You do **not** need the standalone html-to-blocks-converter plugin active for BFB to convert
HTML/Markdown into block markup.

### Build from source

```bash
git clone https://github.com/chubes4/block-format-bridge.git
cd block-format-bridge
composer install
composer build  # runs php-scoper to vendor-prefix h2bc + markdown dependencies
```

## Usage

### `bfb_convert( $content, $from, $to ): string`

Universal conversion. Routes through the block-array pivot via the adapter registry.

```php
// Markdown → blocks (serialised block markup)
$blocks = bfb_convert( "# Hello\n\nWorld", 'markdown', 'blocks' );

// HTML → blocks
$blocks = bfb_convert( '<h1>Hello</h1><p>World</p>', 'html', 'blocks' );

// Blocks → HTML (rendered through render_block())
$html = bfb_convert( $serialised_blocks, 'blocks', 'html' );

// Blocks → markdown
$md = bfb_convert( $serialised_blocks, 'blocks', 'markdown' );

// HTML → markdown (composes via blocks)
$md = bfb_convert( '<h1>X</h1>', 'html', 'markdown' );

// Markdown → HTML (composes via blocks)
$html = bfb_convert( '# X', 'markdown', 'html' );
```

### `bfb_render_post( $post, $format ): string`

Read a post's `post_content` in the requested format. Routes through `bfb_convert()` with `'blocks'` as the source.

```php
$html = bfb_render_post( $post_id, 'html' );      // rendered block HTML
$md   = bfb_render_post( $post_id, 'markdown' );  // GFM
```

### REST: `?content_format=<slug>`

Every REST-enabled post type accepts a `content_format` query parameter. When present, the response gains a sibling
`content.formatted` field rendered via `bfb_render_post()`. The existing `content.raw` and `content.rendered` fields
are left untouched.

```bash
curl 'https://example.com/wp-json/wp/v2/posts/123?content_format=markdown'
```

```json
{
  "content": {
    "raw": "<!-- wp:heading ...",
    "rendered": "<h1 class=\"wp-block-heading\">...</h1>",
    "format": "markdown",
    "formatted": "# Hello\n\nBody."
  }
}
```

Full HTTP content negotiation (`Accept: text/markdown`, `.md` URL suffix, q-values, 406 Not Acceptable) is intentionally
out of scope here — that's the job of [`roots/post-content-to-markdown`](https://github.com/roots/post-content-to-markdown)
when active. The bridge surface is the simpler, programmatic query-param form.

### `bfb_get_adapter( $slug ): ?BFB_Format_Adapter`

Resolve a registered adapter directly. Useful when you want to skip the universal router and operate on block arrays
without re-serialising.

### FSE / Site Compiler Consumers

Future static HTML/CSS to block-theme compiler work should treat BFB as the format-conversion substrate, not the layer
that infers FSE intent. The current stable surface is `bfb_convert()` plus the adapter registry. Proposed compiler-facing
helpers and CLI shape are documented in [`docs/fse-compiler-surface.md`](docs/fse-compiler-surface.md).

### Filters

- **`bfb_default_format( $format, $post_type, $content ): string`** — declares which format a CPT writes in by default.
  Hooks into `wp_insert_post_data` so any code path that calls `wp_insert_post()` (REST, WP-CLI, abilities, plugin
  internals) gets the same conversion behaviour.

  ```php
  add_filter( 'bfb_default_format', function ( $format, $post_type ) {
      return $post_type === 'wiki' ? 'markdown' : $format;
  }, 10, 2 );
  ```

- **`bfb_skip_insert_conversion( $skip, $data, $postarr, $format ): bool`** — lets storage layers veto BFB's
  insert-time format → blocks normalisation after the source format is resolved. Use this when another plugin owns the
  canonical `post_content` shape, such as a markdown-on-disk store that needs raw markdown to remain raw markdown.
- **`bfb_markdown_input( $markdown ): string`** — pre-processes Markdown before CommonMark runs.
- **`bfb_register_format_adapter( $adapter, $slug ): ?BFB_Format_Adapter`** — lazy adapter registration.
- **`bfb_rest_supported_post_types( $post_types ): array`** — restricts which CPTs honour `?content_format=`.
- **`bfb_html_to_markdown_options( $options, $html ): array`** — option array passed to league/html-to-markdown
  (mirrors `roots/post-content-to-markdown`'s `converter_options`).
- **`bfb_html_to_markdown_converter( $converter ): void`** — action fired after the html-to-markdown converter is built
  and before it runs, so consumers can register additional league/html-to-markdown converters.
- **`bfb_markdown_output( $markdown, $html, $blocks ): string`** — final filter on the markdown produced by
  `from_blocks()`.
- **`bfb_loaded( $version ): void`** — action fired after the winning BFB package/plugin version initializes.

### Per-call hint: `_bfb_format` on `$postarr`

Bypass the filter for a single insert by setting the `_bfb_format` key:

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

## Known limitations

- **Code-fence language hints round-trip lossily.** `\`\`\`php` becomes `\`\`\`` after Blocks → Markdown. The block
  carries `className: language-php` but league/html-to-markdown doesn't reconstruct the fence info string. Track in
  follow-up issue.
- **Custom blocks without sensible HTML rendering produce garbage markdown.** Out of bridge scope; document in your
  block.

## Tests

Run the conversion smoke suite through Homeboy:

```bash
homeboy test block-format-bridge
```

The suite runs inside WordPress Playground and covers every documented `bfb_convert()` direction: HTML → Blocks,
Blocks → HTML, Markdown → HTML, Markdown → Blocks, Blocks → Markdown, and HTML → Markdown.

## License

GPL-2.0-or-later.
