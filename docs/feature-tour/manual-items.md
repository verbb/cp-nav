# Manual Items

Manual items are links you add to the sidebar for destinations your editors need. You can link to a specific control panel page or to an external resource, such as your team's handbook.

<span id="create-a-manual-item"></span>

## Creating a Manual Item

Open **Control Panel Nav → Navigation** as an administrator and choose the layout you want to edit. Click **New menu item**, enter `Team Handbook` as the **Label**, and enter your handbook's full address in **URL**, such as `https://handbook.example.com`. Replace that example address with your own.

Enable **New window** if you want the browser to open the destination in a new tab or window, keeping the control panel available. Otherwise, the link opens in the current tab.

Click **Save** to add the item. **Cancel** discards the draft. The new link appears at the end of the menu for this layout. Drag it into position, or nest it beneath another menu item. The sidebar supports two levels, and dividers cannot be parents.

Reload the control panel and open the link to check its destination. Also test it with an account that receives this layout. Manual links aren't automatically hidden based on Craft's native menu permissions, and adding a link doesn't grant access to its destination.

<span id="urls"></span>

## Linking to Control Panel Pages

For a control panel destination, enter the path without your control panel base URL. For example, `settings/plugins` links to the Plugins settings page. Only include that link in a layout intended for people who can access those settings.

For an external page, include the full URL, such as `https://handbook.example.com`. Links can also use `mailto:` or `tel:` for email and telephone destinations. Other absolute URL schemes are not accepted.

## Environment Variables and Aliases

Use an environment variable when a link needs a different destination on each environment. For example, define `TEAM_HANDBOOK_URL` in each environment's configuration, with a full URL as its value, then enter `$TEAM_HANDBOOK_URL` in the item's **URL** field and save.

If the variable's value is `https://handbook.example.com`, the sidebar link opens that address. The saved item keeps `$TEAM_HANDBOOK_URL`, so deploying the layout doesn't copy one environment's address into another. Check the link in each environment after deployment.

Craft aliases work here too. Entering `@web` uses the web URL configured for that alias. Set up the environment variable or alias before using it in a menu item.

<span id="site-tokens"></span>

## Linking to the Current Site

On a project with multiple sites, you can include `{site}` for the current site's numeric ID or `{siteHandle}` for its handle, the name that identifies the site in code.

For example, if your handbook provides a page for each site handle, enter `https://handbook.example.com/sites/{siteHandle}` as the **URL**. When Craft's current site has the handle `english`, the link becomes `https://handbook.example.com/sites/english`. Your handbook must provide that destination; the token only constructs the URL.

Environment variables and aliases are expanded before site tokens. You can therefore put a URL containing `{siteHandle}` in an environment variable and use the variable in the menu item. Check the resulting link with each relevant current site; a token does not switch sites by itself.

<span id="icons"></span>

## Custom Icons

Follow [Configuration](/get-started/configuration#setting-up-custom-icons) to make your SVG files available. Open the item, select a file under **Custom Icon**, and click **Save**. The saved path is relative to the configured folder, so deploy the same icon files alongside your project.

![Team Handbook's menu item editor with the SVG icon picker open and book-open.svg selected](../../screenshots/cp-nav-icon-picker.png)
