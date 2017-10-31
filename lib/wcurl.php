<?php

class wcurl extends \Prefab {

	private $cb_login, $root, $tll, $cookie;

	private $rests = array();

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
			// call_user_func($cb_login);
		}

		if( !self::setTTL($ttl) && !self::setTTL($f3->get('wcurl.ttl')) ){
			$this->ttl = 60;
		}
		if(is_array($f3->get('wcurl.rests')))
			$f3->exists('wcurl.rests', $this->rests);


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

	function setRests($arr){
		if(is_array($arr))
			$this->rests = array_merge($this->rests, $arr);
	}
	function getRests(){
		return $this->rests;
	}
	function setTTL($int){
		if ((is_int($int) || ctype_digit($int)) && (int)$int > 0 ) {
			$this->ttl = $int;
			return true;
		}
		return false;
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

	function post($url, $body = null, $fill = null){

		$url = self::fillRESTS($url, $fill);

		if(is_array($body)){
			$body = json_encode($body);
		}

		return $this->curl_send(array(
				CURLOPT_URL => $url,
				CURLOPT_POST => true,
				CURLOPT_HTTPHEADER	=> 	array('Content-Type: application/json' ),
				CURLOPT_POSTFIELDS => $body
		));
	}

	private function curl_send($params = array(), $nested = false){
		global $f3;

		$default = array(
			CURLOPT_USERAGENT => 'Zeus was here',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_COOKIEJAR	=>	realpath($this->cookie),
			CURLOPT_COOKIEFILE	=>	realpath($this->cookie),
		);

		$url =	trim($this->root,'/').
				'/'.trim($params[CURLOPT_URL],'/');

		$setparams = $params;
		unset($setparams[CURLOPT_URL]);
		$setparams[CURLOPT_URL]=$url;

		$ch = curl_init();

		curl_setopt_array($ch, $default);
		curl_setopt_array($ch, $setparams);

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
						call_user_func($this->cb_login);
						return $this->curl_send($params, true);
					}
					break;
			}
		}
		if($response){
			$return = array('status' => $status,
							'response' => $response,
							'headers' => $headers );
		} else {
			$return = array('error'=>'Error: "'. curl_error($ch).
									'" - Code: '.curl_errno($ch)	);
		}

		curl_close($ch);
		return $return;

	}
}
