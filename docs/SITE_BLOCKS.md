# Site blocks (homepage layout)

The visitor homepage is rendered from a JSON document stored in `site_layouts` (key `visitor_home`). Admins edit it at **Settings → Homepage layout** (`/settings/site-layout/visitor_home/edit`) as raw JSON. The saved document is validated on the server using each block’s Laravel rules.

## Add a new block type

1. **PHP** — Create a class in `app/SiteBlocks/` implementing `App\Contracts\SiteBlockContract` (extend `AbstractSiteBlock`). Implement:
   - `type()` — unique string id (e.g. `my_banner`).
   - `label()`, `category()` — for the block palette.
   - `defaultProps()` — array of default values (arrays/objects are edited as JSON in the layout document).
   - `rules()` — flat validation rules for props (keys only, no `props.` prefix).
   - `viewName()` — Blade view e.g. `site-blocks.my-banner`.

2. **Register** — Add your class to `SiteBlockRegistry::typeMap()` in `app/SiteBlocks/SiteBlockRegistry.php`.

3. **Blade** — Add `resources/views/site-blocks/my-banner.blade.php`. You receive `$props`, plus any context passed by `x-site.layout-renderer` (e.g. `$tagline`, `$popularMemorials` for the home layout).

4. **No frontend bundle** — New block types are picked up automatically by the PHP registry; no npm rebuild is required for the layout editor.

## JSON shape

```json
{
  "version": 1,
  "blocks": [
    { "type": "hero", "props": { ... } },
    { "type": "features_grid", "props": { ... } }
  ]
}
```

## Navigation menus

Header and footer columns are driven by `menus` / `menu_items`. Edit them under **Settings → Navigation menus**. If a menu has no items, the Blade components fall back to the previous hard-coded links.
