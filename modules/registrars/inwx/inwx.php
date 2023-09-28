<?php

use WHMCS\Domain\Registrar\Domain;
use WHMCS\Domain\TopLevel\ImportItem;
use WHMCS\Domains\DomainLookup\ResultsList;
use WHMCS\Domains\DomainLookup\SearchResult;

include_once 'helpers.php';

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
            $values['eppcode'] = htmlspecialchars($response['resData']['authCode']);
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
            $values['ns' . $i] = (isset($response['resData']['ns'][($i - 1)])) ? htmlspecialchars($response['resData']['ns'][($i - 1)]) : '';
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
    $domrobot->login($params['Username'], $params['Password']);

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
    $domrobot->login($params['Username'], $params['Password']);

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
                $response = $domrobot->call('contact', 'create', $pContact);
                $pDomain[$type] = $response['resData']['id'];
                $values['error'] = inwx_GetApiResponseErrorMessage($response);
            } else {
                $pContact['id'] = $contactIds[$type];
                $response = $domrobot->call('contact', 'update', $pContact);
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
    $domrobot->login($params['Username'], $params['Password']);

    // Registrant creation
    $pRegistrant['type'] = 'PERSON';
    $pRegistrant['name'] = $params['firstname'] . ' ' . $params['lastname'];
    if (isset($params['companyname']) && empty($params['companyname'])) {
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
    if (isset($params['admincompanyname']) && empty($params['admincompanyname'])) {
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
    $domrobot->login($params['Username'], $params['Password']);

    // Registrant creation
    $pRegistrant['type'] = 'PERSON';
    $pRegistrant['name'] = $params['firstname'] . ' ' . $params['lastname'];
    if (isset($params['companyname']) && empty($params['companyname'])) {
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
    if (isset($params['admincompanyname']) && empty($params['admincompanyname'])) {
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
    if ($params['TestMode']) {
        $csvUrl = 'https://ote.inwx.de/en/domain/pricelist/vat/1/file/csv';
    } else {
        $csvUrl = 'https://www.inwx.de/en/domain/pricelist/vat/1/file/csv';
    }

    if ($rawCsv = file_get_contents($csvUrl, ';')) {
        $rawCsv = file_get_contents($csvUrl);

        // remove header line and parse data to array
        $csvData = preg_replace('/^.+\n/', '', $rawCsv);
        $rawLines = explode("\n", $csvData);

        $datasets = [];
        foreach ($rawLines as $line) {
            $datasets[] = str_getcsv($line, ';');
        }
    } else {
        return ['error' => 'Could not fetch csv.'];
    }

    $tldRules = [];

    $domrobot = inwx_CreateDomrobot($params);
    $domainRulesResponse = $domrobot->call('domain', 'getRules', inwx_InjectCredentials($params));

    if ($domainRulesResponse['code'] !== 1000) {
        return ['error' => inwx_GetApiResponseErrorMessage($domainRulesResponse)];
    }

    foreach ($domainRulesResponse['resData']['rules'] as $tldRule) {
        $tldRules[$tldRule['tld']] = $tldRule;
    }

    $domains = new ResultsList();

    foreach ($datasets as $dataset) {
        $tld = $dataset[0];
        if ($tld === null) {
            continue;
        }
        $tldRule = $tldRules[$tld];

        $maxYears = explode(',', $tldRule['registrationPeriod']);
        $maxYears = end($maxYears);
        $maxYears = str_replace('Y', '', $maxYears);

        $domain = (new ImportItem())
            ->setExtension($tld)
            ->setMinYears(intval($dataset[2]))
            ->setMaxYears($maxYears)
            ->setRegisterPrice(floatval($dataset[1]))
            ->setRenewPrice(floatval($dataset[5]))
            ->setTransferPrice(floatval($dataset[3]))
            ->setEppRequired($tldRule['authCode'] == 'YES')
            ->setCurrency('EUR');

        $domains[] = $domain;
    }

    return $domains;
}

function inwx_RenewDomain(array $params): array
{
    $params = inwx_InjectOriginalDomain($params);
    $values = ['error' => ''];
    $domrobot = inwx_CreateDomrobot($params);
    $domrobot->login($params['Username'], $params['Password']);

    $pDomain['domain'] = $params['original']['sld'] . '.' . $params['original']['tld'];

    $response = $domrobot->call('domain', 'info', $pDomain);

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
    $domrobot->login($params['Username'], $params['Password']);

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
