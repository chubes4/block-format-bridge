# Block-Theme Conversion Workflow

Issue: https://github.com/chubes4/block-format-bridge/issues/75

This document defines the stack workflow for deterministic block-theme conversion across Blocks Engine PHP transformer,
Block Format Bridge (BFB), and higher-level compiler consumers such as Studio, Data Machine, or site-generation agents.

Use current WordPress language for this work: Site Editor, block themes, templates, template parts, Styles, and
`theme.json`. BFB is a compatibility/API surface; it is not a site compiler.

## What "Every Block" Means

"Every block" means every core block has an explicit classification, not that every block gets a raw HTML transform.

Complete coverage starts with inventory and classification:

1. Generate the core block inventory from WordPress/Gutenberg `block.json` metadata.
2. Classify each block by the layer that can safely decide it.
3. Implement Blocks Engine raw transforms only for blocks whose intent is present in source markup.
4. Expose the active support matrix through BFB capability surfaces.
5. Let compiler consumers own the blocks that require site, template, query, entity, or design-system intent.

This keeps "complete coverage" honest. A block can be covered by being classified as compiler-only.

## Layer Map

```text
WordPress / Gutenberg block.json metadata
        |
        v
Blocks Engine core-block inventory + classification
        |
        +--> safe raw HTML transforms
        +--> explicit-marker transforms
        +--> compiler-only classifications
        |
        v
BFB compatibility/API surface
        |
        +--> PHP APIs: bfb_convert(), bfb_to_blocks(), bfb_normalize()
        +--> Abilities API: machine-readable capability + conversion operations
        +--> WP-CLI / REST wrappers where useful
        |
        v
Compiler consumers
        |
        +--> templates and template parts
        +--> patterns
        +--> persistent navigation entities
        +--> query, post, comment, and site-identity intent
        +--> Styles and theme.json
```

## Blocks Engine Transformer Responsibilities

Blocks Engine PHP transformer owns deterministic raw HTML to core block-array transforms.

Blocks Engine PHP transformer should:

- Generate a core-block inventory and classification map from WordPress/Gutenberg `block.json` metadata.
- Keep the generated coverage documentation in sync with that map.
- Implement raw transforms when source HTML carries enough signal to preserve the author's intent.
- Implement explicit shared marker transforms for primitives such as pattern and template-part references when BFB
  documents the public marker vocabulary.
- Fall back safely when markup is ambiguous.

Blocks Engine PHP transformer should not:

- Create or update WordPress entities such as `wp_navigation` posts.
- Infer template hierarchy from arbitrary wrappers such as `<header>` or `<footer>`.
- Select patterns by visual similarity.
- Generate `theme.json`, Styles, or design-token decisions.

## BFB Responsibilities

BFB owns the public API surface and consumer-facing ergonomics around Blocks Engine FormatBridge.

BFB should expose conversion and capability operations through an ability-first machine surface. The WordPress Abilities
API is the primitive for agent and automation consumers. WP-CLI and REST should be wrappers around the same conversion
and capability contracts, not separate source-of-truth APIs.

BFB should:

- Route supported source formats through the block-array pivot.
- Expose `bfb_convert()`, `bfb_to_blocks()`, and `bfb_normalize()` for PHP callers.
- Expose ability operations for machine callers, including conversion and capability reporting.
- Keep WP-CLI ergonomics thin and script-friendly for humans and shell-based tools.
- Report what the active transformer supports so compiler consumers can plan upstream transformer work or compiler-owned decisions.
- Consume Blocks Engine transformer capabilities instead of duplicating transform internals.

BFB should not:

- Duplicate Blocks Engine raw transforms.
- Infer site structure or design intent.
- Depend on Studio, Data Machine, or any specific compiler consumer.
- Treat CLI or REST output as a richer contract than the underlying ability/API operation.

Tracking:

- BFB #76: https://github.com/chubes4/block-format-bridge/issues/76

## Compiler Consumer Responsibilities

Compiler consumers own site and Site Editor intent. Examples include Studio, Data Machine, and site-generation layers that
assemble full block themes or generated pages from prompts, designs, or imported sites.

Compiler consumers should:

- Decide template hierarchy and template-part boundaries.
- Decide whether a navigation structure should become a persisted WordPress navigation entity.
- Decide whether repeated sections should become patterns or ordinary block groups.
- Decide query, post, comment, and site-identity blocks from WordPress context.
- Generate or update Styles and `theme.json` from site-wide design-system choices.
- Ask BFB for capabilities before delegating static fragments to conversion.

Compiler consumers should not expect BFB or Blocks Engine to recover missing intent from arbitrary HTML. If the source does not
name the WordPress concept, the compiler must decide it before conversion or accept a safe static fallback.

## Why Compiler-Only Blocks Stay Above BFB

Many block-theme blocks are not just markup shapes. They are references to WordPress runtime state.

Examples:

- `core/navigation` with a persistent `ref` points at a WordPress navigation entity.
- `core/query` encodes query semantics, not just a repeated list of cards.
- `core/post-title`, `core/post-content`, and comment blocks depend on template context.
- `core/site-logo` and related site-identity blocks depend on configured site state.
- Global Styles and `theme.json` express site-wide design tokens, not per-fragment HTML conversion.

BFB can serialize block arrays once those decisions are explicit. It should not invent those decisions during format
conversion.

## Workflow

1. Blocks Engine PHP transformer publishes the core-block inventory and classification map.
2. Blocks Engine PHP transformer implements deterministic transforms and explicit-marker transforms for safe classes.
3. BFB exposes the resulting support through capability reports.
4. Compiler consumers request the BFB capability report through the Abilities API.
5. Compiler consumers split work into deterministic fragments and site-intent decisions.
6. Deterministic fragments go through BFB conversion.
7. Site-intent decisions are resolved by the compiler, then written as block arrays, serialized block markup, templates,
   template parts, patterns, navigation entities, or `theme.json` as appropriate.
8. Experiments measure transformer coverage, validation failures, editability, and generation cost.

## Related Documents

- Mechanical conversion matrix: [`docs/mechanical-block-theme-conversion.md`](mechanical-block-theme-conversion.md)
- Compiler consumer surface: [`docs/block-theme-compiler-surface.md`](block-theme-compiler-surface.md)
