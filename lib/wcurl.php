<?php
/**
 * PHP cURL wrapper tailored for use within F3 framework
 */
class wcurl extends \Prefab {

	private $version = 'v1.0';

	private $cb_login, $root, $tll, $cookie, $headers, $useragent,
			$basicauth, $queryToken;
	private $encodeJSON = true;

	private $curlopt, $rests, $stats = [];

	function __construct($identity = 'wcurl') {
		global $f3;

		if ( is_string($identity) && $f3->exists($identity) && is_array($f3->get($identity)) ){
			$options = $f3->get($identity);
		} else if( is_array($identity) ) {
			$options = $identity;
		} else {
			$f3->error(500, "Passed wcurl options array not found!");
		}

		$this->useragent = 'F3-wcurl '.$this->version;
		
		self::setOptions($options);

	}
	 
	 /**
	 * HTTP GET method
	 * 
	 * @param string		$url		Relative or named URL
	 * @param mixed			$fill		Array of url params to fill in
	 * @param array			$options	Override any setting for one request
	 */
	// public function get($url, $fill = null, $ttl = true){
	public function get($url, $fill = null, $options = []){

		$this->stats['request_types']['get']++;

		$url = self::fillRESTS($url, $fill);

		$cache = \Cache::instance();
		$key = \Web::instance()->slug($this->root.$url);
		$ttl = is_int($options['ttl'])?$options['ttl']: $this->ttl;

		if ($ttl && $cache->exists('url_'.$key,$value)) {
			$value['fromcache'] = true;
			$this->stats['requests_from_cache']++;
		    return $value;
		}
		$request = $this->curl_send([
				CURLOPT_URL => $url
		], $options);
		if($ttl && (substr($request['status']['http_code'],0,1)==2) ){
			// $cache->set('url_'.$key, $request, (is_int($ttl)&&$ttl>0)?$ttl:$this->ttl);
			$cache->set('url_'.$key, $request, $ttl);
		}

		$value['fromcache'] = false;
		return $request;
	}

	/**
	 * HTTP POST method
	 *
	 * @param string 	$url
	 * @param array 	$fill
	 * @param array 	$body
	 * @param array		$options	Override any setting for one request
	 * @return array
	 */
	// function post($url, $body = null, $fill = null, $encodeJSON = true){
	function post($url, $fill = null, $body = null, $options = []){

		$this->stats['request_types']['post']++;

		$url = self::fillRESTS($url, $fill);
		$encodeJSON = is_bool($options['encodeJSON'])?$options['encodeJSON']: $this->encodeJSON;

		if(is_array($body)){
			if($encodeJSON){
				$body = json_encode($body);
			} else {
				$body = http_build_query($body);
			}
		}
		return $this->curl_send([
				CURLOPT_URL => $url,
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $body
		], $options);
	}

	/**
	 * HTTP DELETE method
	 *
	 * @param string 	$url
	 * @param array 	$fill
	 * @param array 	$body
	 * @param array		$options	Override any setting for one request
	 * @return array
	 */
	function delete($url, $fill = null, $body = null, $options = []){

		$this->stats['request_types']['delete']++;

		$url = self::fillRESTS($url, $fill);
		$encodeJSON = is_bool($options['encodeJSON'])?$options['encodeJSON']: $this->encodeJSON;

		if(is_array($body)){
			if($encodeJSON){
				$body = json_encode($body);
			} else {
				$body = http_build_query($body);
			}
		}
		return $this->curl_send([
				CURLOPT_URL => $url,
				CURLOPT_POSTFIELDS => $body,
				CURLOPT_CUSTOMREQUEST => 'DELETE'
		], $options);
	}

