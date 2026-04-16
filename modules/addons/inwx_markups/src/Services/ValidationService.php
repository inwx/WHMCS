<?php

namespace INWX\Markups\Services;

/**
 * ValidationService.
 *
 * Handles validation logic for markup configuration.
 */
class ValidationService
{
    /**
     * Validate markup fields.
     *
     * @param array $fields Array of ['type' => ..., 'value' => ..., 'label' => ...]
     * @param bool  $isBulk Prefix messages with bulk context
     *
     * @return string|null Error message or null if valid
     */
    public static function validateMarkupFields(array $fields, bool $isBulk = false): ?string
    {
        foreach ($fields as $field) {
            $type = (string) $field['type'];
            $value = (string) $field['value'];
            $label = (string) $field['label'];
            $prefix = $isBulk ? 'error.bulk.markup.' : 'error.markup.';

            if ($type !== '' && $value === '') {
                return sprintf(
                    inwx_markups_lang(
                        $prefix . 'missingValue',
                        '%s Markup: If type is selected, a value must be entered.'
                    ),
                    $label
                );
            }

            if ($type !== '' && !is_numeric(str_replace(',', '.', $value))) {
                return sprintf(
                    inwx_markups_lang(
                        $prefix . 'invalidNumber',
                        '%s Markup: Value must be a number.'
                    ),
                    $label
                );
            }

            if ($type === '' && $value !== '') {
                return sprintf(
                    inwx_markups_lang(
                        $prefix . 'missingType',
                        '%s Markup: If value is entered, a type must be selected.'
                    ),
                    $label
                );
            }
        }

        return null;
    }

    /**
     * Validate basic save parameters.
     *
     * @return string|null Error message or null if valid
     */
    public static function validateSaveParams(string $tld, int $currencyId, string $mode): ?string
    {
        if ($tld === '') {
            return inwx_markups_lang('error.selectTld', 'Please select a TLD.');
        }
        if ($currencyId <= 0) {
            return inwx_markups_lang('error.selectCurrency', 'Please select a currency.');
        }
        if (!in_array($mode, ['none', 'disable', 'fixed', 'markup'], true)) {
            return inwx_markups_lang('error.invalidMode', 'Invalid mode selected.');
        }

        return null;
    }

    /**
     * Validate rounding ending value.
     *
     * @param float|null $roundingEnding Rounding ending value
     *
     * @return string|null Error message or null if valid
     */
    public static function validateRoundingEnding(?float $roundingEnding): ?string
    {
        if ($roundingEnding === null) {
            return null;
        }

        if ($roundingEnding < 0 || $roundingEnding >= 1) {
            return inwx_markups_lang(
                'error.invalidRoundingEnding',
                'Rounding ending must be between 0 and 0.99 (e.g., 0.99, 0.95, 0.90).'
            );
        }

        return null;
    }

    /**
     * Validate bulk save parameters.
     *
     * @return string|null Error message or null if valid
     */
    public static function validateBulkParams(int $currencyId, string $mode): ?string
    {
        if ($currencyId <= 0 || !in_array($mode, ['none', 'disable', 'fixed', 'markup'], true)) {
            return inwx_markups_lang(
                'error.bulk.selectCurrencyMode',
                'Bulk: Please select currency and mode.'
            );
        }

        return null;
    }
}
