<p align="center"><img src="https://assets.verbb.io/plugins/cp-nav/cp-nav-icon.svg" width="100" height="100" alt="Control Panel Nav icon"></p>
<h1 align="center">Control Panel Nav for Craft CMS</h1>

Control Panel Nav is a Craft CMS plugin to help manage your Control Panel navigation. Rename, reorder, hide, and show menu items — including Craft core and plugin items. Add your own manual links and dividers, and assign different layouts per user group.

## What's new in Control Panel Nav 6

- **New nav builder** — [Plugin Kit](https://docs.verbb.io/plugin-kit/overview/) tree builder with drag-and-drop, indent/outdent, and immediate persistence (no Save/Discard session).
- **Nav sources + customizations** — Live menu items come from Craft’s nav pipeline; you only store overrides (order, visibility, labels, icons, manual items, dividers) in project config.
- **Stable node keys** — `craft:`, `plugin:`, `manual:`, and `divider:` keys keep customizations aligned as Craft and plugins change.
- **No sync-on-read** — Control panel page views never write project config. Registry invalidation uses events + fingerprinting.
- **New items notice** — When Craft or a plugin adds a new nav item, the builder can surface it so you can acknowledge or customise it.
- **Custom SVG icons** — Upload an SVG asset to replace a Craft/plugin icon; registry icons stay live until overridden.
- **Site tokens** — Manual URLs support `{site}` / `{siteHandle}` substitution at render.
- **Console tools** — `cp-nav/migrate-customizations` and `cp-nav/audit-customizations` for upgrade and cleanup.

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

## Documentation

Visit the [Control Panel Nav Plugin page](https://verbb.io/craft-plugins/cp-nav) for all documentation, guides, pricing and developer resources.

## Support

Get in touch with us via the [Control Panel Nav Support page](https://verbb.io/craft-plugins/cp-nav/support) or by [creating a Github issue](https://github.com/verbb/cp-nav/issues)

## Sponsor

Control Panel Nav is licensed under the MIT license, meaning it will always be free and open source – we love free stuff! If you'd like to show your support to the plugin regardless, [Sponsor](https://github.com/sponsors/verbb) development.

<h2></h2>

<a href="https://verbb.io" target="_blank">
    <img width="101" height="33" src="https://verbb.io/assets/img/verbb-pill.svg" alt="Verbb">
</a>
