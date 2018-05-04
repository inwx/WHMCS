<?php
add_hook('ClientAreaPageDomainDNSManagement', 1, "set_dns_entries");

function set_dns_entries($vars) {
$supported_dns_records['records'] = array('A' => 'A (Address)', 'AAAA' => 'AAAA (Address)', 'MXE' => 'MXE (Mail Easy)', 'MX' => 'MX', 'CNAME' => 'CNAME', 'TXT' => 'TXT (SPF)', 'URL'=>'URL Redirect', 'FRAME' => 'URL FRAME', 'SRV'=>'SRV');
return $supported_dns_records;
}
