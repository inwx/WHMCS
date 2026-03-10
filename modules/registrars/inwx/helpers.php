<?php

use Illuminate\Database\Capsule\Manager as DB;
use INWX\Domrobot;

include_once 'api/Domrobot.php';

function inwx_getModuleConfig(): array
{
    $encryptedConfigValues = DB::table('tblregistrars')
        ->where('registrar', '=', 'inwx')
        ->get(['setting', 'value'])->toArray();

    $configValues = [];
    foreach ($encryptedConfigValues as $encryptedValue) {
        $configValues[$encryptedValue->setting] = inwx_decryptString($encryptedValue->value);
    }

    return $configValues;
}

function inwx_decryptString(string $string): string
{
    $applicationConfig = DI::make('config');
    $cc_encryption_hash = $applicationConfig['cc_encryption_hash'];
    $key = md5(md5($cc_encryption_hash)) . md5($cc_encryption_hash);
    $hashKey = inwx_decryptString_hash($key);
    $hashLength = strlen($hashKey);
    $string = base64_decode($string);
    $temporaryIv = substr($string, 0, $hashLength);
    $string = substr($string, $hashLength);
    $iv = '';
    $output = '';
    for ($index = 0; $index < $hashLength; ++$index) {
        $ivValue = isset($temporaryIv[$index]) ? $temporaryIv[$index] : '';
        $hashValue = isset($hashKey[$index]) ? $hashKey[$index] : '';
        $iv .= chr(ord($ivValue) ^ ord($hashValue));
    }
    $key = $iv;
    for ($index = 0; $index < strlen($string); ++$index) {
        if ($index !== 0 && $index % $hashLength === 0) {
            $key = inwx_decryptString_hash($key . substr($output, $index - $hashLength, $hashLength));
        }
        $output .= chr(ord($key[$index % $hashLength]) ^ ord($string[$index]));
    }

    return $output;
}

function inwx_decryptString_hash($string)
{
    if (function_exists('sha1')) {
        $hash = sha1($string);
    } else {
        $hash = md5($string);
    }
    $output = '';
    $index = 0;
    while ($index < strlen($hash)) {
        $output .= chr(hexdec($hash[$index] . $hash[$index + 1]));
        $index += 2;
    }

    return $output;
}

function inwx_InjectOriginalDomain(array $params): array
{
    if (!isset($params['original'])) {
        $params['original'] = [];
    }

    if (!isset($params['original']['sld']) || empty(trim($params['original']['sld']))) {
        $params['original']['sld'] = $params['sld'];
    }

    if (!isset($params['original']['tld']) || empty(trim($params['original']['tld']))) {
        $params['original']['tld'] = $params['tld'];
    }

    return $params;
}

function inwx_GetApiResponseErrorMessage(array $response): string
{
    $msg = '';
    if (!is_array($response) || !isset($response['code'])) {
        $msg = 'Fatal API Error occurred!';
    } elseif ($response['code'] === 1000 || $response['code'] === 1001) {
        $msg = '';
    } elseif (isset($response['resData']['reason'])) {
        $msg = $response['resData']['reason'];
    } elseif (isset($response['reason'])) {
        $msg = $response['reason'];
    } elseif (isset($response['msg'])) {
        $msg = $response['msg'] . ' (EPP: ' . $response['code'] . ')';
    }
    return $msg;
}

function inwx_InjectCredentials(array $params, array $originalParameters = []): array
{
    return array_merge(['user' => $params['Username'], 'pass' => $params['Password']], $originalParameters);
}

function inwx_CreateDomrobot(array $params): Domrobot
{
    $domrobot = (new Domrobot($params['CookieFilePath']))->useJson();
    if ($params['TestMode']) {
        $domrobot->useOte();
    } else {
        $domrobot->useLive();
    }

    return $domrobot;
}

function inwx_GetEnabledRecordTypes(array $params): array
{
    $supportedCustomRecordTypes = ['AFSDB', 'ALIAS', 'CAA', 'CERT', 'HINFO', 'HTTPS', 'IPSECKEY', 'LOC', 'NAPTR', 'OPENPGPKEY', 'PTR', 'RP', 'SMIMEA', 'SOA', 'SRV', 'SSHFP', 'SVCB', 'TLSA', 'URI'];
    $recordTypes = ['A', 'AAAA', 'MX', 'CNAME', 'NS', 'TXT', 'URL']; // Add default types

    if ($params['EnableCustomRecordTypes'] === true || $params['EnableCustomRecordTypes'] === 'on') {
        $enabledCustomRecordTypes = inwx_ParseCustomRecordTypes($params);

        // Check for only supported custom record types
        foreach ($enabledCustomRecordTypes as $enabledCustomRecordType) {
            if (!in_array($enabledCustomRecordType, $supportedCustomRecordTypes, true)) {
                throw new Exception('Unsupported record type: ' . $enabledCustomRecordType);
            }
        }

        $recordTypes = array_merge($recordTypes, $enabledCustomRecordTypes);
    }
    sort($recordTypes);

    return $recordTypes;
}

