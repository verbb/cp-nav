/**
 * Densify the Craft CP sidebar for overview screenshots.
 *
 * Fresh docs installs are Solo with no sections/category groups, so Entries /
 * Categories / Users never appear. Seed Pro + one section + one category group
 * (idempotent), then acknowledge the default layout registry so the builder
 * does not show a "new items" banner on capture.
 *
 * Note: no opening `<?php` — docs-screenshots injects this into a bootstrap file.
 */

use craft\enums\CmsEdition;
use craft\models\CategoryGroup;
use craft\models\CategoryGroup_SiteSettings;
use craft\models\EntryType;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use craft\models\Site;
use verbb\cpnav\CpNav;

const DOCS_SCREENSHOT_SECTION_HANDLE = 'docsScreenshotPages';
const DOCS_SCREENSHOT_CATEGORY_GROUP_HANDLE = 'docsScreenshotTopics';

function docsScreenshotSection(string $handle, string $name): Section
{
    $entriesService = Craft::$app->getEntries();
    $section = $entriesService->getSectionByHandle($handle);

    if ($section) {
        return $section;
    }

    $entryType = new EntryType([
        'name' => $name . ' Type',
        'handle' => $handle . 'Type',
        'hasTitleField' => true,
    ]);

    if (!$entriesService->saveEntryType($entryType)) {
        throw new RuntimeException('Unable to save docs screenshot entry type: ' . json_encode($entryType->getErrors()));
    }

    $section = new Section([
        'name' => $name,
        'handle' => $handle,
        'type' => Section::TYPE_CHANNEL,
    ]);
    $section->setEntryTypes([$entryType]);
    $section->setSiteSettings(array_map(
        static fn(Site $site): Section_SiteSettings => new Section_SiteSettings([
            'siteId' => $site->id,
            'enabledByDefault' => true,
            'hasUrls' => true,
            'uriFormat' => $handle . '/{slug}',
            'template' => '_docs-screenshot/entry',
        ]),
        Craft::$app->getSites()->getAllSites(),
    ));

    if (!$entriesService->saveSection($section)) {
        throw new RuntimeException('Unable to save docs screenshot section: ' . json_encode($section->getErrors()));
    }

    $saved = $entriesService->getSectionByHandle($handle);

    if (!$saved) {
        throw new RuntimeException("Docs screenshot section `{$handle}` could not be reloaded.");
    }

    return $saved;
}

function docsScreenshotCategoryGroup(string $handle, string $name): CategoryGroup
{
    $groupsService = Craft::$app->getCategories();
    $group = $groupsService->getGroupByHandle($handle);

    if ($group) {
        return $group;
    }

    $group = new CategoryGroup([
        'name' => $name,
        'handle' => $handle,
        'maxLevels' => null,
    ]);
    $group->setSiteSettings(array_map(
        static fn(Site $site): CategoryGroup_SiteSettings => new CategoryGroup_SiteSettings([
            'siteId' => $site->id,
            'hasUrls' => true,
            'uriFormat' => $handle . '/{slug}',
            'template' => '_docs-screenshot/category',
        ]),
        Craft::$app->getSites()->getAllSites(),
    ));

    if (!$groupsService->saveGroup($group)) {
        throw new RuntimeException('Unable to save docs screenshot category group: ' . json_encode($group->getErrors()));
    }

    $saved = $groupsService->getGroupByHandle($handle);

    if (!$saved) {
        throw new RuntimeException("Docs screenshot category group `{$handle}` could not be reloaded.");
    }

    return $saved;
}

// Pro unlocks Users in Craft's CP nav (Solo never registers it).
Craft::$app->setEdition(CmsEdition::Pro);

docsScreenshotSection(DOCS_SCREENSHOT_SECTION_HANDLE, 'Pages');
docsScreenshotCategoryGroup(DOCS_SCREENSHOT_CATEGORY_GROUP_HANDLE, 'Topics');

// Fingerprint inputs changed — drop any Solo-era sources cache, then mark the
// denser registry as acknowledged so capture does not show the new-items banner.
CpNav::$plugin->getNavSources()->invalidate();

$layout = CpNav::$plugin->getLayouts()->getDefaultLayout();

if ($layout) {
    CpNav::$plugin->getNavCustomization()->acknowledgeCurrentRegistry($layout->uid);
}

// runCraftScript never runs $app->run(), so EVENT_AFTER_REQUEST never fires —
// flush PC explicitly or setEdition (and acknowledge) die with the process.
Craft::$app->getProjectConfig()->flush();

echo "ok\n";
