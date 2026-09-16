<?php

use craft\models\EntryType;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use verbb\cpnav\CpNav;
use verbb\cpnav\nav\customization\CustomizationNode;
use verbb\cpnav\nav\sources\NavSources;

it('refreshes a warm catalog when a real Craft section is added and removed without losing overrides', function() {
    $entries = Craft::$app->getEntries();
    expect($entries->getAllSections())->toBe([]);
    $layout = $this->fixtureLayout();
    $customization = CpNav::$plugin->getNavCustomization();
    $customization->saveNode($layout->uid, new CustomizationNode('craft:dashboard', true, 10, '', 'Editorial home'));
    $this->nextConfigRequest();
    $sources = CpNav::$plugin->getNavSources();
    $before = $sources->getTree();
    expect(array_column($before, 'key'))->not->toContain('craft:content/entries');
    expect((new NavSources())->getTree())->toEqual($before);

    $type = new EntryType(['name' => 'Catalog article', 'handle' => 'catalogArticle']);
    expect($entries->saveEntryType($type))->toBeTrue();
    $this->onCleanup(fn() => $entries->deleteEntryType($type));
    $section = new Section([
        'name' => 'Catalog news', 'handle' => 'catalogNews', 'type' => Section::TYPE_CHANNEL,
        'entryTypes' => [$type],
        'siteSettings' => [new Section_SiteSettings([
            'siteId' => Craft::$app->getSites()->getPrimarySite()->id,
            'enabledByDefault' => true, 'hasUrls' => false,
        ])],
    ]);
    expect($entries->saveSection($section))->toBeTrue();
    $this->onCleanup(function() use ($entries, $section) {
        if ($entries->getSectionById($section->id)) {
            $this->nextConfigRequest();
            $entries->deleteSection($section);
        }
    });
    $this->nextConfigRequest();
    // No explicit CP Nav invalidation: Craft's real lifecycle must refresh both cache layers.
    $added = $sources->getTree();
    expect(array_column($added, 'key'))->toBe(array_merge(
        array_slice(array_column($before, 'key'), 0, 1), ['craft:content/entries'], array_slice(array_column($before, 'key'), 1),
    ));
    expect((new NavSources())->getTree())->toEqual($added);
    $resolved = CpNav::$plugin->getNavResolver()->resolve($added, $customization->getCustomizationForLayout($layout->uid), $layout->uid);
    expect(array_column($resolved, 'label', 'key')['craft:dashboard'])->toBe('Editorial home');
    expect(array_column($resolved, 'label', 'key')['craft:content/entries'])->toBe('Entries');

    expect($entries->deleteSection($section))->toBeTrue();
    $this->nextConfigRequest();
    expect($sources->getTree())->toEqual($before);
    expect((new NavSources())->getTree())->toEqual($before);
    expect($customization->getCustomizationForLayout($layout->uid)['craft:dashboard']->label)->toBe('Editorial home');
});
