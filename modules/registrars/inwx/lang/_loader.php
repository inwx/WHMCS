<?php

/**
 * Shared language file loader with checksum validation.
 *
 * Loads translation strings from a JSON envelope file into $_LANG.
 * Validates the SHA-256 checksum of the data payload to detect tampering
 * or corruption.
 *
 * @param string $language Language identifier (e.g. 'english', 'german')
 */
function inwx_loadLanguage(string $language): void
{
    global $_LANG;

    $jsonPath = __DIR__ . '/' . $language . '.json';

    if (!file_exists($jsonPath)) {
        throw new RuntimeException("Language file not found: $jsonPath");
    }

    $envelope = json_decode(file_get_contents($jsonPath), true);

    if (!is_array($envelope) || !isset($envelope['data']) || !isset($envelope['checksum'])) {
        throw new RuntimeException("Invalid language envelope: $jsonPath");
    }

    $canonical = json_encode($envelope['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $computed = 'sha256:' . hash('sha256', $canonical);

    if (!hash_equals($computed, $envelope['checksum'])) {
        throw new RuntimeException("Checksum mismatch in $jsonPath (expected $computed, got {$envelope['checksum']})");
    }

    foreach ($envelope['data'] as $key => $value) {
        $_LANG[$key] = $value;
    }
}
