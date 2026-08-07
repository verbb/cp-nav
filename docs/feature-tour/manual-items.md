# Manual Items

Manual items are custom links you add to the sidebar — internal control panel paths or external URLs.

## Create a manual item

Use **New menu item** in the builder header. Enter a **Label** and **URL**, then save. Nothing is persisted until you save the create popover.

New manuals append at the end of the top-level tree for the current layout. Drag to reposition or nest under another item (max two levels; dividers cannot be parents).

## URLs

- **Internal CP paths** — relative paths without your CP base URL, e.g. `entries/pages/homepage` or `settings/plugins`.
- **External links** — absolute URLs including the protocol (`https://…`). Enable **New window** when the link should open externally.

While Control Panel Nav is active, its labels and visibility for Craft/plugin items override names set in other plugins’ settings.

## Environment variables and aliases

Manual URLs support Craft [environment variables and aliases](https://craftcms.com/docs/5.x/configure.html#control-panel-settings) (for example `$PRIMARY_SITE_URL` or `@web`). Values are stored as entered and expanded when the nav is rendered.

## Site tokens

Manual URLs may include:

| Token | Substituted value |
| --- | --- |
| `{site}` | Current site ID |
| `{siteHandle}` | Current site handle |

At render time, env/aliases are expanded first, then site tokens.

## Icons

Optional **Custom Icon** (SVG asset) for the sidebar glyph.
