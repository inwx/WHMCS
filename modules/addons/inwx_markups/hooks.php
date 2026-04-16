<?php

use INWX\Markups\Services\NotificationService;
use INWX\Markups\Services\RegistrarService;
use WHMCS\Database\Capsule;

/*
 * Track pricing changes when WHMCS imports TLD pricing from registrar.
 * This hook fires after the pricing import process completes.
 */
add_hook('AfterRegistrarImportDomainPricing', 1, function (array $vars) {
    $registrar = $vars['registrar'] ?? '';

    // Only process for inwx registrar
    if ($registrar !== 'inwx') {
        return;
    }

    // Get imported pricing data
    $importResults = $vars['results'] ?? [];
    $currencyCode = $vars['currency'] ?? 'EUR';

    if (empty($importResults)) {
        return;
    }

    $priceChanges = [];
    $hasChanges = false;

    foreach ($importResults as $result) {
        if ($result['status'] !== 'updated' && $result['status'] !== 'created') {
            continue;
        }

        $hasChanges = true;
        $tld = $result['extension'] ?? '';
        $status = $result['status'] ?? '';

        // Collect price changes for table format
        if (isset($result['pricing'])) {
            $pricing = $result['pricing'];
            $oldPricing = $result['old_pricing'] ?? [];

            // Registration
            if (isset($pricing['register'])) {
                $oldPrice = $oldPricing['register'] ?? null;
                $newPrice = $pricing['register'];
                $variation = $oldPrice !== null ? ($newPrice - $oldPrice) : null;

                $priceChanges[] = [
                    'tld' => $tld,
                    'action' => 'Registration',
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'variation' => $variation,
                    'is_new' => $status === 'created',
                ];
            }

            // Renewal
            if (isset($pricing['renew'])) {
                $oldPrice = $oldPricing['renew'] ?? null;
                $newPrice = $pricing['renew'];
                $variation = $oldPrice !== null ? ($newPrice - $oldPrice) : null;

                $priceChanges[] = [
                    'tld' => $tld,
                    'action' => 'Renewal',
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'variation' => $variation,
                    'is_new' => $status === 'created',
                ];
            }

            // Transfer
            if (isset($pricing['transfer'])) {
                $oldPrice = $oldPricing['transfer'] ?? null;
                $newPrice = $pricing['transfer'];
                $variation = $oldPrice !== null ? ($newPrice - $oldPrice) : null;

                $priceChanges[] = [
                    'tld' => $tld,
                    'action' => 'Transfer',
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'variation' => $variation,
                    'is_new' => $status === 'created',
                ];
            }
        }
    }

    if (!$hasChanges) {
        return;
    }

    // Build email body
    $body = "INWX · TLD Price Update Report\n\n";
    $body .= "You are receiving this message because the INWX WHMCS Registrar Module is configured to send periodic pricing change reports.\n\n";
    $body .= "Below you will find the latest detected wholesale price adjustments for the listed Top-Level Domains (TLDs), including previous pricing, updated pricing, and the corresponding variation.\n\n";
    $body .= "Currency: {$currencyCode}\n\n\n";
    $body .= "TLD Price Changes\n";
    $body .= "---------------------------------------------------------------------\n\n";
    $body .= sprintf("%-15s | %-13s | %-14s | %-13s | %-11s\n", 'TLD', 'Action', 'Previous Price', 'Updated Price', 'Variation');
    $body .= sprintf("%-15s|%-14s|%-15s|%-14s|%-11s\n", str_repeat('-', 15), str_repeat('-', 14), str_repeat('-', 15), str_repeat('-', 14), str_repeat('-', 11));

    foreach ($priceChanges as $change) {
        $oldPriceStr = $change['is_new'] || $change['old_price'] === null ? 'NEW' : number_format($change['old_price'], 2);
        $newPriceStr = number_format($change['new_price'], 2);

        if ($change['variation'] !== null && $change['variation'] != 0) {
            $variationStr = ($change['variation'] > 0 ? '+' : '') . number_format($change['variation'], 2);
        } else {
            $variationStr = $change['is_new'] ? 'NEW' : '0.00';
        }

        $body .= sprintf("%-15s | %-13s | %14s | %13s | %11s\n",
            $change['tld'],
            $change['action'],
            $oldPriceStr,
            $newPriceStr,
            $variationStr
        );
    }

    $body .= "\n\n---------------------------------------------------------------------\n\n";
    $body .= "Prices shown reflect wholesale registry pricing. Final retail prices may vary depending on your pricing rules, taxes, and margin configuration.\n\n";

    // Check for TLDs that exist at registrar but not in WHMCS
    try {
        $currency = Capsule::table('tblcurrencies')->where('code', $currencyCode)->first();
        if ($currency && isset($currency->id)) {
            $missingTlds = RegistrarService::findMissingTlds($registrar, $currency->id);
            if (!empty($missingTlds)) {
                $body .= "\nTLDs available at registrar but not yet configured in WHMCS:\n";
                foreach ($missingTlds as $missingTld) {
                    $body .= "  - {$missingTld}\n";
                }
                $body .= "\nNote: These TLDs need to be manually added to WHMCS Domain Pricing.\n\n";
            }
        }
    } catch (Throwable $e) {
        logActivity('INWX Markups: Failed to check for missing TLDs: ' . $e->getMessage());
    }

    $body .= "Preferences:\n";
    $body .= "If you no longer wish to receive these email reports, you can disable them in the INWX WHMCS Registrar Module settings (Pricing Updates / Email Reports).\n\n";
    $body .= "INWX\n";
    $body .= 'Time: ' . date('Y-m-d H:i:s T');

    $subject = 'INWX WHMCS: TLD Wholesale Price Update';
    NotificationService::sendAdminEmail($subject, $body);
});

/*
 * Track manual pricing changes made in WHMCS admin.
 * This hook fires after domain pricing is updated in the admin area.
 */
add_hook('DomainPricingUpdate', 1, function (array $vars) {
    $extension = $vars['extension'] ?? '';
    $changes = $vars['changes'] ?? [];

    if (empty($changes)) {
        return;
    }

    $lines = [];

    foreach ($changes as $currencyCode => $currencyChanges) {
        $parts = [];

        foreach ($currencyChanges as $type => $priceData) {
            // Map internal type names to readable labels
            $typeLabels = [
                'register' => 'Register',
                'renew' => 'Renew',
                'transfer' => 'Transfer',
                'redemption' => 'Redemption',
                'grace' => 'Grace',
            ];

            $label = $typeLabels[$type] ?? ucfirst($type);

            // Check for price changes across different year periods
            foreach ($priceData as $years => $prices) {
                if (isset($prices['old']) && isset($prices['new'])) {
                    $old = (float) $prices['old'];
                    $new = (float) $prices['new'];

                    if (abs($old - $new) > 0.0001) {
                        $yearLabel = $years == 1 ? 'yr1' : "{$years}yrs";
                        $parts[] = "{$label}({$yearLabel}): {$old} -> {$new}";
                    }
                }
            }
        }

        if (!empty($parts)) {
            $lines[] = "{$extension} [{$currencyCode}]: " . implode(', ', $parts);
        }
    }

    if (!empty($lines)) {
        $subject = 'TLD pricing overview (manual save)';
        $body = "Overview of TLD pricing changes:\n\n" . implode("\n", $lines) . "\n\nTime: " . date('c');
        NotificationService::sendAdminEmail($subject, $body);
    }
});
