<?php

namespace INWX\Markups\Services;

use WHMCS\Database\Capsule;

/**
 * RegistrarService.
 *
 * Handles registrar-related operations.
 */
class RegistrarService
{
    /**
     * Find TLDs that exist at registrar but not in WHMCS.
     *
     * @param string   $registrar  Registrar name (e.g. 'inwx')
     * @param int|null $currencyId Currency ID (optional, for filtering)
     *
     * @return array List of TLD extensions (with leading dot)
     */
    public static function findMissingTlds(string $registrar, ?int $currencyId = null): array
    {
        try {
            // Get all TLDs that exist in WHMCS for this registrar
            $whmcsTlds = Capsule::table('tbldomainpricing')
                ->where('autoreg', $registrar)
                ->pluck('extension')
                ->toArray();

            $whmcsTldsMap = array_flip(array_map('strtolower', $whmcsTlds));

            // Get TLDs from registrar by calling GetTldPricing
            $registrarConfig = self::getRegistrarConfig($registrar);
            if (empty($registrarConfig)) {
                error_log('INWX Markups: No registrar configuration found for ' . $registrar);
                return [];
            }

            // Decrypt config values
            $config = self::decryptConfig($registrarConfig);
            if (empty($config)) {
                error_log('INWX Markups: Failed to decrypt registrar configuration for ' . $registrar);
                return [];
            }

            // Call GetTldPricing to get TLDs from registrar
            $pricingResult = self::getTldPricing($registrar, $config);
            if (!$pricingResult) {
                error_log('INWX Markups: Failed to get TLD pricing from registrar ' . $registrar);
                return [];
            }

            $registrarTlds = self::extractTldsFromResult($pricingResult);
            if (empty($registrarTlds)) {
                error_log('INWX Markups: No TLDs extracted from registrar pricing result for ' . $registrar);
                return [];
            }

            // Find TLDs that exist at registrar but not in WHMCS
            $missingTlds = [];
            foreach ($registrarTlds as $tld) {
                if (!isset($whmcsTldsMap[$tld])) {
                    $missingTlds[] = $tld;
                }
            }

            sort($missingTlds);
            return $missingTlds;
        } catch (\Throwable $e) {
            // Log the error with detailed information
            error_log('INWX Markups: Exception in findMissingTlds for registrar ' . $registrar . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return [];
        }
    }

    /**
     * Get registrar configuration from database.
     */
    private static function getRegistrarConfig(string $registrar): array
    {
        return Capsule::table('tblregistrars')
            ->where('registrar', $registrar)
            ->get(['setting', 'value'])
            ->mapWithKeys(function ($item) {
                return [$item->setting => $item->value];
            })
            ->toArray();
    }

    /**
     * Decrypt registrar configuration values.
     */
    private static function decryptConfig(array $registrarConfig): array
    {
        // Load registrar helpers if not already loaded
        $helpersPath = ROOTDIR . '/modules/registrars/inwx/helpers.php';
        if (file_exists($helpersPath)) {
            require_once $helpersPath;
        }

        $config = [];
        foreach ($registrarConfig as $key => $encryptedValue) {
            // Check if function exists before calling
            if (function_exists('inwx_decryptString')) {
                $config[$key] = inwx_decryptString($encryptedValue);
            } else {
                // Fallback to WHMCS decrypt if inwx_decryptString not available
                $config[$key] = decrypt($encryptedValue);
            }
        }

        return $config;
    }

    /**
     * Get TLD pricing from registrar.
     */
    private static function getTldPricing(string $registrar, array $config)
    {
        // Load registrar module if not already loaded
        $registrarPath = ROOTDIR . '/modules/registrars/inwx/inwx.php';
        if (!file_exists($registrarPath)) {
            return null;
        }

        require_once $registrarPath;

        // Check if function exists
        if (!function_exists('inwx_GetTldPricing')) {
            return null;
        }

        $pricingResult = inwx_GetTldPricing($config);

        if (is_array($pricingResult) && isset($pricingResult['error'])) {
            return null;
        }

        return $pricingResult;
    }

    /**
     * Extract TLDs from pricing result.
     */
    private static function extractTldsFromResult($pricingResult): array
    {
        $registrarTlds = [];

        // ResultsList is iterable, iterate over it
        if (is_object($pricingResult)) {
            foreach ($pricingResult as $item) {
                if (is_object($item) && method_exists($item, 'getExtension')) {
                    $tld = strtolower($item->getExtension());
                    if ($tld) {
                        $registrarTlds[] = $tld;
                    }
                }
            }
        } elseif (is_array($pricingResult)) {
            foreach ($pricingResult as $item) {
                if (is_object($item) && method_exists($item, 'getExtension')) {
                    $tld = strtolower($item->getExtension());
                    if ($tld) {
                        $registrarTlds[] = $tld;
                    }
                } elseif (isset($item['extension'])) {
                    $tld = strtolower($item['extension']);
                    if ($tld) {
                        $registrarTlds[] = $tld;
                    }
                }
            }
        }

        return $registrarTlds;
    }
}
