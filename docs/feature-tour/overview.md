# Overview

![Control Panel Nav builder](/_screenshots/feature-tour/overview-builder.png)

Navigate to **Control Panel Nav** in the CP (or `/admin/cp-nav`). You’ll see:

- A **layout picker** in the header (when you have more than the default layout)
- The **nav tree** — Craft, plugin, manual, and divider items for the selected layout
- Actions to **Reset navigation**, add a **New menu item**, or add a **New divider**

Changes save immediately — reordering, editing, creating, and deleting persist as you go. There is no separate Save / Discard session.

## How it works

1. **Nav sources** — CP Nav reads Craft’s live sidebar (core + plugins) and caches it by fingerprint.
2. **Customizations** — Your edits (order, show/hide, labels, custom icons, manual links, dividers) are stored in project config for the layout.
3. **Resolve** — At render, sources and customizations merge, then permissions filter the tree for the current user.
4. **Render** — The resolved tree is injected into Craft’s nav so Craft draws the familiar sidebar markup.

You only store overrides — not a full copy of every Craft menu item — so Craft and plugin updates stay in sync without sync-on-read writes.
