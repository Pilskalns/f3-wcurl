<?php

class wcurl extends \Prefab {

	private $cb_login, $root, $tll, $cookie, $headers, $ua,
			$basicauth, $followLocation, $queryToken;

	private $version = 'v0.3';

	private $rests = [];

	function __construct($root = null, $cb_login = null, $ttl = null) {
		global $f3;

		$f3->exists('wcurl.root', $this->root);

		if($root){
			$this->root = $root;
		}

		self::setCookie();

		$f3->exists('wcurl.cb_login', $this->cb_login);

		if($cb_login){
			$this->cb_login = $cb_login;
		}

		if(!$f3->exists('wcurl.followlocation', $this->$followLocation)){
			$this->$followLocation = true;
		};


		if( !self::setTTL($ttl) && !self::setTTL($f3->get('wcurl.ttl')) ){
			$this->ttl = 60;
		}
		if(is_array($f3->get('wcurl.rests')))
			$this->rests = $f3->get('wcurl.rests');

		if( is_string($f3->get('wcurl.headers')) ){
			$this->headers = [trim($f3->get('wcurl.headers'),'\'\"')];
		} else if (is_array($f3->get('wcurl.headers'))){
			foreach ($f3->get('wcurl.headers') as $head ) {
				$this->headers[] = trim($head,'\'\"');
			}
		}
			// $f3->exists('wcurl.headers', $this->headers);

		$this->ua = 'f3-wcurl '.$this->version;
		if(is_string($f3->get('wcurl.useragent')))
			$this->ua = $f3->get('wcurl.useragent');

 	}

	function __destruct(){
	}

	function setLogin($cb){
		$this->cb_login = $cb;
	}
	function setRoot($root){
		$this->root = $root;
		self::setCookie();
	}
	private function setCookie(){
		global $f3;
		$this->cookie = $f3->get('TEMP').'wcurl.cookie.'.
						\Web::instance()->slug($this->root).
						'.tmp';
		touch($this->cookie);
	}
	function getCookie(){
		return $this->cookie;
	}

	function setRests($arr){
		if(is_array($arr))
			$this->rests = array_merge($this->rests, $arr);
	}
	function getRests(){
		return $this->rests;
	}
	function setHeaders($arr){
		if(is_array($arr))
			$this->headers = $arr;
	}
	function setBasicAuth($string){
		// if(strlen($string))
			$this->basicauth = $string;
	}
	function setTTL($int){
		if ((is_int($int) || ctype_digit($int)) && (int)$int > 0 ) {
			$this->ttl = $int;
			return true;
		}
		return false;
	}
	function setUserAgent($string){
		if(is_string($string))
			$this->ua = $string;
	}

	function setFollowLocation($bool){
		if(is_bool ($bool))
			$this->followLocation = $bool;
	}

	function setQueryToken($key, $token){
		if(is_string($key) && is_string($token))
			$this->queryToken = $key.'='.$token;
	}

	function fillRESTS($url, $fill){
		if(array_key_exists($url,$this->rests)){
			$url = $this->rests[$url];
		}
		if(is_null($fill))
			return $url;

		foreach ($fill as $key => $value) {
			$url = str_replace('%%'.$key.'%%', $value, $url);
		}

		// TODO regex search for unreplaced ID's and return in error message

		return $url;
	}

	function get($url, $fill = null, $ttl = true){

		$url = self::fillRESTS($url, $fill);

		$cache = \Cache::instance();
		$key = \Web::instance()->slug($this->root.$url);


		if ($ttl && $cache->exists('url_'.$key,$value)) {
			$value['fromcache'] = true;
		    return $value;
		}
		$request = $this->curl_send(array(
				CURLOPT_URL => $url
		));
		if($ttl && (substr($request['status']['http_code'],0,1)==2) ){
			$cache->set('url_'.$key, $request, (is_int($ttl)&&$ttl>0)?$ttl:$this->ttl);
		}

		$value['fromcache'] = false;
		return $request;
	}

	function post($url, $body = null, $fill = null, $encodeJSON = true){


		$url = self::fillRESTS($url, $fill);

		if(is_array($body)){
			if($encodeJSON){
				$body = json_encode($body);
			} else {
				$body = http_build_query($body);
			}
		}
		return $this->curl_send(array(
				CURLOPT_URL => $url,
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $body
		));
	}

	private function curl_send($params = array(), $nested = false){
		global $f3;
		set_time_limit(30);

		$cache = \Cache::instance();
		$key = \Web::instance()->slug($this->root.$url);

		$default = array(
			CURLOPT_USERAGENT => $this->ua,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_FOLLOWLOCATION => $this->followLocation,
			CURLOPT_COOKIEJAR	=>	realpath($this->cookie),
			CURLOPT_COOKIEFILE	=>	realpath($this->cookie),
			CURLOPT_HTTP_VERSION=>	CURL_HTTP_VERSION_1_0,
		);

		if (strlen($this->basicauth)){
			$default[CURLOPT_USERPWD]=$this->basicauth;
			// curl_setopt($process, CURLOPT_USERPWD, $username . ":" . $password)
		}

		$url =	trim($this->root,'/').
				'/'.trim($params[CURLOPT_URL],'/').
				($this->queryToken?'?'.$this->queryToken:'');

		$setparams = $params;
		unset($setparams[CURLOPT_URL]);
		$setparams[CURLOPT_URL]=$url;

		$ch = curl_init();

		curl_setopt_array($ch, $default);
		curl_setopt_array($ch, $setparams);

		if(is_array($this->headers)){
			curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
			curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		}

		$headers = [];
		curl_setopt($ch, CURLOPT_HEADERFUNCTION,
			function($curl, $header) use (&$headers){
				$len = strlen($header);
				$header = explode(':', $header, 2);
				if (count($header) < 2) // ignore invalid headers
					return $len;
				$name = strtolower(trim($header[0]));
				if (!array_key_exists($name, $headers))
					$headers[$name] = [trim($header[1])];
				else
					$headers[$name][] = trim($header[1]);
				return $len;
			}
		);

		$response = curl_exec($ch);
		$status = curl_getinfo($ch);

		if(!$nested){
			switch (trim($status['http_code'])){
				case 401:
				case 403:
					if($this->cb_login){
						if(call_user_func($this->cb_login))
							return $this->curl_send($params, true);
					}
					break;
			}
		}
		if($response !== false){
			$return = array('status' => $status,
							'response' => self::is_json($response)?json_decode($response, true):$response,
							'headers' => $headers );
		} else {
			$return = array('error'=>'Error: "'. curl_error($ch).
									'" - Code: '.curl_errno($ch),
							'status' => $status,
							'headers' => $headers );
		}

		curl_close($ch);
		return $return;

	}

	function is_json($str) {
	    $json = json_decode($str);
	    return $json && $str != $json;
	}
}
