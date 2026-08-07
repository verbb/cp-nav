# Control Panel Nav Plugin Docs

Docs markdown is consumed on [verbb.io](https://verbb.io); VitePress here is **local preview only**.

From the **plugin root** (directory with main `composer.json`):

```bash
npm install
npm run dev:plugin-docs
```

Preview: [http://localhost:5490](http://localhost:5490)

## Screenshot automation

Uses **`@verbb/docs-screenshots`**. From plugin root:

```bash
npm run docs:screenshots -- prepare
npm run docs:screenshots -- preview --reuse-install last --filter {scenario-id}
npm run docs:screenshots -- capture --reuse-install last --filter {scenario-id}
```

Feature overview scenarios:

| Scenario id | Output | Size |
| --- | --- | --- |
| `feature-tour-overview-builder` | `_screenshots/feature-tour/overview-builder.png` | 924×700 (full CP chrome; sidebar forced on; bold Craft CMS label) |

Requires Docker (compose runtime). Optional: `CRAFT_SCREENSHOT_DB_*` env vars; run `npx playwright install chromium` once.

Filter matches the scenario **file path** (e.g. `--filter overview`), not only the scenario id.

## Layout

- **`*.screenshot.ts`** — colocated with the page it captures
- **`.screenshots/`** — global profile, plugin bootstrap, fixtures
- **`_screenshots/`** — generated PNG output

List scenario ids:

```bash
rg -n "id:" . -g "*.screenshot.ts"
```
