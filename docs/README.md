# Control Panel Nav Plugin Docs

Docs markdown is consumed on [verbb.io](https://verbb.io); VitePress here is **local preview only**.

From the **plugin root** (directory with main `composer.json`):

```bash
npm install
npm run dev:plugin-docs
```

Open `http://localhost:5490/feature-tour/overview` to preview the docs. The preview root redirects to Overview; the documentation has no separate index page.

## Screenshot Automation

All product captures use the shared **`@verbb/craft-screenshots`** package from the plugin’s top-level **`screenshots/`** directory. Documentation scenarios live under **`screenshots/scenarios/docs/`**, their plugin-specific fixtures live under **`screenshots/support/docs/`**, and generated documentation images live under **`screenshots/output/docs/`**.

From the plugin root:

```bash
npm run screenshots -- prepare
npm run screenshots -- preview --reuse-install last --filter docs/feature-tour/overview
npm run screenshots -- capture --reuse-install last --filter docs/feature-tour/overview
```

The filter matches the scenario file path. The shared package owns the disposable Craft installation, Verbb capture identity, retina enforcement, and timeless control-panel cleanup; this repository owns only CP Nav’s scenarios, fixtures, framing, and outputs.
