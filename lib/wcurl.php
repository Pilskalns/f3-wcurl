<?php
/**
 * PHP cURL wrapper tailored for use within F3 framework
 */
class wcurl extends \Prefab {

	private $version = 'v0.3';

	private $cb_login, $root, $tll, $cookie, $headers, $useragent,
			$basicauth, $queryToken;

	private $curlopt = [];
	private $rests = [];

	function __construct($identity = 'wcurl') {
		global $f3;

		if ( is_string($identity) && $f3->exists($identity) && is_array($f3->get($identity)) ){
			$options = $f3->get($identity);
		} else if( is_array($identity) ) {
			$options = $identity;
		} else {
			$f3->error(500, "Passed wcurl options array not found!");
		}

		self::setOptions($options);

	 }
	 
	 /**
	 * HTTP GET method
	 * 
	 * @param string		$url	Relative or named URL
	 * @param mixed			$fill	Array of url params to fill in
	 * @param mixed			$ttl	How long to cache response
	 */
	public function get($url, $fill = null, $ttl = true){

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

	/**
	 * HTTP POST method
	 *
	 * @param string $url
	 * @param array $body
	 * @param array $fill
	 * @param boolean $encodeJSON
	 * @return array
	 */
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

	/**
	 * HTTP DELETE method
	 *
	 * @param string $url
	 * @param array $body
	 * @param array $fill
	 * @param boolean $encodeJSON
	 * @return array
	 */
	function delete($url, $body = null, $fill = null, $encodeJSON = true){


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
				CURLOPT_POSTFIELDS => $body,
				CURLOPT_CUSTOMREQUEST => 'DELETE'
		));
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

		if(array_key_exists('root', $options)) $this->root = $options['root'];
		self::setCookie();

		if(array_key_exists('cb_login', $options)) $this->cb_login = $options['cb_login'];

		if( !self::setTTL($options['ttl']) ){
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
		} else {
			$this->useragent = 'f3-wcurl '.$this->version;
		}
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
				case 'cb_login':
				case 'tll':
				case 'cookie':
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

	private function curl_send($params = array(), $nested = false){
		global $f3;
		set_time_limit(30);

		$default = array(
			CURLOPT_USERAGENT => $this->useragent,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_COOKIEJAR	=>	realpath($this->cookie),
			CURLOPT_COOKIEFILE	=>	realpath($this->cookie),
			CURLOPT_HTTP_VERSION=>	CURL_HTTP_VERSION_1_0,
		);

		if (strlen($this->basicauth)){
			$default[CURLOPT_USERPWD]=$this->basicauth;
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
			curl_setopt($ch, CURLOPT_HTTPHEADER, 
						is_array($this->headers)?$this->headers:[$this->headers] );
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

		foreach( $this->curlopt as $opt=>$val ){
			curl_setopt($ch, constant($opt), constant($val));
		}

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

	// helpers

	private function is_json($str) {
	    $json = json_decode($str);
	    return $json && $str != $json;
	}
}
