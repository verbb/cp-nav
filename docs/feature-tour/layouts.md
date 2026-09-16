# Layouts

Layouts let you give different user groups different control panel menus. For example, your content editors might need a short menu focused on writing, while your administrators need a broader set of tools.

Assigning layouts to user groups requires Craft Pro. You need an administrator account to create or edit layouts. Open **Control Panel Nav → Layouts** to manage them.

<span id="managing-layouts"></span>

## Creating and Assigning a Layout

Suppose you already have an Editors user group in Craft. Click **New layout**, enter `Editorial` in **Name**, and select your Editors group under **Permissions**. Save the layout.

Here, **Permissions** chooses which groups receive the layout; it does not change what those groups can access. Configure access in Craft's user-group settings separately. A manual link in the layout can be visible even when its destination is unavailable to the editor, so check those links with an editor account too.

<span id="editing-a-layout-s-nav"></span>

Switch to the **Navigation** tab and select Editorial from the header layout picker. Rename, reorder, or hide items for that layout, and add any links your editors need. All builder changes, including **Reset navigation**, apply only to the selected layout.

Sign in as an editor to check the result. They should receive Editorial, with Craft and plugin items filtered according to their access. Check that the links they need open successfully.

<span id="which-layout-wins"></span>

## Layout Priority

If someone belongs to several groups with assigned layouts, the first matching layout in the Layouts list is used. Drag layouts to reorder their priority. For example, put Editorial above a general Staff layout if editors who belong to both groups should receive Editorial.

The default layout is used when no group assignment matches. It cannot be deleted. An administrator can select another layout in the navigation builder to edit it, but checking that view does not replace testing with the intended editor account.

## Duplicating a Layout

Click **Duplicate** beside a layout to copy its navigation and group assignments. The copy's name ends in `copy`. Open the copy to give it a useful name and adjust its group assignments, then save. Reorder the list if necessary so the intended layout takes priority for people in multiple groups.

## Deleting a Layout

Delete a layout only when its menu is no longer needed. People assigned to it will receive their next matching layout, or the default layout if none matches. Check their sidebar after deletion, particularly when they belong to multiple groups.
