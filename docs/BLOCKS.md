# TEDA dynamic blocks

TEDA's dynamic sections (hero, stat band, events, …) are **native dynamic blocks**
in `teda-core`, registered from `block.json` with a PHP `render_callback`. **No build
step** (D4): no npm, no webpack, no `node_modules`, no compiled JS. The one editor
script is hand-written ES5.

## Naming

- Block name: `teda/<name>` (kebab-case), e.g. `teda/stat-band`.
- Renderer class, resolved by convention: `teda/stat-band` → `Teda_Core\Blocks\Stat_Band`.

## Where things live (D2)

- **Markup** → the renderer's `render_content()` in `teda-core`. Semantic first; it
  must read acceptably with the child theme inactive.
- **Presentation** → `teda-child` CSS, using `--teda-*` / `--theme-*` tokens. The
  plugin ships no styles.

## How to add a block

1. Create `blocks/<name>/block.json` — `name`, `title`, `category: "teda"`, `icon`,
   and an `attributes` schema (types + defaults).
2. Create `src/Blocks/<Studly_Name>.php` extending `Block_Renderer`:
   - implement `name()` and `render_content()`;
   - if the block can be empty, override `is_empty()` **and** `render_empty()` — every
     block must declare its empty state (house rule 8);
   - run every query through `Blocks\Query::get()` (bounded, capped at 24).
3. That's it — `Blocks\Registry` discovers the folder, registers the block, resolves
   the renderer, and the shared `blocks/editor.js` gives it a ServerSideRender preview
   plus auto-generated InspectorControls from the attribute schema (string → text,
   number → number, boolean → toggle).
4. Add the block's CSS to `teda-child` (a `teda-<name>` wrapper class is emitted).

### Attribute control hints

An attribute may carry an optional `teda` object in `block.json` to steer its editor
control and group it under a panel — no per-block JS:

```json
"s1_image": { "type": "integer", "default": 0, "teda": { "control": "media", "group": "Slide 1" } }
```

- `control`: `media` (image picker → stores the attachment ID), `url` (URL field) or
  `textarea` (multi-line). Omit to derive from `type`.
- `group`: the PanelBody the control lives under. Ungrouped controls share one default
  panel. Panels render in first-seen order; the first is open.

## Contracts provided by `Block_Renderer`

- `render()` wraps output with `get_block_wrapper_attributes()` and a
  `teda-block teda-<name>` class (`teda-block--empty` in the empty state).
- `int_attr()` / `str_attr()` / `bool_attr()` — coerced attribute access.
- `remember( $post_type, $key, $cb )` — front-end render cache keyed on the post
  type's `last_changed` (never caches in the editor / REST preview); also flushed on
  `save_post`.

## Query rules

Every block query goes through `Blocks\Query::get()`: `no_found_rows` by default
(set `false` only when you need pagination), an explicit `posts_per_page`,
`ignore_sticky_posts`, and a hard cap of **24** — a block cannot run an unbounded query.
