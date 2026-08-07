<?php
namespace verbb\cpnav\nav\customization;

/**
 * Nav customization project config contract.
 */
final class CustomizationSchema
{
    // Constants
    // =========================================================================

    public const SCHEMA_VERSION = 1;
    public const ROOT_KEY = 'cp-nav';
    public const LAYOUTS_KEY = 'cp-nav.layouts';
    public const CUSTOMIZATIONS_KEY = 'customizations';
    public const NODES_KEY = 'nodes';
    public const ACKNOWLEDGED_REGISTRY_KEYS_KEY = 'acknowledgedRegistryKeys';
}
