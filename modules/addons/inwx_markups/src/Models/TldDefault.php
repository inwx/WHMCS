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
     * Get all defaults as an associative array indexed by currency_id.
     *
     * @return array Indexed by [currency_id]
     */
    public static function getAllAsArray(): array
    {
        try {
            if (!Capsule::schema()->hasTable(self::TABLE)) {
                return [];
            }

            $rows = Capsule::table(self::TABLE)->get();
        } catch (\Throwable $e) {
            return [];
        }

        $defaults = [];
        foreach ($rows as $row) {
            $defaults[$row->currency_id] = [
                'mode' => $row->mode,
                'fixed_register' => $row->fixed_register,
                'fixed_renew' => $row->fixed_renew,
                'fixed_transfer' => $row->fixed_transfer,
                'fixed_restore' => $row->fixed_restore ?? null,
                'markup_type_register' => $row->markup_type_register ?? null,
                'markup_value_register' => $row->markup_value_register ?? null,
                'markup_type_renew' => $row->markup_type_renew ?? null,
                'markup_value_renew' => $row->markup_value_renew ?? null,
                'markup_type_transfer' => $row->markup_type_transfer ?? null,
                'markup_value_transfer' => $row->markup_value_transfer ?? null,
                'markup_type_restore' => $row->markup_type_restore ?? null,
                'markup_value_restore' => $row->markup_value_restore ?? null,
                'rounding_ending' => $row->rounding_ending ?? null,
            ];
        }

        return $defaults;
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