	/**
	 * HTTP PUT method
	 *
	 * @param string 	$url
	 * @param array 	$fill
	 * @param array 	$body
	 * @param array		$options	Override any setting for one request
	 * @return array
	 */
	function put($url, $fill = null, $body = null, $options = []){

		$this->stats['request_types']['put']++;

		$url = self::fillRESTS($url, $fill);
		$encodeJSON = is_bool($options['encodeJSON'])?$options['encodeJSON']: $this->encodeJSON;

		if(is_array($body)){
			if($encodeJSON){
				$body = json_encode($body);
			} else {
				$body = http_build_query($body);
			}
		}
		return $this->curl_send([
				CURLOPT_URL => $url,
				CURLOPT_POSTFIELDS => $body,
				CURLOPT_CUSTOMREQUEST => 'PUT'
		], $options);
	}


	/**
	 * HTTP PATCH method
	 *
	 * @param string 	$url
	 * @param array 	$fill
	 * @param array 	$body
	 * @param array		$options	Override any setting for one request
	 * @return array
	 */
	function patch($url, $fill = null, $body = null, $options = []){

		$this->stats['request_types']['patch']++;

		$url = self::fillRESTS($url, $fill);
		$encodeJSON = is_bool($options['encodeJSON'])?$options['encodeJSON']: $this->encodeJSON;

		if(is_array($body)){
			if($encodeJSON){
				$body = json_encode($body);
			} else {
				$body = http_build_query($body);
			}
		}
		return $this->curl_send([
				CURLOPT_URL => $url,
				CURLOPT_POSTFIELDS => $body,
				CURLOPT_CUSTOMREQUEST => 'PATCH'
		], $options);
	}

	/**
	 * Change wcurl options by passing array of them. Will merge with existing config.
	 * 
	 * For initial configuration use new \wcurl
	 *
	 * @param array $options
	 */
	public function setOptions($options){
		global $f3;

		if(array_key_exists('root', $options)){
			$this->root = $options['root'];
			self::setCookie();
		}

		if(array_key_exists('cb_login', $options)) $this->cb_login = $options['cb_login'];

		if( isset($options['ttl']) && !self::setTTL($options['ttl']) ){
			$this->ttl = 60;
		}

		if(is_array($options['rests']))
			$this->rests = $options['rests'];

		if(is_array($options['curlopt']))
			$this->curlopt = $options['curlopt'];

		if($options['headers']){
			$this->headers = $options['headers'];
		}

		if(is_string($options['useragent'])) {
			$this->useragent = $options['useragent'];
		} //else {
			//$this->useragent = 'f3-wcurl '.$this->version;
		//}

		if(is_bool($options['encodeJSON']))
			$this->encodeJSON = $options['encodeJSON'];
	}

	/**
	 * Return current object configuration
	 * 
	 * Should contain all config used with set* functions
	 */
	public function getOptions(){
		$options = [];
		$options['root']=$this->root;
		$options['cb_login']=$this->cb_login;
		$options['ttl']=$this->ttl;
		$options['rests']=$this->rests;
		$options['curlopt']=$this->curlopt;
		$options['headers']=$this->headers;
		$options['useragent']=$this->useragent;
		$options['encodeJSON']=$this->encodeJSON;
		return $options;
	}

	/**
	 * Clear one or more settings
	 * 
	 * Can be passed as single option name or array of strings
	 *
	 * @param string|array $options
	 */
	public function clearOptions($options){
		if(is_string($options))
			$options = [$options];

		foreach($options as $opt){
			switch($opt){
				case 'root':
					$this->{$opt}=null;
					self::setCookie();
					break;
				case 'encodeJSON':
					$this->{$opt}=true;
					break;
				case 'cb_login':
				case 'ttl':
				case 'headers':
				case 'useragent':
				case 'basicauth':
				case 'queryToken':
					$this->{$opt}=null;
					break;
				case 'curlopt':
				case 'rests':
					$this->{$opt}=[];
					break;
			}
		}
	}

	/**
	 * Return stats of actions performed like total cURL requests sent
	 *
	 */
	public function getStats(){
		return $this->stats;
	}

	// HTTP request methods end

