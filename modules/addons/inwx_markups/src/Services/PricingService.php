<?php

namespace INWX\Markups\Services;

/**
 * PricingService.
 *
 * Business logic for pricing calculations.
 */
class PricingService
{
    /**
     * Round a price up to the next value ending with a given decimal fraction.
     *
     * @param float $price          Price after markup
     * @param float $roundingEnding Decimal ending (e.g., 0.99, 0.95, 0.90)
     *
     * @return float Rounded price
     */
    public static function roundToEnding(float $price, float $roundingEnding = 0.99): float
    {
        if ($price <= 0.0) {
            return 0.0;
        }

        $intPart = floor($price);
        $target = $intPart + $roundingEnding;

        if ($target < $price) {
            $target = ($intPart + 1) + $roundingEnding;
        }

        return round($target, 2);
    }

    /**
     * Calculate markup on a base price.
     *
     * @param float       $basePrice   Base price from registrar
     * @param string|null $markupType  'percent' or 'fixed'
     * @param float|null  $markupValue Markup value
     * @param float|null  $rounding    Rounding ending (optional)
     *
     * @return float Final price
     *
     * @throws \InvalidArgumentException If base price is negative
     */
    public static function calculateMarkup(
        float $basePrice,
        ?string $markupType,
        ?float $markupValue,
        ?float $rounding = null,
    ): float {
        if ($basePrice < 0) {
            error_log('INWX Markups: Negative base price detected: ' . $basePrice);
            throw new \InvalidArgumentException('Base price cannot be negative: ' . $basePrice);
        }

        $price = $basePrice;

        if ($markupType && $markupValue !== null) {
            if ($markupType === 'percent') {
                $price = $basePrice * (1 + ($markupValue / 100));
            } elseif ($markupType === 'fixed') {
                $price = $basePrice + $markupValue;
            }
        }

        // Validate result is not negative after markup calculation
        if ($price < 0) {
            error_log('INWX Markups: Negative price after markup calculation. Base: ' . $basePrice . ', Type: ' . $markupType . ', Value: ' . $markupValue . ', Result: ' . $price);
            throw new \InvalidArgumentException('Calculated price cannot be negative. Check markup configuration.');
        }

        if ($rounding !== null) {
            $price = self::roundToEnding($price, $rounding);
        }

        return round($price, 2);
    }
}
