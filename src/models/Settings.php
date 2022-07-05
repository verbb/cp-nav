<?php
namespace verbb\cpnav\models;

use Craft;
use craft\base\Model;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use craft\web\assets\cp\CpAsset;

class Settings extends Model
{
    // Constants
    // =========================================================================

    public const SUBNAV_DEFAULT = 'default';
    public const SUBNAV_ALWAYS_OPEN = 'alwaysOpen';
    public const SUBNAV_OPEN_TOGGLE = 'openToggle';


    // Properties
    // =========================================================================

    public string $defaultSubnavBehaviour = self::SUBNAV_DEFAULT;


    // Public Methods
    // =========================================================================

    public function __construct($config = [])
    {
        // Remove deprecated settings
        unset($config['originalNavHash']);

        parent::__construct($config);
    }

    public function getFontIconOptions(): array
    {
        return Craft::$app->getCache()->getOrSet('craft-font-options', function() {
            $options = [];

            try {
                $view = Craft::$app->getView();
                $path = FileHelper::normalizePath( __DIR__ . '/../json/craft-font.json');
                $json = Json::decode(@file_get_contents($path));

                foreach (($json['icons'] ?? []) as $icon) {
                    $ligatures = $icon['properties']['ligatures'] ?? '';
                    $name = $icon['properties']['name'] ?? '';
                    $tags = $icon['icon']['tags'] ?? [];

                    // Names can also contain multiple properties
                    $ligatures = explode(', ', $ligatures);
                    $name = explode(', ', $name);

                    if (!$ligatures[0]) {
                        continue;
                    }

                    // Sometimes the primary glyph isn't always included in tags
                    $tags = array_merge($tags, $name, $ligatures);

                    sort($tags);
                    $tags = array_unique($tags);

                    // Filter out some annoying values
                    $tags = array_filter($tags, function($tag) {
                        return !in_array($tag, ['T']);
                    });

                    $options[] = [
                        'label' => implode(', ', $tags),
                        'value' => $ligatures[0] ?? '',
                    ];
                }

                usort($options, function($a, $b) {
                    return $a['label'] > $b['label'];
                });
            } catch (Throwable $e) {
                CpNav::error(Craft::t('app', '{e} - {f}: {l}.', ['e' => $e->getMessage(), 'f' => $e->getFile(), 'l' => $e->getLine()]));
            }

            return array_merge([
                [ 'value' => '', 'label' => 'Select icon' ],
                [ 'value' => 'title', 'label' => 'First Letter' ],
            ], $options);
        });
    }

    public function getSubnavBehaviourOptions(): array
    {
        $options = [
            [
                'label' => Craft::t('app', 'Open when active'),
                'value' => self::SUBNAV_DEFAULT,
            ],
            [
                'label' => Craft::t('app', 'Always open'),
                'value' => self::SUBNAV_ALWAYS_OPEN,
            ],
            [
                'label' => Craft::t('app', 'Toggle open'),
                'value' => self::SUBNAV_OPEN_TOGGLE,
            ],
        ];

        return $options;
    }

}
