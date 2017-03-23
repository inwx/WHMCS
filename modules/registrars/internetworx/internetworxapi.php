<?php

class domrobot {
	private $_ver = "1.1";
	private $address = null;
	private $lng = 'en';
	private $_inwxhash = null;
	
	function __construct($username,$password,$ote='off') {
		if ($ote=='on') {
			$this->address = "https://api.ote.domrobot.com/xmlrpc/";
		} else {
			$this->address = "https://api.domrobot.com/xmlrpc/";
		}
		$retLogin = $this->login($username,$password);
		if (!$retLogin) {
			return false;
		} else {
			return true;
		}
	}

	private function login($username,$password) {
		$params['user'] = $username;
		$params['pass'] = $password; 
		$params['lang'] = $this->lng; 
		$ret = $this->call('account','login',$params);
		if ($ret['code']==1000) {
			return true;
		} else {
			return false;
		}
	}

	public function call($object, $method, $params=array()) {
		$action = strtolower("$object.$method");
		$request = xmlrpc_encode_request($action, $params, array("encoding"=>"UTF-8","escaping"=>"markup","verbosity"=>"no_white_space"));

		$header[] = "Content-Type: text/xml";   
		$header[] = "Connection: keep-alive";
		$header[] = "Keep-Alive: 300";
		$header[] = "X-FORWARDED-FOR: ".@$_SERVER['HTTP_X_FORWARDED_FOR'];
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL,$this->address);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
		curl_setopt($ch,CURLOPT_TIMEOUT,30);
        curl_setopt($ch,CURLOPT_MAXREDIRS,2);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);
		curl_setopt($ch,CURLOPT_HEADERFUNCTION,array($this, 'read_header'));
		curl_setopt($ch,CURLOPT_HTTPHEADER,$header);
		curl_setopt($ch,CURLOPT_COOKIE,$this->_inwxhash);		
		curl_setopt($ch,CURLOPT_POST,1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$request);
		curl_setopt($ch,CURLOPT_USERAGENT,"WHMCS/{$this->_ver} (PHP ".phpversion().")");
		
		$_SiteResponse = curl_exec($ch);
		curl_close($ch); 

		$response = xmlrpc_decode($_SiteResponse,'UTF-8');

		// https://developers.whmcs.com/provisioning-modules/module-logging/
		logModuleCall('internetworx', $action, $request, $_SiteResponse, $response, array('pass'));

		return $response;
	}

	private function read_header($ch,$string) {
		if (preg_match('/^Set-Cookie:/i', $string)) {
			$cookiestr = trim(substr($string, 11, -1));
			$cookie = explode(';', $cookiestr);
			$cookie = explode('=', $cookie[0]);
			$cookiename = trim(array_shift($cookie)); 
      		$cookiearr[$cookiename] = trim(implode('=', $cookie));

      		foreach ($cookiearr as $key=>$value) {
				$cookie = "$key=$value";
			}
			$_SESSION['inwxhash'] = $this->_inwxhash = $cookie;
		}
		return strlen($string);
	}
	
	public function getErrorMsg($response) {
		$msg = "";
		if (!is_array($response) || !isset($response['code'])) {
			$msg = "Fatal API Error occurred!";
		} elseif ($response['code']==1000) {
			$msg = "";
		} elseif (isset($response['resData']['reason'])) {
			$msg = $response['resData']['reason'];
		} elseif (isset($response['reason'])) {
			$msg = $response['reason'];
		} elseif (isset($response['msg'])) {
			$msg = $response['msg']." (EPP: ".$response['code'].")";
		}
		return $msg;
	}
}
?>
