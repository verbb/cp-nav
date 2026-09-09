<?php
namespace verbb\cpnav\helpers;

/**
 * Manual nav URL validation — relative CP paths plus a small absolute-scheme allowlist.
 */
final class ManualUrl
{
    // Constants
    // =========================================================================

    /** Absolute schemes allowed after env/alias expansion. */
    public const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];


    // Static Methods
    // =========================================================================

    /**
     * Whether a stored/expanded URL is safe to put in a CP nav href.
     * Relative paths (no scheme) are allowed; `javascript:` / `data:` / etc. are not.
     */
    public static function isAllowed(mixed $url): bool
    {
        if (!is_string($url)) {
            return false;
        }

        $url = trim($url);

        if ($url === '') {
            return false;
        }

        // Protocol-relative → treat as https for scheme purposes.
        if (str_starts_with($url, '//')) {
            return true;
        }

        if (!preg_match('/^([a-z][a-z0-9+.-]*):/i', $url, $matches)) {
            // No scheme — relative CP path or site-token URL.
            return true;
        }

        return in_array(strtolower($matches[1]), self::ALLOWED_SCHEMES, true);
    }
}