	/**
	  * Set callback function to handle authentication
	  *
	  * If any request results in HTTP 401 or 403 code, `wcurl` calls Login callback function and then repeats original request. If again there is error, it is returned to original function result. `wcurl` stores cookies in temporary file unique to API root. This cookie file is included in every request.
	  * Callback must return true, if login success, otherwise it would fail to auto-repeat request after auth success.
	  *
	  * **N!B! If login doesn't works, but still `return true`, it can cause `request->login->request->login...` infinitive loop**
	  *
	  * @param string $cb should be valid callback for `call_user_func()`
	  */
	public function setLogin($cb){
		$this->cb_login = $cb;
	}

	private function setCookie(){
		global $f3;
		$this->cookie = $f3->get('TEMP').'wcurl.cookie.'.
						\Web::instance()->slug($this->root).
						'.tmp';
		touch($this->cookie);
	}

	/**
	 * Set Basic Authentication
	 *
	 * @param string $user
	 * @param string $password
	 */
	public function setBasicAuth($user, $password){
			$this->basicauth = $user.':'.$password;
	}
	private function setTTL($int){
		if ((is_int($int) || ctype_digit($int)) && (int)$int > 0 ) {
			$this->ttl = $int;
			return true;
		}
		return false;
	}

	function setQueryToken($key, $value){
		if(is_string($key) && is_string($value))
			$this->queryToken = $key.'='.$value;
	}

	private function fillRESTS($url, $fill){
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

	private function curl_send($params, $extraOptions, $nested = false){
		global $f3;
		set_time_limit(30);

		/*
			Order, how settings should be applied,
			thus overriding/merging previous

			1. INI
			2. INI.curlopt
			3. EXTRA
			4. EXTRA.curlopt
		*/

		$options = self::getOptions();

		foreach($options['curlopt'] as $k => $v){
			unset($options['curlopt'][$k]);
			$options['curlopt'][is_int($k)?$k:constant($k)]=$v;
		}

		if( isset($extraOptions['curlopt']) ){
			foreach($extraOptions['curlopt'] as $k => $v){
				unset($extraOptions['curlopt'][$k]);
				$extraOptions['curlopt'][is_int($k)?$k:constant($k)]=$v;
			}
		}

		$mergedOptions = array_replace_recursive($options, $extraOptions);

		$curlopts = [
			CURLOPT_USERAGENT => $mergedOptions['useragent'],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_COOKIEJAR	=>	realpath($this->cookie),
			CURLOPT_COOKIEFILE	=>	realpath($this->cookie),
			CURLOPT_HTTP_VERSION=>	CURL_HTTP_VERSION_1_0,
			CURLINFO_HEADER_OUT => true,
		];

		if (strlen($mergedOptions['basicauth'])){
			unset($curlopts[CURLOPT_USERPWD]);
			$curlopts[CURLOPT_USERPWD]=$mergedOptions['basicauth'];
		}

		if(is_array($mergedOptions['headers'])){
			unset($curlopts[CURLOPT_HTTPHEADER]);
			$curlopts[CURLOPT_HTTPHEADER]=$mergedOptions['headers'];
		}

		$mergedOptions['curlopt']= array_replace_recursive($curlopts, $mergedOptions['curlopt']);


		// TODO do proper URL build combining root, path and proper appeding parameters after '?'
		$url =	trim($mergedOptions['root'],'/').
				'/'.trim($params[CURLOPT_URL],'/').
				($this->queryToken?'?'.$this->queryToken:'');

				
		// new object required so, in case of nested request, untouched origin can be passed again
		$setparams = $params;
		unset($setparams[CURLOPT_URL]);
		$setparams[CURLOPT_URL]=$url;

		$ch = curl_init();

		curl_setopt_array($ch, $mergedOptions['curlopt']);
		curl_setopt_array($ch, $setparams);

		// following function from Stack Overflow or smth
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
		$this->stats['curl_exec_count']++;
		$this->stats['http_code_count'][trim($status['http_code'])]++;

		if(!$nested){
			switch (trim($status['http_code'])){
				case 401:
				case 403:
					if($mergedOptions['cb_login']){
						if(call_user_func($mergedOptions['cb_login']))
							return $this->curl_send($params, $extraOptions, true);
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

	// helpers

	private function is_json($str) {
	    $json = json_decode($str);
	    return $json && $str != $json;
	}
}