function inwx_ParseCustomRecordTypes(array $params): array
{
    return explode(',', preg_replace('/\s/', '', preg_replace('/[,;]/', ',', trim($params['CustomRecordTypes']))));
}

function inwx_IncludeAdditionalDomainFields()
{
    global $additionaldomainfields;
    include implode(DIRECTORY_SEPARATOR, [ROOTDIR, 'resources', 'domains', 'additionalfields.php']);
}

// --- Rate Limiting Helpers ---

function inwx_EnsureRateLimitTable(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $schema = DB::schema();
    if (!$schema->hasTable('mod_inwx_ratelimit')) {
        $schema->create('mod_inwx_ratelimit', function ($table) {
            $table->increments('id');
            $table->string('ip_address', 45)->unique();
            $table->integer('request_count')->default(0);
            $table->timestamp('window_start')->useCurrent();
        });
    }

    $checked = true;
}

function inwx_GetClientIp(): string
{
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        return $_SERVER['REMOTE_ADDR'];
    }

    return 'unknown';
}

function inwx_IsAdminContext(): bool
{
    if (isset($_SESSION['adminid']) && is_numeric($_SESSION['adminid']) && (int)$_SESSION['adminid'] > 0) {
        return true;
    }

    return false;
}

function inwx_ParseWhitelist(string $raw): array
{
    $entries = [];
    $lines = array_filter(array_map('trim', explode("\n", $raw)));

    foreach ($lines as $line) {
        $parts = explode('|', $line);
        $ip = trim($parts[0]);
        if (empty($ip)) {
            continue;
        }

        $entry = ['ip' => $ip, 'max_requests' => null, 'window_seconds' => null];

        if (count($parts) >= 3) {
            $max = (int)trim($parts[1]);
            $window = (int)trim($parts[2]);
            if ($max > 0 && $window > 0) {
                $entry['max_requests'] = $max;
                $entry['window_seconds'] = $window;
            }
        }

        $entries[$ip] = $entry;
    }

    return $entries;
}

function inwx_CheckRateLimit(array $params): bool
{
    $rateLimitEnabled = ($params['RateLimitEnabled'] ?? '') === 'on';
    if (!$rateLimitEnabled) {
        return true;
    }

    // Admin users bypass rate limiting entirely
    if (inwx_IsAdminContext()) {
        return true;
    }

    $ip = inwx_GetClientIp();
    if ($ip === 'unknown') {
        return true;
    }

    $maxRequests = max(1, (int)($params['RateLimitMaxRequests'] ?? 30));
    $windowSeconds = max(1, (int)($params['RateLimitWindowSeconds'] ?? 60));

    // Check whitelist
    $whitelist = inwx_ParseWhitelist($params['RateLimitWhitelist'] ?? '');
    if (isset($whitelist[$ip])) {
        $entry = $whitelist[$ip];
        // Whitelisted with no custom limits = bypass entirely
        if ($entry['max_requests'] === null) {
            return true;
        }
        // Whitelisted with custom limits = apply those instead
        $maxRequests = $entry['max_requests'];
        $windowSeconds = $entry['window_seconds'];
    }

    inwx_EnsureRateLimitTable();

    $now = time();
    $table = DB::table('mod_inwx_ratelimit');
    $row = $table->where('ip_address', $ip)->first();

    if (!$row) {
        DB::table('mod_inwx_ratelimit')->insert([
            'ip_address' => $ip,
            'request_count' => 1,
            'window_start' => date('Y-m-d H:i:s', $now),
        ]);
        return true;
    }

    $windowStart = strtotime($row->window_start);

    // Window expired — reset
    if (($now - $windowStart) >= $windowSeconds) {
        DB::table('mod_inwx_ratelimit')
            ->where('ip_address', $ip)
            ->update([
                'request_count' => 1,
                'window_start' => date('Y-m-d H:i:s', $now),
            ]);
        return true;
    }

    // Within window — check count
    if ($row->request_count < $maxRequests) {
        DB::table('mod_inwx_ratelimit')
            ->where('ip_address', $ip)
            ->increment('request_count');
        return true;
    }

    // Rate limit exceeded
    return false;
}

function inwx_ValidateSld(string $sld): bool
{
    if (empty($sld) || strlen($sld) > 63) {
        return false;
    }

    return (bool)preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/i', $sld);
}

function inwx_ValidateTlds(array $tlds): bool
{
    if (empty($tlds) || count($tlds) > 20) {
        return false;
    }

    foreach ($tlds as $tld) {
        if (!preg_match('/^\.[a-z0-9][a-z0-9.-]{0,62}$/i', $tld)) {
            return false;
        }
    }

    return true;
}

function inwx_CleanupRateLimitTable(): void
{
    // Run cleanup probabilistically (1 in 100 requests)
    if (mt_rand(1, 100) !== 1) {
        return;
    }

    try {
        DB::table('mod_inwx_ratelimit')
            ->where('window_start', '<', date('Y-m-d H:i:s', time() - 3600))
            ->delete();
    } catch (\Exception $e) {
        // Silently ignore cleanup errors
    }
}
