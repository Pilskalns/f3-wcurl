<?php

class wcurl extends \Prefab {

	private $cb_login, $rests;

	private $ttl = 60;

	function __construct($cb_login = null, $request_root = null,  $ttl = 60) {
		global $f3;
		if(!strlen($f3->get('wcurl_cookie'))){
			$f3->set('wcurl_cookie', $f3->get('TEMP').'wcurl_cookie.tmp' );
			touch($f3->get('wcurl_cookie'));
		}
		if($cb_login){
			$this->cb_login = $cb_login;
			call_user_func($cb_login);
		}
 	}

	function __destruct(){
		// if($this->ch){
		// 	curl_close($this->ch);
		// }
	}

	function setLogin($cb){
		$this->cb_login = $cb;
	}
	function setIsLogged($cb){
		$this->cb_islogged = $cb;
	}
	function setRests($arr){
		if(is_array($arr))
			$this->rests = $arr;
	}
	function setCachettl($int){
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

	function get($url, $fill = null, $usecache = true){

		$url = self::fillRESTS($url, $fill);

		// TODO if URL array, return $url;

		$cache = \Cache::instance();
		$key = \Web::instance()->slug($url);


		if ($usecache && $cache->exists('url_'.$key,$value)) {
			$value['fromcache'] = true;
		    return $value; // bar
		}
		$request = $this->curl_send(array(
				CURLOPT_URL => $url
		));
		if($usecache && (substr($request['status']['http_code'],0,1)==2) ){
			$cache->set('url_'.$key, $request, $this->ttl);
		}

		$value['fromcache'] = false;
		return $request;
	}
	function post($url, $body = null){

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

	function curl_send($params = array(), $nested = false){
		global $f3;

		$default = array(
			CURLOPT_USERAGENT => 'Zeus was here',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_COOKIEJAR	=>	realpath($f3->get('wcurl_cookie')),
			CURLOPT_COOKIEFILE	=>	realpath($f3->get('wcurl_cookie')),
		);

		$url =	trim($f3->get('nimbus.root'),'/').
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
