<?php

use WHMCS\Carbon;
use WHMCS\Domain\Registrar\Domain;
use WHMCS\Domain\TopLevel\ImportItem;
use WHMCS\Domains\DomainLookup\ResultsList;
use WHMCS\Domains\DomainLookup\SearchResult;
use INWX\Domrobot;

include_once 'api/Domrobot.php';

function inwx_RequestDelete($params) {
    $params = inwx_InjectOriginalDomain($params);

    // call domrobot
    $domrobot = inwx_CreateDomrobot($params);
    $response = $domrobot->call('domain', 'delete', inwx_InjectCredentials($params, ['domain' => $params['original']['sld'] . '.' . $params['original']['tld']]));
    if ($response['code'] !== 1000) {
        return ['error' => inwx_GetApiResponseErrorMessage($response)];
    }

    return true;
}

function inwx_Sync($params)
{
    $params = inwx_InjectOriginalDomain($params);
    $values = [];
    // call domrobot
    $domrobot = inwx_CreateDomrobot($params);
    $response = $domrobot->call('domain', 'info', inwx_InjectCredentials($params, ['domain' => $params['original']['sld'] . '.' . $params['original']['tld']]));
    if ($response['code'] === 1000 && isset($response['resData']['domain'])) {
        $exDate = (isset($response['resData']['exDate']) ? date('Y-m-d', $response['resData']['exDate']->timestamp) : null);

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

function inwx_getConfigArray()
{
    return [
        'Username' => ['Type' => 'text', 'Size' => '20', 'Description' => 'Enter your INWX username here'],
        'Password' => ['Type' => 'password', 'Size' => '20', 'Description' => 'Enter your INWX password here'],
        'TestMode' => ['Type' => 'yesno', 'Description' => 'Connect to OTE (Test Environment). Your credentials may differ.'],
        'TechHandle' => ['Type' => 'text', 'Description' => 'Enter your default contact handle id for tech contact.<br/>.DE domains require a fax number for the tech contact. Since WHMCS does not provide a field for this, you can manually create a contact with a fax number in the INWX webinterface, and specify the handle here.<br/>(You can use our default Tech/Billing contact handle: 1).'],
        'BillingHandle' => ['Type' => 'text', 'Description' => 'Enter your default contact handle id for billing contact.<br/>.DE domains require a fax number for the billing contact. Since WHMCS does not provide a field for this, you can manually create a contact with a fax number in the INWX webinterface, and specify the handle here.<br/>(You can use our default Tech/Billing contact handle: 1).'],
    ];
}

function inwx_GetRegistrarLock($params)
{
    $params = inwx_InjectOriginalDomain($params);
    $domrobot = inwx_CreateDomrobot($params);

    $pDomain['domain'] = $params['original']['sld'] . '.' . $params['original']['tld'];
    $pDomain['wide'] = 1;

    $response = $domrobot->call('domain', 'info', inwx_InjectCredentials($params, $pDomain));

    if ($response['code'] === 1000 && isset($response['resData']['transferLock'])) {
        if ($response['resData']['transferLock'] === 1) {
            $lockstatus = 'locked';
        } elseif ($response['resData']['transferLock'] === 0) {
            $lockstatus = 'unlocked';
        } else {
            $lockstatus = '';
        }
        return $lockstatus;
    }

    return ['error' => inwx_GetApiResponseErrorMessage($response)];
}

function inwx_SaveRegistrarLock($params)
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

function inwx_GetEPPCode($params)
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

function inwx_GetNameservers($params)
{
    $params = inwx_InjectOriginalDomain($params);
    $values = ['error' => ''];
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

function inwx_SaveNameservers($params)
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

function inwx_GetDNS($params)
{
    $params = inwx_InjectOriginalDomain($params);
    $values = ['error' => ''];
    $hostrecords = [];
    $domrobot = inwx_CreateDomrobot($params);

    $pInfo['domain'] = $params['original']['sld'] . '.' . $params['original']['tld'];
    $response = $domrobot->call('nameserver', 'info', inwx_InjectCredentials($params, $pInfo));

    if ($response['code'] === 1000 && isset($response['resData']['record']) && count($response['resData']['record']) > 0) {
        $_allowedRecTypes = ['A', 'AAAA', 'CNAME', 'MX', 'SPF', 'TXT', 'URL', 'SRV'];
        foreach ($response['resData']['record'] as $_record) {
            if (in_array($_record['type'], $_allowedRecTypes, true)) {
                if ($_record['type'] === 'URL') {
                    $_record['type'] = (isset($_record['urlRedirectType']) && $_record['urlRedirectType'] === 'FRAME') ? 'FRAME' : 'URL';
                }
                $hostrecords[] = ['hostname' => $_record['name'], 'type' => $_record['type'], 'address' => $_record['content'], 'priority' => $_record['prio']];
            }
        }
    } else {
        $values['error'] = inwx_GetApiResponseErrorMessage($response);
    }

    return $hostrecords;
}

function inwx_SaveDNS($params)
{
    $params = inwx_InjectOriginalDomain($params);
    $values = ['error' => ''];
    $domrobot = inwx_CreateDomrobot($params);
    $domrobot->login($params['Username'], $params['Password']);

    $pInfo['domain'] = $params['original']['sld'] . '.' . $params['original']['tld'];
    $response = $domrobot->call('nameserver', 'info', $pInfo);
    $_records = [];
    if ($response['code'] === 1000 && isset($response['resData']['record']) && count($response['resData']['record']) > 0) {
        $_allowedRecTypes = ['A', 'AAAA', 'CNAME', 'MX', 'SPF', 'TXT', 'URL', 'SRV'];
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
        if (empty($val['address'])) {
            continue;
        }
        $pRecord = [];
        $pRecord['id'] = (isset($_records[$key]['id'])) ? $_records[$key]['id'] : null;
        $pRecord['name'] = $val['hostname'];
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

function inwx_GetContactDetails($params)
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

function inwx_SaveContactDetails($params)
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

function inwx_RegisterNameserver($params)
{
    $values = ['error' => ''];
    $domrobot = inwx_CreateDomrobot($params);

    $pHost['hostname'] = $params['nameserver'];
    $pHost['ip'] = $params['ipaddress'];

    $response = $domrobot->call('host', 'create', inwx_InjectCredentials($params, $pHost));
    $values['error'] = inwx_GetApiResponseErrorMessage($response);

    return $values;
}

function inwx_ModifyNameserver($params)
{
    $values = ['error' => ''];
    $domrobot = inwx_CreateDomrobot($params);

    $pHost['hostname'] = $params['nameserver'];
    $pHost['ip'] = $params['newipaddress'];

    $response = $domrobot->call('host', 'update', inwx_InjectCredentials($params, $pHost));
    $values['error'] = inwx_GetApiResponseErrorMessage($response);

    return $values;
}

function inwx_DeleteNameserver($params)
{
    $values = ['error' => ''];
    $domrobot = inwx_CreateDomrobot($params);

    $pHost['hostname'] = $params['nameserver'];

    $response = $domrobot->call('host', 'delete', inwx_InjectCredentials($params, $pHost));
    $values['error'] = inwx_GetApiResponseErrorMessage($response);

    return $values;
}

function inwx_IDProtectToggle($params)
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

function inwx_RegisterDomain($params)
{
    $params = injectDomainObjectIfNecessary($params);
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
    include 'additionaldomainfields.php';
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
    if ($params['dnsmanagement'] === 1 && count($pDomain['ns']) > 0) {
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

function inwx_TransferDomain($params)
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

    // TODO: ext data

    $response = $domrobot->call('domain', 'transfer', $pDomain);
    $values['error'] = inwx_GetApiResponseErrorMessage($response);

    return $values;
}

function inwx_GetTldPricing($params)
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

function inwx_RenewDomain($params)
{
    $params = inwx_InjectOriginalDomain($params);
    $values = ['error' => ''];
    $domrobot = inwx_CreateDomrobot($params);
    $domrobot->login($params['Username'], $params['Password']);

    $pDomain['domain'] = $params['original']['sld'] . '.' . $params['original']['tld'];

    $response = $domrobot->call('domain', 'info', $pDomain);

    if (($response['code'] === 1000 || $response['code'] === 1001) && isset($response['resData']['exDate'])) {
        $pDomain['expiration'] = date('Y-m-d', $response['resData']['exDate']->timestamp);
    } else {
        $values['error'] = inwx_GetApiResponseErrorMessage($response);
        return $values;
    }

    $pDomain['period'] = $params['regperiod'] . 'Y';
    $response = $domrobot->call('domain', 'renew', $pDomain);
    $values['error'] = inwx_GetApiResponseErrorMessage($response);

    return $values;
}

function inwx_CheckAvailability($params)
{
    $domrobot = inwx_CreateDomrobot($params);

    $payload = ['domain' => $params['sld'] . $params['tlds'][0]];
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
            $searchResult->setStatus(SearchResult::UNKNOWN);
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

function inwx_ResendIRTPVerificationEmail($params)
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

function inwx_ReleaseDomain($params)
{
    $params = inwx_InjectOriginalDomain($params);
    $domrobot = inwx_CreateDomrobot($params);

    $payload['domain'] = $params['original']['sld'] . '.' . $params['original']['tld'];
    if (!empty($params["transfertag"])) {
        $payload["target"] = $params["transfertag"];
    }

    $response = $domrobot->call('domain', 'push', inwx_InjectCredentials($params, $payload));

    if ($response['code'] !== 1000) {
        return ['error' => inwx_GetApiResponseErrorMessage($response)];
    }

    return ['success' => true];
}

function injectOriginalDomain($params)
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

function inwx_GetApiResponseErrorMessage(array $response): string {
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

function inwx_InjectCredentials(array $params, array $originalParameters = []) {
    return array_merge(['user' => $params['Username'], 'pass' => $params['Password']], $originalParameters);
}

function inwx_CreateDomrobot($params) {
    $domrobot = (new Domrobot(null))->useJson();
    if($params['TestMode']) {
        $domrobot->useOte();
    } else {
        $domrobot->useLive();
    }

    return $domrobot;
}