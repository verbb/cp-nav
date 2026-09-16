# Console Commands

Use the audit command to review saved navigation customisations after changing the Craft or plugin menu items available to your project. A customisation can remain stored after its original item is removed; the sidebar ignores it, but you may want to remove that unused configuration.

<span id="audit-customizations"></span>

## Audit Customisations

Open a terminal in your Craft project directory and run:

```shell
php craft cp-nav/audit-customizations
```

The command reports each layout's name and UID, any stale customisation keys, and the number of available menu items without customisations. A UID is the identifier for a layout across environments. Items without customisations use their defaults; that count does not by itself indicate a problem.

To inspect one layout, replace `YOUR_LAYOUT_UID` with the UID from the output:

```shell
php craft cp-nav/audit-customizations --layoutUid=YOUR_LAYOUT_UID
```

### Removing Stale Customisations

Run the audit in an environment with the project's intended plugins and content configuration available, such as staging. Review why each listed item is absent before removing its customisation; an item may be temporarily unavailable because a plugin is disabled.

Keep a Project Config backup before applying changes. To remove the stale keys for the layout you reviewed, run:

```shell
php craft cp-nav/audit-customizations --layoutUid=YOUR_LAYOUT_UID --fix=1
```

The command reports the keys it removed. Manual links and dividers are kept. Run the audit again, check the affected sidebar, and review the Project Config changes before deploying them through your usual process.

### Options

| Option | Default | Effect |
| --- | --- | --- |
| `--layoutUid` | All layouts | Limits the audit to one layout UID. |
| `--fix` | `0` | Removes stale Craft and plugin customisation keys when set to `1`. |
