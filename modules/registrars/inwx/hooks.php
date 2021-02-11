<?php

add_hook('ClientAreaPageDomainDNSManagement', 1, 'inwx_hook_ClientAreaPageDomainDNSManagement');

function inwx_hook_ClientAreaPageDomainDNSManagement(array $variables)
{
    $supported_dns_records['records'] = [
        'A' => 'A',
        'AAAA' => 'AAAA',
        'AFSDB' => 'AFSDB',
        'ALIAS' => 'ALIAS',
        'CAA' => 'CAA',
        'CERT' => 'CERT',
        'CNAME' => 'CNAME',
        'HINFO' => 'HINFO',
        'KEY' => 'KEY',
        'LOC' => 'LOC',
        'MX' => 'MX',
        'NAPTR' => 'NAPTR',
        'PTR' => 'PTR',
        'RP' => 'RP',
        'SOA' => 'SOA',
        'SRV' => 'SRV',
        'SSHFP' => 'SSHFP',
        'TLSA' => 'TLSA',
        'TXT' => 'TXT (SPF)',
        'URL' => 'URL Redirect',
        'FRAME' => 'URL FRAME'
    ];
    return $supported_dns_records;
}
