<?php

require_once 'helpers.php';

add_hook('ClientAreaPageDomainDNSManagement', 1, 'inwx_hook_ClientAreaPageDomainDNSManagement');

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
