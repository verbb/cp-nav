# Node Keys

A node key identifies an item in the navigation tree. Use it when your module needs to find an item in an event handler or read a saved customisation. For example, `craft:dashboard` identifies Dashboard even if an administrator renames its label.

You receive each item's `key` in the `resolvedNodes` array supplied to the [navigation event](/developers/events#the-modifyresolvednav-event). Use the keys supplied by the tree rather than deriving them from labels. Customisation events provide `nodeKey` for single-item changes.

## Format

Keys have a namespace followed by a colon and the item's identifying path or UUID:

```text
{namespace}:{path}
```

| Item | Pattern | Example |
| --- | --- | --- |
| Craft item | `craft:{url}` | `craft:dashboard` |
| Craft child | `craft:{parentUrl}/{subHandle}` | `craft:graphql/schemas` |
| Plugin item | `plugin:{rootUrl}` | `plugin:formie` |
| Plugin child | `plugin:{rootUrl}:{subHandle}` | `plugin:formie:submissions` |
| Manual link | `manual:{uuid}` | `manual:f47ac10b-58cc-4372-a567-0e02b2c3d479` |
| Divider | `divider:{uuid}` | `divider:6ba7b810-9dad-11d1-80b4-00c04fd430c8` |

Craft and plugin keys are derived from the available menu items. Manual links and dividers receive a UUID when created. Namespaces distinguish items with otherwise similar identifiers; keep the whole key when comparing items.

Plugin roots retain their full relative route, excluding query parameters and fragments. For example, `formie/forms` and `formie/settings` have different keys. Submenu handles preserve case: `contentBlocks` and `content-blocks` are distinct. Reserved characters in plugin routes and submenu handles are URL-encoded, while route slashes remain readable.

Upgrading an earlier v6 beta migrates available provider keys, parent references, and acknowledged items to this format. Where an old key collided, its saved customization follows the last provider item, matching the previous catalog behaviour.

## Project Config Encoding

Project Config stores each node under an encoded path segment. `NodeKey::encodePathKey()` keeps the namespace and encodes the remainder using base64url without padding, prefixed by `__b64_`. For example, `craft:dashboard` becomes `craft__b64_ZGFzaGJvYXJk`. The stored value also contains the canonical `key`.

Use the helper when inspecting encoded paths. This standalone PHP example can run with the Craft project's Composer autoloader already loaded:

```php
use verbb\cpnav\nav\sources\NodeKey;

$key = 'craft:dashboard';
$segment = NodeKey::encodePathKey($key);
$decoded = NodeKey::decodePathKey($segment);
```

Here, `$segment` is `craft__b64_ZGFzaGJvYXJk` and `$decoded` is `craft:dashboard`. The customisation service handles encoding when you use it to read or save nodes; you don't need to assemble Project Config paths yourself.

## Unavailable Items

A saved customisation for a Craft or plugin key is ignored while that key is absent from the available navigation. Rendering the sidebar does not delete its configuration. Use [Audit Customisations](/developers/console-commands#audit-customisations) to review and, when appropriate, remove stale keys.
