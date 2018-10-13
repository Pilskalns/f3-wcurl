# F3-wcurl

master | dev
-------|-----
[![Master Status](https://travis-ci.com/Pilskalns/f3-wcurl.svg?branch=master)](https://travis-ci.com/Pilskalns/f3-wcurl) | [![Dev Status](https://travis-ci.com/Pilskalns/f3-wcurl.svg?branch=dev)](https://travis-ci.com/Pilskalns/f3-wcurl)

A Fat Free Framework plugin: Bridge between your code and external REST API. F3-wcurl acts as a logical abstraction layer for cURL, which handles authentication and sucess response caching.

F3 built-in `Web` plugin is great and easy to handle individual HTTP requests. F3-wcurl builds implementation of the whole remote API inside your code.

##### Preface

Over the time I've had need to quickly build tools and scripts that had one essential, but repetetive task with them: to handle cURL requests, settings, objects, responses etc. In the end, I kept copying the same functions, modifying them and debbuging the same issues _why something isn't working written months ago_.

Even though you have great control over and access to cURL options, F3-wcurl does not force you to do so. It lets focus on the request itself, what does it change and receive in return. As a plugin for F3 ecosystem, it naturally got few cool dependencies - `Prefab`, `Cache` and `Web` (not for cURL requests themselves).

### Iniate the class

F3 class is built from associative array of relevant settings. Array can be passed from either code directly, or either from INI file (imported to F3 before building F3-wcurl) and stored in the [F3 hive](https://fatfreeframework.com/3.6/base).

F3-wcurl uses F3's Prefab and Cache, which allows same wcurl object to be called anywhere from the code and quick response turnover.

On default, F3-wcurl will search for 'wcurl' key in F3 hive, but INI can conviniently hold settings for multiple different REST API implementations.

``` php
$wcurl = \wcurl::instance([$iniName = 'wcurl' | $optionsArray]);
```

----

### Options array

This fairly simple array structure defines inner workings of wcurl:

name 		| type 		| default 	| description
----		|-----		|--------	|------------
**root**	| string	| _null_	| Remote API root, which is used to build request URI.
cb_login	| string	| _null_	| Set callback function from your code, which can perform authentication, which should be valid callback for `call_user_func()`
ttl			| integer	| 60		| Seconds, how long to cache GET responses
headers		| array		| [ ]		| [cURL valid array of strings](http://php.net/manual/en/function.curl-setopt.php) ["Header: value", "Another-Header: Value"]
useragent	| string	| F3-wcurl version| Useragent string
basicauth	| string	| _null_	| Send Basic Auth headers, use in format `username:password`
queryToken	| string	| _null_	| Append each request with URL token
encodeJSON	| boolean	| true		| Weather serialize POST body as JSON, setting `false` will send body as regular HTML form
curlopt		| array		| [ ]		| [RAW cURL settings](http://php.net/manual/en/function.curl-setopt.php) RAW cURL settings array for outright control, with  `key => val` where key can be either constant or either string name of it
rests		| array		| [ ]		| `key => val` table for URL building helpers (see Examples section)

### updating options

#### setOptions
To set any option from table above, pass `key => val` array with one or more options.

``` php
$wcurl->setOptions(
	[
		'useragent' 	= > 'F3-wcurl API integration',
		'encodeJSON' 	= > false,
		'ttl' 			= > 300,
		// etc
	]
);
```
Only options you pass will be updated, everything else will stay in previous/default state.

#### clearOptions
To clear one or more options, pass name or array list of keys you wish to reset to defaults:

``` php
$wcurl->clearOptions([ 'useragent', 'ttl' /*, ... etc */ ]);
```
#### getOptions
To get full array of options that represents current state of wcurl class:

``` php
$wcurl->clearOptions();
```
Returned multi-dimension array should be compatible to create exactly the same class as the one extracted from.

#### getStats

Will return statistics how many requests are executed since class is created, what/how many http responses received and how many served from cache.

``` php
$wcurl->getStats();
```

------------------

### HTTP functions

#### GET

``` php
$response = $wcurl->get( string $url [, array $fill = null [, array $options = null ]] );
```

I memorize arguments like this.

UFO:
* **U**rl - Properly would be called the **PATH** of the URL, or identefier from _rests_ table (see below).
* **F**ill - Array of values to fill in the _rests_ path
* **O**ptions - Force any option for this request exclusively


#### POST

``` php
$response = $wcurl->post( string $url, array $body = null [, array $fill = null [, array $options = null ]] );
```

UFBO - Url, Fill, Body, Options

#### DELETE

``` php
$response = $wcurl->delete( string $url, array $body = null [, array $fill = null [, array $options = null ]] );
```

UFBO - Url, Fill, Body, Options

------------------

### Using table of _rests_ (REST pointS)

Rests table serves two equally important purposes:

1. To shorthand API paths avoiding typos of long strings
2. To fill in parts of those strings to build dynamic API call paths 

When constructing long remote URL paths, it's easier to remember them by short keywords, especially if they are called from multiple places. Like `allmembers` instead of `/lists/members/all/pages`. Sometimes these contain also unique parameters, which needs to be filled in per each request. **This concept is one of main reasons why this plugin exists.**

Keep on reading.

#### Set named paths

The best ways is to store remote paths in the `.ini` configuration. URL variables for fill are wrapped in two `%` from both sides.

TODO: Make this wrap character configurable.

``` ini
[wcurl.rests]
allmembers=/lists/members/all/pages
withVariable=/lists/members/%%memberID%%/pages
```

or pass simple `key => value` array on the fly - it will be **merged** over with previous configuration

``` php
$wcurl->setOptions(
	'rests' => [
		'allmembers'	=> '/lists/members/all/pages',
		'withVariable'	=> '/lists/members/%%memberID%%/pages',
		'updateEmail'	=> '/lists/members/%%memberID%%/update'
	]
);
```

#### Use named paths

To use named route, pass it's name instead of full path

``` php
$response = $wcurl->get( 'allmembers' );
```

This will resolve to `/lists/members/all/pages`

#### Use named paths with variables

``` php
$response = $wcurl->get( 'withVariable', array('memberID' => 'abc123ID') );
```

This will resolve to `/lists/members/abc123ID/pages`


Or in the POST request we know that have to pass following _UFBO_ parameters

``` php
$wcurl->post('updateEmail', 					// path shorthand to resolve name
			[ 'memberID' =>'abc123ID' ], 		// fill this in the path
			[ 'email'=>'andzs@pilskalns.lv' ] 	// body to send
		);
```

------------------

### Using INI configuration

If you put all configuration in your main `ini` file, class can be initialized only on first required use of it. I.e. when your code decides to send get(). At that moment, if class is not registered in Prefab, it will be built from INI config exactly as needed.

**For full list of options refer to Options Array table few scrolls above.**

``` ini
[wcurl]
root=http://mysite.api/v1
ttl=3600
cb_login=yourClass::cb_do_login
useragent = Zeus was here
headers = "Header: value", "Another-Header: Value"

[wcurl.rests]
allmembers=/lists/members/all/pages
withVariable=/lists/members/%%memberID%%/pages


; Using with multiple API's
[apitwo]
root=http://yoursite.io/v2
ttl=60
useragent = Big Falcon Rocket
[apitwo.rests]
getUsers=/lists/members/all/pages
getOneUser=/lists/members/%%memberID%%/pages
```

------------------

### Examples

#### cb_login

``` php
$wcurl->setLogin( callback 'yourClass::cb_do_login' );
```

If any request results in HTTP 401 or 403 code, `wcurl` calls Login callback function and then repeats original request. If again there is error, it is returned to original function result. `wcurl` stores cookies in temporary file unique to API root. This cookie file is included in every request.

Callback must return true, if login success, otherwise it would fail to auto-repeat request after auth success.

**N!B! If login doesn't works, but still `return true`, it can cause `request->login->request->login...` infinitive loop**

Login function **example**
``` php
static function cb_do_login(){
	$wcurl = \wcurl::instance();

	$login = $wcurl->post("/login", array(
				'login'=> 'my_user',
				'password'=> 'covfefe' )
			);
	if($login['status']['http_code']==200){
		return true;
	}

	// or
	$wcurl->setOptions( [ 'basicauth' => "$user:$password"]);
}
```

#### Using with multiple API's
When calling `\wcurl::instance()` it is returned like singleton class, thus in any place in code, same object is used. To force fresh instance from class, use something like
``` php
$apiTwo = new wcurl([$iniName | $optionsArray]);
```
And then `$apiTwo` can be stored in F3 hive.


There's a lot to improve, but currently will be making features I need. If something not possible for your use case, submit an issue or even PR. Thanks to F3 developers for this wonderful framework. If you are looking for something as F3-wcurl, but don't use it, then think twice - Why you are not using F3 yet?
