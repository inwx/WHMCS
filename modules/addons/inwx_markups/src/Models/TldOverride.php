<?php

namespace INWX\Markups\Models;

use WHMCS\Database\Capsule;

/**
 * TldOverride Model.
 *
 * Represents per-TLD overrides for pricing configuration.
 */
class TldOverride
{
    private const TABLE = 'mod_inwx_tld_overrides';

    /**
     * Get all overrides excluding 'none' mode.
     *
     * @param string|null $search     Search term for TLD, mode, or currency
     * @param array       $currencies Currency collection for filtering
     * @param int         $limit      Number of records per page
     * @param int         $offset     Offset for pagination
     */
    public static function getActiveOverrides(
        ?string $search = null,
        array $currencies = [],
        int $limit = 10,
        int $offset = 0,
    ): array {
        $query = Capsule::table(self::TABLE)->where('mode', '!=', 'none');

        if ($search !== null && $search !== '') {
            // Escape special LIKE characters
            $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $searchPattern = '%' . $escapedSearch . '%';

            $query->where(function ($q) use ($searchPattern, $search, $currencies) {
                $q->where('tld', 'like', $searchPattern)
                    ->orWhere('mode', 'like', $searchPattern);

                foreach ($currencies as $currency) {
                    if (stripos($currency->code, $search) !== false) {
                        $q->orWhere('currency_id', $currency->id);
                    }
                }
            });
        }

        return $query
            ->orderBy('tld')
            ->orderBy('currency_id')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->toArray();
    }

    /**
     * Count active overrides (excluding 'none' mode).
     *
     * @param string|null $search     Search term
     * @param array       $currencies Currency collection for filtering
     */
    public static function countActiveOverrides(?string $search = null, array $currencies = []): int
    {
        $query = Capsule::table(self::TABLE)->where('mode', '!=', 'none');

        if ($search !== null && $search !== '') {
            // Escape special LIKE characters
            $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $searchPattern = '%' . $escapedSearch . '%';

            $query->where(function ($q) use ($searchPattern, $search, $currencies) {
                $q->where('tld', 'like', $searchPattern)
                    ->orWhere('mode', 'like', $searchPattern);

                foreach ($currencies as $currency) {
                    if (stripos($currency->code, $search) !== false) {
                        $q->orWhere('currency_id', $currency->id);
                    }
                }
            });
        }

        return $query->count();
    }

    /**
     * Get all overrides as an associative array.
     *
     * @return array Indexed by [tld][currency_id]
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

        $overrides = [];
        foreach ($rows as $row) {
            $overrides[$row->tld][$row->currency_id] = [
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

        return $overrides;
    }

    /**
     * Find an override by ID.
     */
    public static function findById(int $id): ?object
    {
        return Capsule::table(self::TABLE)->where('id', $id)->first();
    }

    /**
     * Create or update an override.
     *
     * @param int $id Override ID (0 for new record)
     */
    public static function save(array $data, int $id = 0): bool
    {
        try {
            if ($id > 0) {
                Capsule::table(self::TABLE)->where('id', $id)->update($data);
            } else {
                Capsule::table(self::TABLE)->updateOrInsert(
                    ['tld' => $data['tld'], 'currency_id' => $data['currency_id']],
                    $data
                );
            }

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Delete an override by ID.
     */
    public static function deleteById(int $id): bool
    {
        try {
            Capsule::table(self::TABLE)->where('id', $id)->delete();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Delete an override by TLD and currency.
     */
    public static function deleteByTldAndCurrency(string $tld, int $currencyId): bool
    {
        try {
            Capsule::table(self::TABLE)
                ->where('tld', $tld)
                ->where('currency_id', $currencyId)
                ->delete();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
