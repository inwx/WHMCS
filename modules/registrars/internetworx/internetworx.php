<?php

include_once 'internetworxapi.php';

function internetworx_Sync($params)
{
    $params = injectDomainObjectIfNecessary($params);
    $values = [];
    // call domrobot
    $domrobot = new domrobot($params['Username'], $params['Password'], $params['TestMode']);
    $response = $domrobot->call('domain', 'info', ['domain' => $params['domainObj']->getDomain()]);
    if ($response['code'] == 1000 && isset($response['resData']['domain'])) {
        $exDate = (isset($response['resData']['exDate']) ? date('Y-m-d', $response['resData']['exDate']->timestamp) : null);
        $reDate = (isset($response['resData']['reDate']) ? date('Y-m-d', $response['resData']['reDate']->timestamp) : null);

        // set expiration date if available
        if (!is_null($exDate)) {
            $values['expirydate'] = $exDate;
        }

        // change domain-status if domain is active
        if ($response['resData']['status'] == 'OK') {
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

function internetworx_getConfigArray()
{
    $configarray = [
        'Username' => ['Type' => 'text', 'Size' => '20', 'Description' => 'Enter your InterNetworX username here'],
        'Password' => ['Type' => 'password', 'Size' => '20', 'Description' => 'Enter your InterNetworX password here'],
        'TestMode' => ['Type' => 'yesno', 'Description' => 'Connect to OTE (Test Environment). Your credentials may differ.'],
        'TechHandle' => ['Type' => 'text', 'Description' => 'Enter your default contact handle id for tech contact.<br/>.DE domains require a fax number for the tech contact. Since WHMCS does not provide a field for this, you can manually create a contact with a fax number in the InterNetworX webinterface, and specify the handle here.<br/>(You can use our default Tech/Billing contact handle: 1).'],
        'BillingHandle' => ['Type' => 'text', 'Description' => 'Enter your default contact handle id for billing contact.<br/>.DE domains require a fax number for the billing contact. Since WHMCS does not provide a field for this, you can manually create a contact with a fax number in the InterNetworX webinterface, and specify the handle here.<br/>(You can use our default Tech/Billing contact handle: 1).'],
    ];
    return $configarray;
}

function internetworx_GetRegistrarLock($params)
{
    if (isset($params['original'])) {
        $params = $params['original'];
    }
    $lockstatus = 'locked';
    $domrobot = new domrobot($params['Username'], $params['Password'], $params['TestMode']);

    $pDomain['domain'] = $params['sld'] . '.' . $params['tld'];
    $pDomain['wide'] = 1;

    $response = $domrobot->call('domain', 'info', $pDomain);

    if ($response['code'] == 1000 && isset($response['resData']['transferLock'])) {
        if ($response['resData']['transferLock'] == 1) {
            $lockstatus = 'locked';
        } elseif ($response['resData']['transferLock'] == 0) {
            $lockstatus = 'unlocked';
        } else {
            $lockstatus = '';
        }
        return $lockstatus;
    } else {
        return ['error' => $domrobot->getErrorMsg($response)];
    }
}

function internetworx_SaveRegistrarLock($params)
{
    $params = injectDomainObjectIfNecessary($params);
    $values = ['error' => ''];
    $domrobot = new domrobot($params['Username'], $params['Password'], $params['TestMode']);

    $pDomain['domain'] = $params['domainObj']->getDomain();
    $pDomain['transferLock'] = ($params['lockenabled'] == 'locked') ? 1 : 0;

    $response = $domrobot->call('domain', 'update', $pDomain);
    $values['error'] = $domrobot->getErrorMsg($response);
    return $values;
}

function internetworx_GetEPPCode($params)
{
    if (isset($params['original'])) {
        $params = $params['original'];
    }

    $values = ['error' => ''];
    $domrobot = new domrobot($params['Username'], $params['Password'], $params['TestMode']);

    $pDomain['domain'] = $params['sld'] . '.' . $params['tld'];
    $pDomain['wide'] = 1;

    $response = $domrobot->call('domain', 'info', $pDomain);

    if ($response['code'] == 1000) {
        if (isset($response['resData']['authCode'])) {
            $values['eppcode'] = htmlspecialchars($response['resData']['authCode']);
        } else {
            $values['eppcode'] = '';
        }
    } else {
        $values['error'] = $domrobot->getErrorMsg($response);
    }
    return $values;
}

function internetworx_GetNameservers($params)
{
    if (isset($params['original'])) {
        $params = $params['original'];
    }

    $values = ['error' => ''];
    $domrobot = new domrobot($params['Username'], $params['Password'], $params['TestMode']);

    $pDomain['domain'] = $params['sld'] . '.' . $params['tld'];
    $pDomain['wide'] = 1;

    $response = $domrobot->call('domain', 'info', $pDomain);
    if ($response['code'] == 1000 && isset($response['resData']['ns'])) {
        for ($i = 1; $i <= 4; ++$i) {
            $values['ns' . $i] = (isset($response['resData']['ns'][($i - 1)])) ? htmlspecialchars($response['resData']['ns'][($i - 1)]) : '';
        }
    } else {
        $values['error'] = $domrobot->getErrorMsg($response);
    }

    return $values;
}

function internetworx_SaveNameservers($params)
{
    if (isset($params['original'])) {
        $params = $params['original'];
    }
    $values = ['error' => ''];
    $domrobot = new domrobot($params['Username'], $params['Password'], $params['TestMode']);

    $pDomain['domain'] = $params['sld'] . '.' . $params['tld'];
    $pDomain['ns'] = [];
    for ($i = 1; $i <= 4; ++$i) {
        if (isset($params['ns' . $i]) && !empty($params['ns' . $i])) {
            $pDomain['ns'][] = $params['ns' . $i];
        }
    }

    $response = $domrobot->call('domain', 'update', $pDomain);
    $values['error'] = $domrobot->getErrorMsg($response);

    return $values;
}

function internetworx_GetDNS($params)
{
    if (isset($params['original'])) {
        $params = $params['original'];
    }
    $values = ['error' => ''];
    $hostrecords = [];
    $domrobot = new domrobot($params['Username'], $params['Password'], $params['TestMode']);

    $pInfo['domain'] = $params['sld'] . '.' . $params['tld'];
    $response = $domrobot->call('nameserver', 'info', $pInfo);

    if ($response['code'] == 1000 && isset($response['resData']['record']) && count($response['resData']['record']) > 0) {
        $_allowedRecTypes = ['A', 'AAAA', 'CNAME', 'MX', 'SPF', 'TXT', 'URL', 'SRV'];
        foreach ($response['resData']['record'] as $_record) {
            if (in_array($_record['type'], $_allowedRecTypes)) {
                if ($_record['type'] == 'URL') {
                    $_record['type'] = (isset($_record['urlRedirectType']) && $_record['urlRedirectType'] == 'FRAME') ? 'FRAME' : 'URL';
                }
                $hostrecords[] = ['hostname' => $_record['name'], 'type' => $_record['type'], 'address' => $_record['content'], 'priority' => $_record['prio']];
            }
        }
    } else {
        $values['error'] = $domrobot->getErrorMsg($response);
    }

    return $hostrecords;
}

function internetworx_SaveDNS($params)
{
    if (isset($params['original'])) {
        $params = $params['original'];
    }
    $values = ['error' => ''];
    $domrobot = new domrobot($params['Username'], $params['Password'], $params['TestMode']);

    $pInfo['domain'] = $params['sld'] . '.' . $params['tld'];
    $response = $domrobot->call('nameserver', 'info', $pInfo);
    $_records = [];
    if ($response['code'] == 1000 && isset($response['resData']['record']) && count($response['resData']['record']) > 0) {
        $_allowedRecTypes = ['A', 'AAAA', 'CNAME', 'MX', 'SPF', 'TXT', 'URL', 'SRV'];
        foreach ($response['resData']['record'] as $_record) {
            if (in_array($_record['type'], $_allowedRecTypes)) {
                $_records[] = ['id' => $_record['id']];
            }
        }
    } elseif ($response['code'] != 1000) {
        $values['error'] = $domrobot->getErrorMsg($response);
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
        if ($val['priority'] != 'N/A' && is_numeric($val['priority'])) {
            $pRecord['prio'] = $val['priority'];
        }
        if ($pRecord['type'] == 'URL') {
            $pRecord['urlRedirectType'] = 'HEADER301';
        } elseif ($pRecord['type'] == 'FRAME') {
            $pRecord['type'] = 'URL';
            $pRecord['urlRedirectType'] = 'FRAME';
            $pRecord['urlRedirectTitle'] = '';
        }

        if (empty($pRecord['id']) || $pRecord['id'] < 1) {
            unset($pRecord['id']);
            $pRecord['domain'] = $params['sld'] . '.' . $params['tld'];
            $response = $domrobot->call('nameserver', 'createrecord', $pRecord);
        } else {
            $response = $domrobot->call('nameserver', 'updaterecord', $pRecord);
        }
        $values['error'] = (empty($values['error'])) ? $domrobot->getErrorMsg($response) : $values['error'];
    }

    return $values;
}

function internetworx_GetContactDetails($params)
{
    if (isset($params['original'])) {
        $params = $params['original'];
    }

    $values = [];
    $domrobot = new domrobot($params['Username'], $params['Password'], $params['TestMode']);

    $pDomain['domain'] = $params['sld'] . '.' . $params['tld'];
    $pDomain['wide'] = 2;

    $response = $domrobot->call('domain', 'info', $pDomain);
    $contactTypes = ['registrant' => 'Registrant', 'admin' => 'Admin', 'tech' => 'Technical', 'billing' => 'Billing'];
    if ($response['code'] == 1000) {
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
    } else {
    }
    return $values;
}

function internetworx_SaveContactDetails($params)
{
    $params = injectDomainObjectIfNecessary($params);
    $values = [];
    $domrobot = new domrobot($params['Username'], $params['Password'], $params['TestMode']);

    $pDomain['domain'] = $params['domainObj']->getDomain();
    $response = $domrobot->call('domain', 'info', $pDomain);
    if ($response['code'] == 1000) {
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
                $values['error'] = $domrobot->getErrorMsg($response);
            } else {
                $pContact['id'] = $contactIds[$type];
                $response = $domrobot->call('contact', 'update', $pContact);
                $values['error'] = $domrobot->getErrorMsg($response);
            }
        }
        if (count($pDomain) > 1) {
            $response = $domrobot->call('domain', 'update', $pDomain);
            $values['error'] = $domrobot->getErrorMsg($response);
        }
    } else {
        $values['error'] = $domrobot->getErrorMsg($response);
    }
    return $values;
}

function internetworx_RegisterNameserver($params)
{
    $values = ['error' => ''];
    $domrobot = new domrobot($params['Username'], $params['Password'], $params['TestMode']);

    $pHost['hostname'] = $params['nameserver'];
    $pHost['ip'] = $params['ipaddress'];

    $response = $domrobot->call('host', 'create', $pHost);
    $values['error'] = $domrobot->getErrorMsg($response);

    return $values;
}

function internetworx_ModifyNameserver($params)
{
    if (isset($params['original'])) {
        $params = $params['original'];
    }
    $values = ['error' => ''];
    $domrobot = new domrobot($params['Username'], $params['Password'], $params['TestMode']);

    $pHost['hostname'] = $params['nameserver'];
    $pHost['ip'] = $params['newipaddress'];

    $response = $domrobot->call('host', 'update', $pHost);
    $values['error'] = $domrobot->getErrorMsg($response);

    return $values;
}

function internetworx_DeleteNameserver($params)
{
    if (isset($params['original'])) {
        $params = $params['original'];
    }
    $values = ['error' => ''];
    $domrobot = new domrobot($params['Username'], $params['Password'], $params['TestMode']);

    $pHost['hostname'] = $params['nameserver'];

    $response = $domrobot->call('host', 'delete', $pHost);
    $values['error'] = $domrobot->getErrorMsg($response);

    return $values;
}

function internetworx_RegisterDomain($params)
{
    $params = injectDomainObjectIfNecessary($params);
    $values = ['error' => ''];
    $domrobot = new domrobot($params['Username'], $params['Password'], $params['TestMode']);

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
    if (($response['code'] == 1000 || $response['code'] == 1001)) {
        $pDomain['registrant'] = $response['resData']['id'];
    } else {
        $values['error'] = $domrobot->getErrorMsg($response);
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
    if (($response['code'] == 1000 || $response['code'] == 1001)) {
        $pDomain['admin'] = $response['resData']['id'];
    } else {
        $values['error'] = $domrobot->getErrorMsg($response);
        return $values;
    }

    // 	Register Domain
    $pDomain['domain'] = $params['domainObj']->getDomain();
    $pDomain['renewalMode'] = ($params['tld'] == 'at' || substr($params['tld'], -3) == '.at') ? 'AUTODELETE' : 'AUTOEXPIRE';
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
            if (isset($addField['InwxName']) && isset($params['additionalfields'][$addField['InwxName']])) {
                switch ($addField['Type']) {
                    case 'text':
                        $pDomain['extData'][$addField['InwxName']] = $params['additionalfields'][$addField['InwxName']];
                        break;
                    case 'tickbox':
                        if ($params['additionalfields'][$addField['InwxName']] == 'on') {
                            $pDomain['extData'][$addField['InwxName']] = 1;
                        }
                        break;
                    case 'dropdown':
                        $_whmcsOptions = explode(',', $addField['Options']);
                        $_inwxOptions = explode(',', $addField['InwxOptions']);
                        $_key = array_search($params['additionalfields'][$addField['InwxName']], $_whmcsOptions);
                        $pDomain['extData'][$addField['InwxName']] = $_inwxOptions[$_key];
                        break;
                }
            }
        }
    }

    // create nameserver
    if (count($pDomain['ns']) > 0 && $params['dnsmanagement'] == 1) {
        $pNs['domain'] = $pDomain['domain'];
        $pNs['type'] = 'MASTER';
        $pNs['ns'] = $pDomain['ns'];
        $response = $domrobot->call('nameserver', 'create', $pNs);
    }

    // do domain create command
    $response = $domrobot->call('domain', 'create', $pDomain);
    $values['error'] = $domrobot->getErrorMsg($response);

    return $values;
}

