<?php

namespace INWX\Markups\Helpers;

/**
 * LanguageHelper.
 *
 * Handles translation lookups using WHMCS addon module language system.
 */
class LanguageHelper
{
    /**
     * Translate addon strings using WHMCS addon module language system.
     *
     * Uses the module's `lang/*.php` files loaded by WHMCS, as documented in
     * https://developers.whmcs.com/addon-modules/multi-language/
     *
     * @param string $key     Language key
     * @param string $default Default value if translation not found
     *
     * @return string Translated string or default
     */
    public static function translate(string $key, string $default): string
    {
        // WHMCS loads module language strings into the global $_ADDONLANG.
        if (isset($GLOBALS['_ADDONLANG']) && is_array($GLOBALS['_ADDONLANG'])) {
            $addonLang = $GLOBALS['_ADDONLANG'];

            // Support both namespaced keys ($_ADDONLANG['inwx_markups']['key'])
            // and flat keys ($_ADDONLANG['key']).
            if (isset($addonLang['inwx_markups']) && is_array($addonLang['inwx_markups'])) {
                if (array_key_exists($key, $addonLang['inwx_markups']) && $addonLang['inwx_markups'][$key] !== '') {
                    return (string) $addonLang['inwx_markups'][$key];
                }
            }

            if (array_key_exists($key, $addonLang) && $addonLang[$key] !== '') {
                return (string) $addonLang[$key];
            }
        }

        return $default;
    }
}
