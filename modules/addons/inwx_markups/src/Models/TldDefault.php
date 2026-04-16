<?php

namespace INWX\Markups\Models;

use WHMCS\Database\Capsule;

/**
 * TldDefault Model.
 *
 * Represents bulk default pricing configuration per currency.
 */
class TldDefault
{
    private const TABLE = 'mod_inwx_tld_defaults';

    /**
     * Get all defaults.
     */
    public static function getAll(): array
    {
        try {
            return Capsule::table(self::TABLE)->get()->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Save or update default configuration for a currency.
     */
    public static function saveForCurrency(int $currencyId, array $data): bool
    {
        try {
            Capsule::table(self::TABLE)->updateOrInsert(
                ['currency_id' => $currencyId],
                $data
            );

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
