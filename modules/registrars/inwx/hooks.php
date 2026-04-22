<?php

require_once 'helpers.php';
require_once 'version_check.php';

add_hook('ClientAreaPageDomainDNSManagement', 1, 'inwx_hook_ClientAreaPageDomainDNSManagement');
add_hook('AdminAreaHeaderOutput', 1, 'inwx_hook_AdminAreaHeaderOutput');

function inwx_hook_ClientAreaPageDomainDNSManagement(array $variables)
{
    $records = [];
    $config = inwx_getModuleConfig();
    foreach (inwx_GetEnabledRecordTypes($config) as $recordType) {
        $records[$recordType] = $recordType;
    }

    $supportedDNSRecords['records'] = $records;

    return $supportedDNSRecords;
}
