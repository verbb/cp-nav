Control Panel Nav is a Craft CMS plugin to help manage your Control Panel navigation. Rename, reorder, hide, and show menu items — including Craft core and plugin items. Add your own manual links and dividers, and assign different layouts per user group.

## What's new in Control Panel Nav 6

- **React nav builder** — [Plugin Kit](https://docs.verbb.io/plugin-kit/overview/) tree builder with drag-and-drop, indent/outdent, and immediate persistence.
- **Nav sources + customizations** — Live menu items come from Craft’s nav pipeline; only your overrides are stored in project config.
- **Stable node keys** — `craft:`, `plugin:`, `manual:`, and `divider:` keys keep customizations aligned as Craft and plugins change.
- **No sync-on-read** — Control panel page views never write project config.
- **New items notice** — When Craft or a plugin adds a nav item, the builder can surface it for review.
- **Custom SVG icons** — Upload an SVG asset to replace a Craft/plugin icon.
- **Site tokens** — Manual URLs support `{site}` / `{siteHandle}` at render.
- **Console tools** — Migrate and audit customizations from the CLI.

## Features

- Modify the main sidebar navigation in the Craft control panel
- Rename, reorder, and show/hide Craft and plugin menu items
- Nest items up to 2 levels deep
- Add manual URLs (internal CP paths or external links)
- Add divider section breaks with optional labels
- Custom SVG icons per menu item
- Layouts assigned to user groups (Craft Pro)
- Project Config support for environment parity
- Live user permissions — items stay hidden when the user cannot access them
- Unlimited use, free forever

### Build & manage

- Drag-and-drop tree builder with immediate save on reorder, edit, create, and delete.
- Per-layout navigation: switch layouts in the builder header, then reset or customise that layout.
- Manual links for docs, external tools, or deep CP URLs.
- Dividers to group the sidebar into labelled (or rule-only) sections.
- Acknowledge new Craft/plugin items when the registry grows.

### Layouts

- Default layout for everyone without a group assignment.
- Additional layouts mapped to user groups (Craft Pro).
- When a user belongs to multiple groups, the first matching layout by sort order wins.

### Project config & ops

- Customizations live under each layout in project config — deploy nav changes with the rest of your project.
- Console commands to migrate from v5 and audit stale customization keys.
- Reset a layout to clear overrides and return to the live Craft/plugin nav order.

## Documentation

Visit the [Control Panel Nav Plugin page](https://verbb.io/craft-plugins/cp-nav) for all documentation, guides, pricing and developer resources.

## Support

Get in touch with us via the [Control Panel Nav Support page](https://verbb.io/craft-plugins/cp-nav/support) or by [creating a Github issue](https://github.com/verbb/cp-nav/issues)
