<?php

$jsonPath = __DIR__ . '/additionaldomainfields.json';
$envelope = json_decode(file_get_contents($jsonPath), true);

if (!is_array($envelope) || !isset($envelope['data']) || !isset($envelope['checksum'])) {
    throw new RuntimeException("Invalid or missing JSON: $jsonPath");
}

$canonical = json_encode($envelope['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$computed = 'sha256:' . hash('sha256', $canonical);

if (!hash_equals($computed, $envelope['checksum'])) {
    throw new RuntimeException("Checksum mismatch in $jsonPath (expected $computed, got {$envelope['checksum']})");
}

foreach ($envelope['data'] as $tld => $fields) {
    if (count($fields) === 1 && isset($fields[0]['$ref'])) {
        continue;
    }
    $additionaldomainfields[".$tld"] = $fields;
}

foreach ($envelope['data'] as $tld => $fields) {
    if (count($fields) === 1 && isset($fields[0]['$ref'])) {
        $parentTld = str_replace('#/data/', '', $fields[0]['$ref']);
        if (isset($additionaldomainfields[".$parentTld"])) {
            $additionaldomainfields[".$tld"] = $additionaldomainfields[".$parentTld"];
        }
    }
}
