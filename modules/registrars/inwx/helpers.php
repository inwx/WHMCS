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
    $supportedCustomRecordTypes = ['AFSDB', 'ALIAS', 'CAA', 'CERT', 'HINFO', 'KEY', 'LOC', 'NAPTR', 'OPENPGPKEY', 'PTR', 'RP', 'SMIMEA', 'SOA', 'SRV', 'SSHFP', 'TLSA', 'URI'];
    $recordTypes = ['A', 'AAAA', 'MX', 'CNAME', 'TXT', 'URL']; // Add default types

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
