<?php

use WHMCS\Domain\Registrar\Domain;
use WHMCS\Domain\TopLevel\ImportItem;
use WHMCS\Domains\DomainLookup\ResultsList;
use WHMCS\Domains\DomainLookup\SearchResult;
use WHMCS\Carbon;
use WHMCS\Database\Capsule;

include_once 'helpers.php';

class Obfuscated implements Stringable
{
    protected string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

function inwx_RequestDelete(array $params)
{
    $params = inwx_InjectOriginalDomain($params);

    $domrobot = inwx_CreateDomrobot($params);
    $response = $domrobot->call('domain', 'delete', inwx_InjectCredentials($params, ['domain' => $params['original']['sld'] . '.' . $params['original']['tld']]));
    if ($response['code'] !== 1000) {
        return ['error' => inwx_GetApiResponseErrorMessage($response)];
    }

    return true;
}

function inwx_Sync(array $params): array
{
    $params = inwx_InjectOriginalDomain($params);
    $values = [];

    $domrobot = inwx_CreateDomrobot($params);
    $response = $domrobot->call('domain', 'info', inwx_InjectCredentials($params, ['domain' => $params['original']['sld'] . '.' . $params['original']['tld']]));
    if ($response['code'] === 1000 && isset($response['resData']['domain'])) {
        $exDate = (isset($response['resData']['exDate']) ? date('Y-m-d', $response['resData']['exDate']['timestamp']) : null);

        // set expiration date if available
        if (!is_null($exDate)) {
            $values['expirydate'] = $exDate;
        }

        // change domain-status if domain is active
        if ($response['resData']['status'] === 'OK') {
            $values['active'] = true;
        }

        // change expire-status if domain is expired
        if (!is_null($exDate) && time() >= strtotime($exDate)) {
            $values['expired'] = true;
            unset($values['active']);
        }
    }
    return $values;
}

function inwx_TransferSync(array $params): array
{
    $params = inwx_InjectOriginalDomain($params);
    $values = ['failed' => false];

    $domrobot = inwx_CreateDomrobot($params);
    $response = $domrobot->call('domain', 'info', inwx_InjectCredentials($params, [
        'domain' => $params['original']['sld'] . '.' . $params['original']['tld']
    ]));

    if ($response['code'] === 1000 && isset($response['resData']['status'])) {
        $exDate = (isset($response['resData']['exDate']) ? date('Y-m-d', $response['resData']['exDate']['timestamp']) : null);

        // set expiration date if available
        if (!is_null($exDate)) {
            $values['expirydate'] = $exDate;
        }

        return array_merge($values, ['completed' => $response['resData']['status'] === "OK"]);
    }

    if ($response['code'] === 2303) {
        return array_merge($values, ['failed' => true]);
    }

    return ['error' => inwx_GetApiResponseErrorMessage($response)];
}

function inwx_getConfigArray(): array
{
    return [
        'Username' => [
            'Type' => 'text',
            'Size' => '20',
            'Description' => 'Enter your INWX username here',
        ],
        'Password' => [
            'Type' => 'password',
            'Size' => '20',
            'Description' => 'Enter your INWX password here',
        ],
        'TestMode' => [
            'Type' => 'yesno',
            'Description' => 'Connect to OTE (Test Environment). Your credentials may differ.',
        ],
        'TechHandle' => [
            'Type' => 'text',
            'Description' => 'Enter your default contact handle id for tech contact. .DE domains require a fax number for the tech contact. Since WHMCS does not provide a field for this, you can manually create a contact with a fax number in the INWX webinterface, and specify the handle here. (You can use our default Tech/Billing contact handle: 1).',
        ],
        'BillingHandle' => [
            'Type' => 'text',
            'Description' => 'Enter your default contact handle id for billing contact. .DE domains require a fax number for the billing contact. Since WHMCS does not provide a field for this, you can manually create a contact with a fax number in the INWX webinterface, and specify the handle here. (You can use our default Tech/Billing contact handle: 1).',
        ],
        'EnableCustomRecordTypes' => [
            'Type' => 'yesno',
            'Description' => 'Enable DNS record types not natively supported by WHMCS but offered by INWX.',
        ],
        'CustomRecordTypes' => [
            'Type' => 'text',
            'Default' => 'AFSDB,ALIAS,CAA,CERT,HINFO,KEY,LOC,NAPTR,OPENPGPKEY,PTR,RP,SMIMEA,SOA,SRV,SSHFP,TLSA,URI',
            'Description' => 'The custom record types to enable. This must be a comma separated list. Allowed record types: AFSDB, ALIAS, CAA, CERT, HINFO, KEY, LOC, NAPTR, OPENPGPKEY, PTR, RP, SMIMEA, SOA, SRV, SSHFP, TLSA, URI',
        ],
        'UseShortRecordForm' => [
            'Type' => 'yesno',
            'Description' => 'Whether the domain.tld of records should be omitted (Example: "test.example.com" becomes "test"; "example.com" becomes "@").',
        ],
        'CookieFilePath' => [
            'Type' => 'text',
            'Default' => '/tmp/inwx_whmcs_cookiefile',
            'Description' => 'Place where the cookie file for API requests should reside. This file can be lost at any time with no problems, it is only necessary for sessions between API calls and will be regenerated if it was deleted.',
        ],
    ];
}

function inwx_GetRegistrarLock(array $params)
{
    $params = inwx_InjectOriginalDomain($params);
    $domrobot = inwx_CreateDomrobot($params);

    $pDomain['domain'] = $params['original']['sld'] . '.' . $params['original']['tld'];
    $pDomain['wide'] = 1;

    $response = $domrobot->call('domain', 'info', inwx_InjectCredentials($params, $pDomain));

    if ($response['code'] === 1000 && isset($response['resData']['transferLock'])) {
        if ($response['resData']['transferLock'] === true) {
            $lockstatus = 'locked';
        } elseif ($response['resData']['transferLock'] === false) {
            $lockstatus = 'unlocked';
        } else {
            $lockstatus = '';
        }
        return $lockstatus;
    }

    return ['error' => inwx_GetApiResponseErrorMessage($response)];
}

function inwx_SaveRegistrarLock(array $params): array
{
    $params = inwx_InjectOriginalDomain($params);
    $values = ['error' => ''];
    $domrobot = inwx_CreateDomrobot($params);

    $pDomain['domain'] = $params['original']['sld'] . '.' . $params['original']['tld'];
    $pDomain['transferLock'] = ($params['lockenabled'] === 'locked') ? 1 : 0;

    $response = $domrobot->call('domain', 'update', inwx_InjectCredentials($params, $pDomain));
    $values['error'] = inwx_GetApiResponseErrorMessage($response);
    return $values;
}

function inwx_GetEPPCode(array $params): array
{
    $params = inwx_InjectOriginalDomain($params);
    $values = ['error' => ''];
    $domrobot = inwx_CreateDomrobot($params);

    $pDomain['domain'] = $params['original']['sld'] . '.' . $params['original']['tld'];
    $pDomain['wide'] = 1;

    $response = $domrobot->call('domain', 'info', inwx_InjectCredentials($params, $pDomain));

    if ($response['code'] === 1000) {
        if (isset($response['resData']['authCode'])) {
            $values['eppcode'] = $response['resData']['authCode'];
        } else {
            $values['eppcode'] = '';
        }
    } else {
        $values['error'] = inwx_GetApiResponseErrorMessage($response);
    }
    return $values;
}

function inwx_GetNameservers(array $params): array
{
    $params = inwx_InjectOriginalDomain($params);
    $values = [];
    $domrobot = inwx_CreateDomrobot($params);

    $pDomain['domain'] = $params['original']['sld'] . '.' . $params['original']['tld'];
    $pDomain['wide'] = 1;

    $response = $domrobot->call('domain', 'info', inwx_InjectCredentials($params, $pDomain));
    if ($response['code'] === 1000 && isset($response['resData']['ns'])) {
        for ($i = 1; $i <= 4; ++$i) {
            $values['ns' . $i] = (isset($response['resData']['ns'][($i - 1)])) ? $response['resData']['ns'][($i - 1)] : '';
        }
    } else {
        $values['error'] = inwx_GetApiResponseErrorMessage($response);
    }

    return $values;
}

function inwx_SaveNameservers(array $params): array
{
    $params = inwx_InjectOriginalDomain($params);
    $values = ['error' => ''];
    $domrobot = inwx_CreateDomrobot($params);

    $pDomain['domain'] = $params['original']['sld'] . '.' . $params['original']['tld'];
    $pDomain['ns'] = [];
    for ($i = 1; $i <= 4; ++$i) {
        if (isset($params['ns' . $i]) && !empty($params['ns' . $i])) {
            $pDomain['ns'][] = $params['ns' . $i];
        }
    }

    $response = $domrobot->call('domain', 'update', inwx_InjectCredentials($params, $pDomain));
    $values['error'] = inwx_GetApiResponseErrorMessage($response);

    return $values;
}

function inwx_GetDNS(array $params): array
{
    $params = inwx_InjectOriginalDomain($params);
    $values = ['error' => ''];
    $hostrecords = [];
    $domrobot = inwx_CreateDomrobot($params);

    $pInfo['domain'] = $params['original']['sld'] . '.' . $params['original']['tld'];
    $response = $domrobot->call('nameserver', 'info', inwx_InjectCredentials($params, $pInfo));

    if ($response['code'] === 1000 && isset($response['resData']['record']) && count($response['resData']['record']) > 0) {
        $_allowedRecTypes = inwx_GetEnabledRecordTypes($params);
        foreach ($response['resData']['record'] as $_record) {
            if (in_array($_record['type'], $_allowedRecTypes, true)) {
                if ($_record['type'] === 'URL') {
                    $_record['type'] = (isset($_record['urlRedirectType']) && $_record['urlRedirectType'] === 'FRAME') ? 'FRAME' : 'URL';
                }
                $hostname = $_record['name'];
                if ($params['UseShortRecordForm']) {
                    if ($hostname === $pInfo['domain']) {
                        $hostname = '@';
                    } else {
                        $hostname = preg_replace('/.' . $pInfo['domain'] . '$/', '', $hostname);
                    }
                }
                $hostrecords[] = ['hostname' => $hostname, 'type' => $_record['type'], 'address' => $_record['content'], 'priority' => $_record['prio']];
            }
        }
    } else {
        $values['error'] = inwx_GetApiResponseErrorMessage($response);
    }

    return $hostrecords;
}

function inwx_SaveDNS(array $params): array
{
    $params = inwx_InjectOriginalDomain($params);
    $values = ['error' => ''];
    $domrobot = inwx_CreateDomrobot($params);

    $user = new Obfuscated($params['Username']);
    $pass = new Obfuscated($params['Password']);

    $domrobot->login($user, $pass);

    $pInfo['domain'] = $params['original']['sld'] . '.' . $params['original']['tld'];
    $response = $domrobot->call('nameserver', 'info', $pInfo);
    $_records = [];
    if ($response['code'] === 1000 && isset($response['resData']['record']) && count($response['resData']['record']) > 0) {
        $_allowedRecTypes = inwx_GetEnabledRecordTypes($params);
        foreach ($response['resData']['record'] as $_record) {
            if (in_array($_record['type'], $_allowedRecTypes, true)) {
                $_records[] = ['id' => $_record['id']];
            }
        }
    } elseif ($response['code'] !== 1000) {
        $values['error'] = inwx_GetApiResponseErrorMessage($response);
        return $values;
    }

    // Loop through the submitted records
    foreach ($params['dnsrecords'] as $key => $val) {
        $pRecord = [];
        $pRecord['id'] = (isset($_records[$key]['id'])) ? $_records[$key]['id'] : null;

        if (empty($val['address'])) {
            if (!empty($pRecord['id']) && $pRecord['id'] >= 1) {
                // Delete record when address is empty and record has an id at inwx
                $response = $domrobot->call('nameserver', 'deleteRecord', ['id' => $pRecord['id']]);
                if ($response['code'] !== 1000) {
                    $values['error'] = inwx_GetApiResponseErrorMessage($response);
                    return $values;
                }
            }
            continue;
        }

        if ($params['UseShortRecordForm']) {
            if ($val['hostname'] === '@') {
                $pRecord['name'] = $pInfo['domain'];
            } else {
                $pRecord['name'] = $val['hostname'] . '.' . $pInfo['domain'];
            }
        } else {
            $pRecord['name'] = $val['hostname'];
        }
        $pRecord['type'] = $val['type'];
        $pRecord['content'] = $val['address'];
        if ($val['priority'] !== 'N/A' && is_numeric($val['priority'])) {
            $pRecord['prio'] = $val['priority'];
        }
        if ($pRecord['type'] === 'URL') {
            $pRecord['urlRedirectType'] = 'HEADER301';
        } elseif ($pRecord['type'] === 'FRAME') {
            $pRecord['type'] = 'URL';
            $pRecord['urlRedirectType'] = 'FRAME';
            $pRecord['urlRedirectTitle'] = '';
        }

        if (empty($pRecord['id']) || $pRecord['id'] < 1) {
            unset($pRecord['id']);
            $pRecord['domain'] = $params['original']['sld'] . '.' . $params['original']['tld'];
            $response = $domrobot->call('nameserver', 'createrecord', $pRecord);
        } else {
            $response = $domrobot->call('nameserver', 'updaterecord', $pRecord);
        }
        $values['error'] = (empty($values['error'])) ? inwx_GetApiResponseErrorMessage($response) : $values['error'];
    }

    return $values;
}

function inwx_GetContactDetails(array $params): array
{
    $params = inwx_InjectOriginalDomain($params);
    $values = [];
    $domrobot = inwx_CreateDomrobot($params);

    $pDomain['domain'] = $params['original']['sld'] . '.' . $params['original']['tld'];
    $pDomain['wide'] = 2;

    $response = $domrobot->call('domain', 'info', inwx_InjectCredentials($params, $pDomain));
    $contactTypes = ['registrant' => 'Registrant', 'admin' => 'Admin', 'tech' => 'Technical', 'billing' => 'Billing'];
    if ($response['code'] === 1000) {
        // Data should be returned in an array as follows
        foreach ($contactTypes as $type => $typeName) {
            // $values[$typeName]["Id"] = $response['resData']['contact'][$type]['id'];
            $values[$typeName]['First Name'] = '';
            $values[$typeName]['Last Name'] = '';
            $nameArr = explode(' ', $response['resData']['contact'][$type]['name']);
            for ($i = 0; $i < count($nameArr) - 1; ++$i) {
                $values[$typeName]['First Name'] .= $nameArr[$i] . ' ';
            }
            trim($values[$typeName]['First Name']);
            $values[$typeName]['Last Name'] = $nameArr[count($nameArr) - 1];
            $values[$typeName]['Company'] = $response['resData']['contact'][$type]['org'];
            $values[$typeName]['Street'] = $response['resData']['contact'][$type]['street'];
            $values[$typeName]['City'] = $response['resData']['contact'][$type]['city'];
            $values[$typeName]['Post Code'] = $response['resData']['contact'][$type]['pc'];
            $values[$typeName]['Country Code'] = $response['resData']['contact'][$type]['cc'];
            $values[$typeName]['State'] = $response['resData']['contact'][$type]['sp'];
            $values[$typeName]['Phone Number'] = $response['resData']['contact'][$type]['voice'];
            $values[$typeName]['Fax Number'] = $response['resData']['contact'][$type]['fax'];
            $values[$typeName]['Email'] = $response['resData']['contact'][$type]['email'];
            $values[$typeName]['Notes'] = $response['resData']['contact'][$type]['remarks'];
        }
    }

    return $values;
}

function inwx_SaveContactDetails(array $params): array
{
    $params = inwx_InjectOriginalDomain($params);
    $values = [];
    $domrobot = inwx_CreateDomrobot($params);

    $user = new Obfuscated($params['Username']);
    $pass = new Obfuscated($params['Password']);

    $domrobot->login($user, $pass);

    $pDomain['domain'] = $params['original']['sld'] . '.' . $params['original']['tld'];
    $response = $domrobot->call('domain', 'info', $pDomain);
    if ($response['code'] === 1000) {
        $contactIds = ['registrant' => $response['resData']['registrant'], 'admin' => $response['resData']['admin'], 'tech' => $response['resData']['tech'], 'billing' => $response['resData']['billing']];
        $countContactIds = array_count_values([$response['resData']['registrant'], $response['resData']['admin'], $response['resData']['tech'], $response['resData']['billing']]);
        $contactTypes = ['registrant' => 'Registrant', 'admin' => 'Admin', 'tech' => 'Technical', 'billing' => 'Billing'];
        // Data is returned as specified in the GetContactDetails() function
        foreach ($contactTypes as $type => $typeName) {
            $pContact = [];
            $pContact['name'] = $params['contactdetails'][$typeName]['First Name'];
            $pContact['name'] .= ' ' . $params['contactdetails'][$typeName]['Last Name'];
            $pContact['org'] = $params['contactdetails'][$typeName]['Company'];
            $pContact['street'] = $params['contactdetails'][$typeName]['Street'];
            $pContact['city'] = $params['contactdetails'][$typeName]['City'];
            $pContact['pc'] = $params['contactdetails'][$typeName]['Post Code'];
            $pContact['sp'] = $params['contactdetails'][$typeName]['State'];
            $pContact['cc'] = strtoupper($params['contactdetails'][$typeName]['Country Code']);
            $pContact['voice'] = $params['contactdetails'][$typeName]['Phone Number'];
            $pContact['fax'] = $params['contactdetails'][$typeName]['Fax Number'];
            $pContact['email'] = $params['contactdetails'][$typeName]['Email'];
            $pContact['remarks'] = $params['contactdetails'][$typeName]['Notes'];
            $pContact['extData'] = ['PARSE-VOICE' => true, 'PARSE-FAX' => true];

            if ($countContactIds[$contactIds[$type]] > 1) {
                // create contact
                $pContact['type'] = 'PERSON';
                $response = $domrobot->call('contact', 'create', array_filter($pContact));
                $pDomain[$type] = $response['resData']['id'];
                $values['error'] = inwx_GetApiResponseErrorMessage($response);
            } else {
                $pContact['id'] = $contactIds[$type];
                $response = $domrobot->call('contact', 'update', array_filter($pContact));
                $values['error'] = inwx_GetApiResponseErrorMessage($response);
            }
        }
        if (count($pDomain) > 1) {
            $response = $domrobot->call('domain', 'update', $pDomain);
            $values['error'] = inwx_GetApiResponseErrorMessage($response);
        }
    } else {
        $values['error'] = inwx_GetApiResponseErrorMessage($response);
    }
    return $values;
}

function inwx_RegisterNameserver(array $params): array
{
    $values = ['error' => ''];
    $domrobot = inwx_CreateDomrobot($params);

    $pHost['hostname'] = $params['nameserver'];
    $pHost['ip'] = $params['ipaddress'];

    $response = $domrobot->call('host', 'create', inwx_InjectCredentials($params, $pHost));
    $values['error'] = inwx_GetApiResponseErrorMessage($response);

    return $values;
}

function inwx_ModifyNameserver(array $params): array
{
    $values = ['error' => ''];
    $domrobot = inwx_CreateDomrobot($params);

    $pHost['hostname'] = $params['nameserver'];
    $pHost['ip'] = $params['newipaddress'];

    $response = $domrobot->call('host', 'update', inwx_InjectCredentials($params, $pHost));
    $values['error'] = inwx_GetApiResponseErrorMessage($response);

    return $values;
}

function inwx_DeleteNameserver(array $params): array
{
    $values = ['error' => ''];
    $domrobot = inwx_CreateDomrobot($params);

    $pHost['hostname'] = $params['nameserver'];

    $response = $domrobot->call('host', 'delete', inwx_InjectCredentials($params, $pHost));
    $values['error'] = inwx_GetApiResponseErrorMessage($response);

    return $values;
}

function inwx_IDProtectToggle(array $params): array
{
    $values = [];
    $domrobot = inwx_CreateDomrobot($params);

    $pDomain = [
        'domain' => $params['original']['sld'] . '.' . $params['original']['tld'],
        'extData' => ['WHOIS-PROTECTION' => $params['protectenable']],
    ];

    $response = $domrobot->call('domain', 'update', inwx_InjectCredentials($params, $pDomain));
    $values['error'] = inwx_GetApiResponseErrorMessage($response);

    return $values;
}

function inwx_RegisterDomain(array $params): array
{
    $params = inwx_InjectOriginalDomain($params);
    $values = ['error' => ''];
    $domrobot = inwx_CreateDomrobot($params);

    $user = new Obfuscated($params['Username']);
    $pass = new Obfuscated($params['Password']);

    $domrobot->login($user, $pass);

    // Registrant creation
    $pRegistrant['type'] = 'PERSON';
    $pRegistrant['name'] = $params['firstname'] . ' ' . $params['lastname'];
    if (isset($params['companyname']) && !empty($params['companyname'])) {
        $pRegistrant['org'] = $params['companyname'];
    }
    $pRegistrant['street'] = $params['address1'];
    if (isset($params['address2']) && !empty($params['address2'])) {
        $pRegistrant['street2'] = $params['address2'];
    }
    $pRegistrant['city'] = $params['city'];
    if (isset($params['state']) && !empty($params['state'])) {
        $pRegistrant['sp'] = $params['state'];
    }
    $pRegistrant['pc'] = $params['postcode'];
    $pRegistrant['cc'] = strtoupper($params['country']);
    $pRegistrant['email'] = $params['email'];
    $pRegistrant['voice'] = $params['fullphonenumber'];
    if (isset($params['notes']) && !empty($params['notes'])) {
        $pRegistrant['remarks'] = $params['notes'];
    }
    $pRegistrant['extData'] = ['PARSE-VOICE' => true, 'PARSE-FAX' => true];

    // do registrant create command
    $response = $domrobot->call('contact', 'create', $pRegistrant);
    if (($response['code'] === 1000 || $response['code'] === 1001)) {
        $pDomain['registrant'] = $response['resData']['id'];
    } else {
        $values['error'] = inwx_GetApiResponseErrorMessage($response);
        return $values;
    }

    // Admin creation
    $pAdmin['type'] = 'PERSON';
    $pAdmin['name'] = $params['adminfirstname'] . ' ' . $params['adminlastname'];
    if (isset($params['admincompanyname']) && !empty($params['admincompanyname'])) {
        $pAdmin['org'] = $params['admincompanyname'];
    }
    $pAdmin['street'] = $params['adminaddress1'];
    if (isset($params['adminaddress2']) && !empty($params['adminaddress2'])) {
        $pAdmin['street2'] = $params['adminaddress2'];
    }
    $pAdmin['city'] = $params['admincity'];
    if (isset($params['adminstate']) && !empty($params['adminstate'])) {
        $pAdmin['sp'] = $params['adminstate'];
    }
    $pAdmin['pc'] = $params['adminpostcode'];
    $pAdmin['cc'] = strtoupper($params['admincountry']);
    $pAdmin['email'] = $params['adminemail'];
    $pAdmin['voice'] = $params['adminfullphonenumber'];
    if (isset($params['adminnotes']) && !empty($params['adminnotes'])) {
        $pAdmin['remarks'] = $params['adminnotes'];
    }
    $pAdmin['extData'] = ['PARSE-VOICE' => true, 'PARSE-FAX' => true];

    // do admin create command
    $response = $domrobot->call('contact', 'create', $pAdmin);
    if (($response['code'] === 1000 || $response['code'] === 1001)) {
        $pDomain['admin'] = $response['resData']['id'];
    } else {
        $values['error'] = inwx_GetApiResponseErrorMessage($response);
        return $values;
    }

    // 	Register Domain
    $pDomain['domain'] = $params['domainObj']->getDomain();
    $pDomain['renewalMode'] = ($params['tld'] === 'at' || substr($params['tld'], -3) === '.at') ? 'AUTODELETE' : 'AUTOEXPIRE';
    if (isset($params['TechHandle']) && !empty($params['TechHandle'])) {
        $pDomain['tech'] = $params['TechHandle'];
    } else {
        $pDomain['tech'] = $pDomain['admin'];
    }
    if (isset($params['BillingHandle']) && !empty($params['BillingHandle'])) {
        $pDomain['billing'] = $params['BillingHandle'];
    } else {
        $pDomain['billing'] = $pDomain['admin'];
    }
    for ($i = 1; $i <= 4; ++$i) {
        if (isset($params['ns' . $i]) && !empty($params['ns' . $i])) {
            $pDomain['ns'][] = $params['ns' . $i];
        }
    }
    $pDomain['period'] = $params['regperiod'] . 'Y';

    // ext data
    global $additionaldomainfields;
    inwx_IncludeAdditionalDomainFields();
    if (is_array($additionaldomainfields) && isset($additionaldomainfields['.' . $params['tld']])) {
        foreach ($additionaldomainfields['.' . $params['tld']] as $addField) {
            if (isset($addField['InwxName'], $params['additionalfields'][$addField['InwxName']])) {
                switch ($addField['Type']) {
                    case 'text':
                        $pDomain['extData'][$addField['InwxName']] = $params['additionalfields'][$addField['InwxName']];
                        break;
                    case 'tickbox':
                        if ($params['additionalfields'][$addField['InwxName']] === 'on') {
                            $pDomain['extData'][$addField['InwxName']] = 1;
                        }
                        break;
                    case 'dropdown':
                        $_whmcsOptions = explode(',', $addField['Options']);
                        $_inwxOptions = explode(',', $addField['InwxOptions']);
                        $_key = array_search($params['additionalfields'][$addField['InwxName']], $_whmcsOptions, true);
                        $pDomain['extData'][$addField['InwxName']] = $_inwxOptions[$_key];
                        break;
                }
            }
        }
    }

    if ($params['idprotection']) {
        $pDomain['extData']['WHOIS-PROTECTION'] = true;
    }

    // create nameserver
    if ($params['dnsmanagement'] && count($pDomain['ns']) > 0) {
        $pNs['domain'] = $pDomain['domain'];
        $pNs['type'] = 'MASTER';
        $pNs['ns'] = $pDomain['ns'];
        $domrobot->call('nameserver', 'create', $pNs);
    }

    // do domain create command
    $response = $domrobot->call('domain', 'create', $pDomain);
    $values['error'] = inwx_GetApiResponseErrorMessage($response);

    return $values;
}

function inwx_TransferDomain(array $params): array
{
    $params = inwx_InjectOriginalDomain($params);
    $values = ['error' => ''];
    $domrobot = inwx_CreateDomrobot($params);

    $user = new Obfuscated($params['Username']);
    $pass = new Obfuscated($params['Password']);

    $domrobot->login($user, $pass);

    // Registrant creation
    $pRegistrant['type'] = 'PERSON';
    $pRegistrant['name'] = $params['firstname'] . ' ' . $params['lastname'];
    if (isset($params['companyname']) && !empty($params['companyname'])) {
        $pRegistrant['org'] = $params['companyname'];
    }
    $pRegistrant['street'] = $params['address1'];
    if (isset($params['address2']) && !empty($params['address2'])) {
        $pRegistrant['street2'] = $params['address2'];
    }
    $pRegistrant['city'] = $params['city'];
    if (isset($params['state']) && !empty($params['state'])) {
        $pRegistrant['sp'] = $params['state'];
    }
    $pRegistrant['pc'] = $params['postcode'];
    $pRegistrant['cc'] = strtoupper($params['country']);
    $pRegistrant['email'] = $params['email'];
    $pRegistrant['voice'] = $params['fullphonenumber'];
    if (isset($params['notes']) && !empty($params['notes'])) {
        $pRegistrant['remarks'] = $params['notes'];
    }
    $pRegistrant['extData'] = ['PARSE-VOICE' => true, 'PARSE-FAX' => true];

    // do registrant create command
    $response = $domrobot->call('contact', 'create', $pRegistrant);
    if (($response['code'] === 1000 || $response['code'] === 1001)) {
        $pDomain['registrant'] = $response['resData']['id'];
    } else {
        $values['error'] = inwx_GetApiResponseErrorMessage($response);
        return $values;
    }

    // Admin creation
    $pAdmin['type'] = 'PERSON';
    $pAdmin['name'] = $params['adminfirstname'] . ' ' . $params['adminlastname'];
    if (isset($params['admincompanyname']) && !empty($params['admincompanyname'])) {
        $pAdmin['org'] = $params['admincompanyname'];
    }
    $pAdmin['street'] = $params['adminaddress1'];
    if (isset($params['adminaddress2']) && !empty($params['adminaddress2'])) {
        $pAdmin['street2'] = $params['adminaddress2'];
    }
    $pAdmin['city'] = $params['admincity'];
    if (isset($params['adminstate']) && !empty($params['adminstate'])) {
        $pAdmin['sp'] = $params['adminstate'];
    }
    $pAdmin['pc'] = $params['adminpostcode'];
    $pAdmin['cc'] = strtoupper($params['admincountry']);
    $pAdmin['email'] = $params['adminemail'];
    $pAdmin['voice'] = $params['adminfullphonenumber'];
    if (isset($params['adminnotes']) && !empty($params['adminnotes'])) {
        $pAdmin['remarks'] = $params['adminnotes'];
    }
    $pAdmin['extData'] = ['PARSE-VOICE' => true, 'PARSE-FAX' => true];

    // do admin create command
    $response = $domrobot->call('contact', 'create', $pAdmin);
    if (($response['code'] === 1000 || $response['code'] === 1001)) {
        $pDomain['admin'] = $response['resData']['id'];
    } else {
        $values['error'] = inwx_GetApiResponseErrorMessage($response);
        return $values;
    }

    // 	Transfer Domain
    $pDomain['domain'] = $params['original']['sld'] . '.' . $params['original']['tld'];
    $pDomain['renewalMode'] = ($params['tld'] === 'at' || substr($params['tld'], -3) === '.at') ? 'AUTODELETE' : 'AUTOEXPIRE';
    if (isset($params['TechHandle']) && !empty($params['TechHandle'])) {
        $pDomain['tech'] = $params['TechHandle'];
    } else {
        $pDomain['tech'] = $pDomain['admin'];
    }
    if (isset($params['BillingHandle']) && !empty($params['BillingHandle'])) {
        $pDomain['billing'] = $params['BillingHandle'];
    } else {
        $pDomain['billing'] = $pDomain['admin'];
    }
    for ($i = 1; $i <= 4; ++$i) {
        if (isset($params['ns' . $i]) && !empty($params['ns' . $i])) {
            $pDomain['ns'][] = $params['ns' . $i];
        }
    }
    // $pDomain['period'] = $params["regperiod"]."Y"; // not yet supported!
    if (isset($params['transfersecret']) && !empty($params['transfersecret'])) {
        $pDomain['authCode'] = $params['transfersecret'];
    }

    // ext data
    global $additionaldomainfields;
    inwx_IncludeAdditionalDomainFields();
    if (is_array($additionaldomainfields) && isset($additionaldomainfields['.' . $params['tld']])) {
        foreach ($additionaldomainfields['.' . $params['tld']] as $addField) {
            if (isset($addField['InwxName'], $params['additionalfields'][$addField['InwxName']])) {
                switch ($addField['Type']) {
                    case 'text':
                        $pDomain['extData'][$addField['InwxName']] = $params['additionalfields'][$addField['InwxName']];
                        break;
                    case 'tickbox':
                        if ($params['additionalfields'][$addField['InwxName']] === 'on') {
                            $pDomain['extData'][$addField['InwxName']] = 1;
                        }
                        break;
                    case 'dropdown':
                        $_whmcsOptions = explode(',', $addField['Options']);
                        $_inwxOptions = explode(',', $addField['InwxOptions']);
                        $_key = array_search($params['additionalfields'][$addField['InwxName']], $_whmcsOptions, true);
                        $pDomain['extData'][$addField['InwxName']] = $_inwxOptions[$_key];
                        break;
                }
            }
        }
    }

    $response = $domrobot->call('domain', 'transfer', $pDomain);
    $values['error'] = inwx_GetApiResponseErrorMessage($response);

    return $values;
}

function inwx_GetTldPricing(array $params)
{
    $domrobot = inwx_CreateDomrobot($params);

    $response = $domrobot->call('domain', 'getPrices', array_merge(inwx_InjectCredentials($params), ["vat" => false]));
    if (($response['code'] === 1000 || $response['code'] === 1001)) {
        $prices = $response['resData']['price'];
    } else {
        $values['error'] = inwx_GetApiResponseErrorMessage($response);
        return $values;
    }

    $tldRules = [];

    $domainRulesResponse = $domrobot->call('domain', 'getRules', inwx_InjectCredentials($params));

    if ($domainRulesResponse['code'] !== 1000) {
        return ['error' => inwx_GetApiResponseErrorMessage($domainRulesResponse)];
    }

    foreach ($domainRulesResponse['resData']['rules'] as $tldRule) {
        $tldRules[$tldRule['tld']] = $tldRule;
    }

    $domains = new ResultsList();

    foreach ($prices as $price) {
        $tld = $price['tld'];
        if ($tld === null) {
            continue;
        }
        $tldRule = $tldRules[$tld];

        $maxYears = explode(',', $tldRule['registrationPeriod']);
        $maxYears = end($maxYears);
        $maxYears = str_replace('Y', '', $maxYears);

        $domain = (new ImportItem())
            ->setExtension($tld)
            ->setMinYears($price['createPeriod'])
            ->setMaxYears($maxYears)
            ->setRegisterPrice($price['promo']['createPrice'] ?? $price['createPrice'])
            ->setRenewPrice($price['promo']['renewalPrice'] ?? $price['renewalPrice'])
            ->setTransferPrice($price['promo']['renewalPrice'] ?? $price['transferPrice'])
            ->setEppRequired($tldRule['authCode'] == 'YES')
            ->setCurrency($price['currency']);

        $domains[] = $domain;
    }

    return $domains;
}

function inwx_RenewDomain(array $params): array
{
    $params = inwx_InjectOriginalDomain($params);
    $values = ['error' => ''];
    $domrobot = inwx_CreateDomrobot($params);

    $user = new Obfuscated($params['Username']);
    $pass = new Obfuscated($params['Password']);

    $domrobot->login($user, $pass);

    $domain = $params['original']['sld'] . '.' . $params['original']['tld'];

    $response = $domrobot->call('domain', 'restore', [
        "domain" => $domain,
        "testing" => true
    ]);

    if ($response['code'] === 1000 || $response['code'] === 1001) {
        $response = $domrobot->call('domain', 'restore', ["domain" => $domain]);
        if ($response['code'] !== 1000 && $response['code'] !== 1001) {
            $values['error'] = inwx_GetApiResponseErrorMessage($response);
            return $values;
        }
    }

    $response = $domrobot->call('domain', 'info', ["domain" => $domain]);

    if (($response['code'] === 1000 || $response['code'] === 1001) && isset($response['resData']['exDate'])) {
        $pDomain['expiration'] = date('Y-m-d', $response['resData']['exDate']['timestamp']);
    } else {
        $values['error'] = inwx_GetApiResponseErrorMessage($response);
        return $values;
    }

    $pDomain['period'] = $params['regperiod'] . 'Y';
    $response = $domrobot->call('domain', 'renew', $pDomain);
    $values['error'] = inwx_GetApiResponseErrorMessage($response);

    return $values;
}

function inwx_CheckAvailability(array $params)
{
    $params = inwx_InjectOriginalDomain($params);
    $domrobot = inwx_CreateDomrobot($params);

    $payload = [
        'sld' => $params['original']['sld'],
        'tld' => array_map(static function ($tld) {
            // Remove dot at end of tld
            return substr($tld, 1);
        }, $params['original']['tldsToInclude'])
    ];
    $response = $domrobot->call('domain', 'check', inwx_InjectCredentials($params, $payload));

    if ($response['code'] !== 1000) {
        return ['error' => inwx_GetApiResponseErrorMessage($response)];
    }

    $domains = $response['resData']['domain']; // whoever had the idea to name an array with a singular name...literal god

    $searchResults = new ResultsList();

    foreach ($domains as $domain) {
        // 0 = sld, 1 = tld
        $domainParts = explode('.', $domain['domain'], 2);

        $searchResult = new SearchResult($domainParts[0], $domainParts[1]);

        if ($domain['avail'] === 1) {
            $searchResult->setStatus(SearchResult::STATUS_NOT_REGISTERED);
        } elseif ($domain['avail'] === 0) {
            $searchResult->setStatus(SearchResult::STATUS_REGISTERED);
        } else {
            $searchResult->setStatus(SearchResult::STATUS_UNKNOWN);
        }

        if (array_key_exists('premium', $domain) && is_array($domain['premium'])) {
            $searchResult->setPremiumDomain(true);
            $searchResult->setPremiumCostPricing(
                [
                    'register' => $domain['premium']['prices']['create']['price'],
                    'renew' => $domain['premium']['prices']['renew']['price'],
                    'transfer' => $domain['premium']['prices']['transfer']['price'],
                    'CurrencyCode' => $domain['premium']['currency'],
                ]
            );
        }

        $searchResults->append($searchResult);
    }

    return $searchResults;
}

function inwx_ResendIRTPVerificationEmail(array $params): array
{
    $params = inwx_InjectOriginalDomain($params);
    $domrobot = inwx_CreateDomrobot($params);

    $user = new Obfuscated($params['Username']);
    $pass = new Obfuscated($params['Password']);

    $domrobot->login($user, $pass);

    $domain['domain'] = $params['original']['sld'] . '.' . $params['original']['tld'];

    $domainInfoResponse = $domrobot->call('domain', 'info', $domain);

    if ($domainInfoResponse['code'] !== 1000 || !isset($domainInfoResponse['resData']['registrant'])) {
        return ['error' => inwx_GetApiResponseErrorMessage($domainInfoResponse)];
    }

    $payload = ['id' => $domainInfoResponse['resData']['registrant']];
    $response = $domrobot->call('contact', 'sendcontactverification', $payload);

    if ($response['code'] !== 1000) {
        return ['error' => inwx_GetApiResponseErrorMessage($response)];
    }

    return ['success' => true];
}

function inwx_ReleaseDomain(array $params): array
{
    $params = inwx_InjectOriginalDomain($params);
    $domrobot = inwx_CreateDomrobot($params);

    $payload['domain'] = $params['original']['sld'] . '.' . $params['original']['tld'];
    if (!empty($params['transfertag'])) {
        $payload['target'] = $params['transfertag'];
    }

    $response = $domrobot->call('domain', 'push', inwx_InjectCredentials($params, $payload));

    if ($response['code'] !== 1000) {
        return ['error' => inwx_GetApiResponseErrorMessage($response)];
    }

    return ['success' => true];
}

function startsWith( $haystack, $needle ) {
    $length = strlen( $needle );
    return substr( $haystack, 0, $length ) === $needle;
}

function endsWith( $haystack, $needle ) {
    $length = strlen( $needle );
    if( !$length ) {
        return true;
    }
    return substr( $haystack, -$length ) === $needle;
}

function inwx_SyncDomain($params) {
    $params = inwx_InjectOriginalDomain($params);
    $domrobot = inwx_CreateDomrobot($params);

    $pDomain['domain'] = $params['original']['sld'] . '.' . $params['original']['tld'];
    $pDomain['wide'] = 1;

    $response = $domrobot->call('domain', 'info', inwx_InjectCredentials($params, $pDomain));

    if ($response['code'] === 2303) {
        try {
            Capsule::table('tbldomains')
                ->where('id', $params['domainid'])
                ->update([
                    'status' => 'Cancelled'
                ]);

            return ['error' => inwx_GetApiResponseErrorMessage($response)];
        } catch (Exception $e) {
            return ['error' => "Couldn't update domain. {$e->getMessage()}"];
        }
    }

    if ($response['code'] === 1000 && isset($response['resData']['domain'])) {
        $exDate = (isset($response['resData']['exDate']) ? date('Y-m-d', $response['resData']['exDate']['timestamp']) : null);
        $crDate = (isset($response['resData']['crDate']) ? date('Y-m-d', $response['resData']['crDate']['timestamp']) : null);
        $reDate = (isset($response['resData']['reDate']) ? date('Y-m-d', $response['resData']['reDate']['timestamp']) : null);
        $status = (isset($response['resData']['status']) ?: null);

        $updateDetails = [];

        if ($status === 'OK') {
            $updateDetails['status'] = 'Active';
        } else if (startsWith($status, 'TRANSFER') && endsWith($status, 'SUCCESSFUL')) {
            $updateDetails['status'] = 'Transferred Away';
        } else if (startsWith($status, 'TRANSFER') && !endsWith($status, 'SUCCESSFUL')) {
            $updateDetails['status'] = 'Pending Transfer';
        }

        // set expiration date if available
        if (!is_null($exDate)) {
            $updateDetails['expirydate'] = $exDate;
        }

        if (!is_null($crDate)) {
            $updateDetails['registrationdate'] = $crDate;
        }

        if (!is_null($reDate)) {
            $updateDetails['nextduedate'] = $reDate;
        }

        try {
            $updatedDomain = Capsule::table('tbldomains')
                ->where('id', $params['domainid'])
                ->update($updateDetails);

            return ['message' => "Updated ${updatedDomain} domain(s)."];
        } catch (Exception $e) {
            return ['error' => "Couldn't update domain. {$e->getMessage()}"];
        }
    }

    return ['error' => ''];
}

function inwx_AdminCustomButtonArray() {
    $buttonarray = array(
        "Sync Domain" => "SyncDomain"
    );
    return $buttonarray;
}

/**
 * @throws Exception
 */
function inwx_GetDomainInformation(array $params): Domain
{
    $params = inwx_InjectOriginalDomain($params);
    $domrobot = inwx_CreateDomrobot($params);

    $pDomain['domain'] = $params['original']['sld'] . '.' . $params['original']['tld'];
    $pDomain['wide'] = 1;

    $response = $domrobot->call('domain', 'info', inwx_InjectCredentials($params, $pDomain));

    $domain = new Domain;

    if ($response['code'] !== 1000) {
        throw new Exception(inwx_GetApiResponseErrorMessage($response));
    }

    if (isset($response['resData']['ns'])) {
        $nameservers = [];
        for ($i = 0; $i < count($response['resData']['ns']); $i++) {
            $nameservers['ns' . ($i + 1)] = $response['resData']['ns'][$i];
        }
        $domain->setNameservers($nameservers);
    }

    if (isset($response['resData']['status'])) {
        switch ($response['resData']['status']) {
            case 'OK':
                $domain->setRegistrationStatus(Domain::STATUS_ACTIVE);
                break;
            case 'DELETE SCHEDULED':
            case 'DELETE INITIATED':
                $domain->setRegistrationStatus(Domain::STATUS_PENDING_DELETE);
                break;
            case 'DELETE':
            case 'DELETE SUCCESSFUL':
                $domain->setRegistrationStatus(Domain::STATUS_DELETED);
                break;
            case 'EXPIRE SUCCESSFUL':
            case 'EXPIRED':
                $domain->setRegistrationStatus(Domain::STATUS_EXPIRED);
                break;
            default:
                $domain->setRegistrationStatus(Domain::STATUS_INACTIVE);
        }
    }

    if (isset($response['resData']['transferLock'])) {
        $domain->setTransferLock($response['resData']['transferLock'])
            ->setTransferLockExpiryDate(null)
        ;
    }

    if (isset($response['resData']['exDate'])) {
        $domain->setExpiryDate(Carbon::parse($response['resData']['exDate']['scalar']));
    }

    if (isset($response['resData']['registrantVerificationStatus']) && isset($response['resData']['verificationStatus'])) {
        $domain->setIsIrtpEnabled(true);
    }

    return $domain
        ->setPendingSuspension(true)
        ->setDomain($response['resData']['domain'])
        ->setIrtpVerificationTriggerFields(
            [
                'Registrant' => [
                    'First Name',
                    'Last Name',
                    'Organization Name',
                    'Email',
                ],
            ]
        )
        ;
}