function internetworx_TransferDomain($params)
{
    $params = injectDomainObjectIfNecessary($params);
    $values = ['error' => ''];
    $domrobot = new domrobot($params['Username'], $params['Password'], $params['TestMode']);

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
    if (($response['code'] == 1000 || $response['code'] == 1001)) {
        $pDomain['registrant'] = $response['resData']['id'];
    } else {
        $values['error'] = $domrobot->getErrorMsg($response);
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
    if (($response['code'] == 1000 || $response['code'] == 1001)) {
        $pDomain['admin'] = $response['resData']['id'];
    } else {
        $values['error'] = $domrobot->getErrorMsg($response);
        return $values;
    }

    // 	Transfer Domain
    $pDomain['domain'] = $params['domainObj']->getDomain();
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
    $values['error'] = $domrobot->getErrorMsg($response);

    return $values;
}

function internetworx_RenewDomain($params)
{
    if (isset($params['original'])) {
        $params = $params['original'];
    }
    $values = ['error' => ''];
    $domrobot = new domrobot($params['Username'], $params['Password'], $params['TestMode']);

    $pDomain['domain'] = $params['sld'] . '.' . $params['tld'];

    $response = $domrobot->call('domain', 'info', $pDomain);

    if (($response['code'] == 1000 || $response['code'] == 1001) && isset($response['resData']['exDate'])) {
        $pDomain['expiration'] = date('Y-m-d', $response['resData']['exDate']->timestamp);
    } else {
        $values['error'] = $domrobot->getErrorMsg($response);
        return $values;
    }

    $pDomain['period'] = $params['regperiod'] . 'Y';
    $response = $domrobot->call('domain', 'renew', $pDomain);
    $values['error'] = $domrobot->getErrorMsg($response);

    return $values;
}

/*
 * the following functions are not yet supported!
 *
function internetworx_GetEmailForwarding($params) {
    $username = $params["Username"];
    $password = $params["Password"];
    $testmode = $params["TestMode"];
    $tld = $params["tld"];
    $sld = $params["sld"];
    # Put your code to get email forwarding here - the result should be an array of prefixes and forward to emails (max 10)
    foreach ($result AS $value) {
        $values[$counter]["prefix"] = $value["prefix"];
        $values[$counter]["forwardto"] = $value["forwardto"];
    }
    return $values;
}

function internetworx_SaveEmailForwarding($params) {
    $username = $params["Username"];
    $password = $params["Password"];
    $testmode = $params["TestMode"];
    $tld = $params["tld"];
    $sld = $params["sld"];
    foreach ($params["prefix"] AS $key=>$value) {
        $forwardarray[$key]["prefix"] =  $params["prefix"][$key];
        $forwardarray[$key]["forwardto"] =  $params["forwardto"][$key];
    }
    # Put your code to save email forwarders here
}
*/
