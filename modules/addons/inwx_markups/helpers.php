<?php

use INWX\Markups\Helpers\LanguageHelper;
use INWX\Markups\Helpers\UrlHelper;
use INWX\Markups\Models\TldDefault;
use INWX\Markups\Models\TldOverride;
use INWX\Markups\Services\PricingService;

/**
 * Translate addon strings using WHMCS addon module language system.
 *
 * @param string $key     Language key
 * @param string $default Default value if translation not found
 */
function inwx_markups_lang(string $key, string $default): string
{
    return LanguageHelper::translate($key, $default);
}

/**
 * Build module URL with tool parameter.
 *
 * @param string $moduleLink Base module link
 * @param string $tool       Tool name (default: 'markups')
 * @param array  $params     Additional query parameters
 */
function inwx_markups_buildUrl(string $moduleLink, string $tool = 'markups', array $params = []): string
{
    return UrlHelper::build($moduleLink, $tool, $params);
}

/**
 * Round a price up to the next value ending with a given decimal fraction.
 *
 * @param float $price          Price after markup
 * @param float $roundingEnding Decimal ending (e.g., 0.99, 0.95, 0.90)
 *
 * @return float Rounded price
 */
function inwx_roundToEnding(float $price, float $roundingEnding = 0.99): float
{
    return PricingService::roundToEnding($price, $roundingEnding);
}

/**
 * Fetch TLD overrides from database.
 *
 * @return array Indexed by [tld][currency_id]
 */
function inwx_fetchTldOverrides(): array
{
    return TldOverride::getAllAsArray();
}

/**
 * Fetch TLD defaults from database.
 *
 * @return array Indexed by [currency_id]
 */
function inwx_fetchTldDefaults(): array
{
    return TldDefault::getAllAsArray();
}
