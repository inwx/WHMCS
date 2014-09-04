<?php
ini_set("display_errors", 1);

$registrar = "internetworx";

include_once dirname(__FILE__)."/../../../dbconnect.php";
include_once dirname(__FILE__)."/../../../includes/functions.php";
include_once dirname(__FILE__)."/../../../includes/registrarfunctions.php";
include_once "$registrar.php";

$result = full_query("
	SELECT id, domain, nextduedate, status FROM tbldomains
	WHERE registrar='$registrar'");

$data = array();
while ( $row = mysql_fetch_array($result, MYSQL_ASSOC) ) {
	$data[$row["domain"]] = array("id"=>$row["id"],"nextduedate"=>$row["nextduedate"],"status"=>$row["status"]);
}

$pDomain['page'] = 0;
$pDomain['pagelimit'] = 0;
$pDomain['domain'] = array_keys($data);
if (count($pDomain['domain'])<1) {
	exit;
}
$params = getregistrarconfigoptions($registrar);
$domrobot = new domrobot($params["Username"],$params["Password"],$params["TestMode"]);
$response = $domrobot->call('domain','list',$pDomain);
if ($response['code']==1000 && isset($response['resData']['domain']) && $response['resData']['count']>0) {
	for($i=0;$i<count($response['resData']['domain']);$i++) {
		$domain = $response['resData']['domain'][$i]['domain'];
		$status = $response['resData']['domain'][$i]['status'];
		$exDate = (isset($response['resData']['domain'][$i]['exDate']))?date('Y-m-d',$response['resData']['domain'][$i]['exDate']->timestamp):null;
		$reDate = (isset($response['resData']['domain'][$i]['reDate']))?date('Y-m-d',$response['resData']['domain'][$i]['reDate']->timestamp):null;
		
		if ($status!='OK' || !isset($data[$domain]) || empty($exDate) || empty($reDate)) {
			continue;
		}
		$rowId = $data[$domain]['id'];
		
		$whmcsDomainStatus = strtolower($data[$domain]['status']);
		if ($whmcsDomainStatus!='active' && $status=='OK') {
			print "$domain - ACTIVE\n";
			full_query ("
				UPDATE tbldomains
				SET status='Active'
				WHERE id='".db_escape_string($rowId)."'
				LIMIT 1
			");
		}

		$nextDueDate = $data[$domain]['nextduedate'];
		if ($reDate != $nextDueDate ) {
			print "$domain - $exDate - $reDate\n";
			full_query ("
				UPDATE tbldomains
				SET expirydate='".db_escape_string($exDate)."',
				nextinvoicedate=DATE_ADD(nextinvoicedate, INTERVAL (UNIX_TIMESTAMP('".db_escape_string($reDate)."')-UNIX_TIMESTAMP(nextduedate)) SECOND),
				nextduedate='".db_escape_string($reDate)."'
				WHERE nextduedate !='".db_escape_string($reDate)."'
				AND id='".db_escape_string($rowId)."'
				LIMIT 1
			");
		}
	}
}
?>