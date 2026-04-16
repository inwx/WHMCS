<?php

namespace INWX\Markups\Helpers;

use WHMCS\Database\Capsule;

/**
 * SchemaHelper.
 *
 * Handles database schema creation and migration.
 */
class SchemaHelper
{
    /**
     * Ensure override table exists and has all required columns.
     */
    public static function ensureSchema(): void
    {
        try {
            $schema = Capsule::schema();
            if ($schema->hasTable('mod_inwx_tld_overrides')) {
                $columns = $schema->getColumnListing('mod_inwx_tld_overrides');
            } else {
                $columns = [];
            }
        } catch (\Throwable $e) {
            $columns = [];
        }

        try {
            self::createOrMigrateOverridesTable($schema, $columns);
            self::createOrMigrateDefaultsTable($schema);
        } catch (\Throwable $e) {
            // Fail silently in UI; activate() will surface schema errors.
        }
    }

    /**
     * Create or migrate the overrides table.
     *
     * @param \Illuminate\Database\Schema\Builder $schema
     */
    private static function createOrMigrateOverridesTable($schema, array $columns): void
    {
        if (!$schema->hasTable('mod_inwx_tld_overrides')) {
            $schema->create('mod_inwx_tld_overrides', function ($table) {
                $table->increments('id');
                $table->string('tld', 50);
                $table->unsignedInteger('currency_id');
                $table->string('mode', 10)->default('none'); // none | disable | fixed | markup
                $table->decimal('fixed_register', 10, 2)->nullable();
                $table->decimal('fixed_renew', 10, 2)->nullable();
                $table->decimal('fixed_transfer', 10, 2)->nullable();
                $table->decimal('fixed_restore', 10, 2)->nullable();
                $table->string('markup_type_register', 10)->nullable();
                $table->decimal('markup_value_register', 10, 2)->nullable();
                $table->string('markup_type_renew', 10)->nullable();
                $table->decimal('markup_value_renew', 10, 2)->nullable();
                $table->string('markup_type_transfer', 10)->nullable();
                $table->decimal('markup_value_transfer', 10, 2)->nullable();
                $table->string('markup_type_restore', 10)->nullable();
                $table->decimal('markup_value_restore', 10, 2)->nullable();
                $table->decimal('rounding_ending', 10, 2)->nullable();
                $table->unique(['tld', 'currency_id']);
            });
            return;
        }

        // Add missing markup / rounding columns on existing installations.
        self::addMissingColumns($schema, $columns);
    }

    /**
     * Add missing columns to overrides table.
     *
     * @param \Illuminate\Database\Schema\Builder $schema
     */
    private static function addMissingColumns($schema, array $columns): void
    {
        if (!in_array('fixed_restore', $columns, true)) {
            $schema->table('mod_inwx_tld_overrides', static function ($table): void {
                $table->decimal('fixed_restore', 10, 2)->nullable()->after('fixed_transfer');
            });
        }

        foreach (['markup_type_register', 'markup_type_renew', 'markup_type_transfer', 'markup_type_restore'] as $col) {
            if (!in_array($col, $columns, true)) {
                $schema->table('mod_inwx_tld_overrides', static function ($table) use ($col): void {
                    $table->string($col, 10)->nullable()->after('fixed_restore');
                });
            }
        }

        $valueColumns = [
            'markup_value_register' => 'markup_type_register',
            'markup_value_renew' => 'markup_type_renew',
            'markup_value_transfer' => 'markup_type_transfer',
            'markup_value_restore' => 'markup_type_restore',
        ];

        foreach ($valueColumns as $col => $typeCol) {
            if (!in_array($col, $columns, true)) {
                $schema->table('mod_inwx_tld_overrides', static function ($table) use ($col, $typeCol): void {
                    $table->decimal($col, 10, 2)->nullable()->after($typeCol);
                });
            }
        }

        if (!in_array('rounding_ending', $columns, true)) {
            $schema->table('mod_inwx_tld_overrides', static function ($table): void {
                $table->decimal('rounding_ending', 10, 2)->nullable()->after('markup_value_restore');
            });
        }
    }

    /**
     * Create or migrate the defaults table.
     *
     * @param \Illuminate\Database\Schema\Builder $schema
     */
    private static function createOrMigrateDefaultsTable($schema): void
    {
        if (!$schema->hasTable('mod_inwx_tld_defaults')) {
            $schema->create('mod_inwx_tld_defaults', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('currency_id');
                $table->string('mode', 10)->default('none');
                $table->decimal('fixed_register', 10, 2)->nullable();
                $table->decimal('fixed_renew', 10, 2)->nullable();
                $table->decimal('fixed_transfer', 10, 2)->nullable();
                $table->decimal('fixed_restore', 10, 2)->nullable();
                $table->string('markup_type_register', 10)->nullable();
                $table->decimal('markup_value_register', 10, 2)->nullable();
                $table->string('markup_type_renew', 10)->nullable();
                $table->decimal('markup_value_renew', 10, 2)->nullable();
                $table->string('markup_type_transfer', 10)->nullable();
                $table->decimal('markup_value_transfer', 10, 2)->nullable();
                $table->string('markup_type_restore', 10)->nullable();
                $table->decimal('markup_value_restore', 10, 2)->nullable();
                $table->decimal('rounding_ending', 10, 2)->nullable();
                $table->unique(['currency_id']);
            });
        } else {
            // Migrate existing defaults table
            $defaultColumns = $schema->getColumnListing('mod_inwx_tld_defaults');
            self::addMissingDefaultColumns($schema, $defaultColumns);
        }
    }

    /**
     * Add missing columns to defaults table.
     *
     * @param \Illuminate\Database\Schema\Builder $schema
     */
    private static function addMissingDefaultColumns($schema, array $defaultColumns): void
    {
        if (!in_array('fixed_restore', $defaultColumns, true)) {
            $schema->table('mod_inwx_tld_defaults', static function ($table): void {
                $table->decimal('fixed_restore', 10, 2)->nullable()->after('fixed_transfer');
            });
        }

        foreach (['markup_type_register', 'markup_type_renew', 'markup_type_transfer', 'markup_type_restore'] as $col) {
            if (!in_array($col, $defaultColumns, true)) {
                $schema->table('mod_inwx_tld_defaults', static function ($table) use ($col): void {
                    $table->string($col, 10)->nullable()->after('fixed_restore');
                });
            }
        }

        $defaultValueColumns = [
            'markup_value_register' => 'markup_type_register',
            'markup_value_renew' => 'markup_type_renew',
            'markup_value_transfer' => 'markup_type_transfer',
            'markup_value_restore' => 'markup_type_restore',
        ];

        foreach ($defaultValueColumns as $col => $typeCol) {
            if (!in_array($col, $defaultColumns, true)) {
                $schema->table('mod_inwx_tld_defaults', static function ($table) use ($col, $typeCol): void {
                    $table->decimal($col, 10, 2)->nullable()->after($typeCol);
                });
            }
        }

        if (!in_array('rounding_ending', $defaultColumns, true)) {
            $schema->table('mod_inwx_tld_defaults', static function ($table): void {
                $table->decimal('rounding_ending', 10, 2)->nullable()->after('markup_value_restore');
            });
        }
    }
}
