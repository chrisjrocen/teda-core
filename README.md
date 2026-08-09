# teda-core

The TEDA **content model**, as a plugin. It owns everything that must survive a theme
change (SPEC.md §5, PROMPTS.md **C10/D2**): custom post types, taxonomies, meta fields,
dynamic blocks, cron and the redirect map.

- **No presentation.** Blocks render semantic markup here; their *styling* lives in
  [`teda-child`](../teda-child) (**D2**). Blocks must render acceptably with the child
  theme inactive.
- **No click-ops.** Meta Box field groups, Fluent Forms definitions and Customizer
  exports are defined in PHP/JSON and version-controlled, so they survive a database
  restore (**C3**).
- **Degrade, never fatal.** Missing Meta Box / Fluent Forms produces an admin notice and
  safe defaults, never a fatal (house rule 13, **D6**).

## What lives here

| Path | Purpose |
|---|---|
| `teda-core.php` | Plugin header, constants, boot |
| `src/` (`Teda_Core\`) | PSR-4 classes: PostTypes, Fields, Blocks, Forms, Support, Admin, Cron, Redirects |
| `blocks/*/block.json` | Dynamic blocks — `block.json` + PHP `render_callback`, **no build step** (**D4**) |
| `forms/` | Exported Fluent Forms JSON (`wp teda import-forms`) |

## Dependencies

- **Meta Box (free)** — field groups (**C3**).
- **Fluent Forms Lite** — forms + soft capacity counting via one adapter (**D6**).
- **teda-child** theme renders this plugin's content. Versions independently (**D12**).

## Boot order

`post types → taxonomies → fields → blocks → cron → redirects → admin` (P01).
Activation flushes rewrites and schedules the daily cron; deactivation unschedules and
flushes — **never deletes content**.
