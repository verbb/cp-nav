/**
 * Side-effect entry: register Plugin Kit glyphs before any <pk-icon icon="…"> lookup.
 * Bundler builds start with an empty registry — without this, icons render blank.
 *
 * Import this module first from main.tsx so registration runs before React mount.
 */
import '@verbb/plugin-kit-icons/all.js';
