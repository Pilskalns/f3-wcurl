# F3-wcurl
F3 Wrapper for cURL to talk with external REST API. Handles authentication and response caching.

##### Preface

Each time I ask my browser to open Email, it jumps few redirects and opens main page. If my login have expired, it quickly asks for password and then continues previous task. Few hours later if same URL opened, browser still remembers login state and I directly get to my email.

Underneath so many actions, requests, but from user very simple action.

Same way I want my code to interact with API's. Here you have remote site, URL's for basic actions and at some point later now "Get enabled servers in this organization" or "Add user to this mailing list". By the way, if remote asks for authentication - here's you can tell it back and then try again.

### Configuration
Acess the class for first time
``` php
$wcurl = \wcurl::instance();
// or with optional parameters, which can also be set later
$wcurl = \wcurl::instance([$iniName | $optionsArray]);
```

#### Set login callback function
Function should be valid callback for `call_user_func()`

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
	$wcurl->setBasicAuth($user, $password);
}
```

#### Set headers included in each request

Some session-less API's require user/api password in each request header. Others need specify Content-type and/or Accept values

[cURL valid array of strings](http://php.net/manual/en/function.curl-setopt.php) ["Header: value", "Another-Header: Value"]

``` php
$wcurl->setHeaders( array ['Accept:application/json, text/plain, */*'] );
```

#### Set User Agent string

``` php
$wcurl->setUserAgent( 'Zeus was here' );
```

### How to use it

#### GET

``` php
$response = $wcurl->get( string $url [, array $fill = null [, (int|bool) $ttl = true]] );
```

#### POST

``` php
$response = $wcurl->post( string $url, (array|string) $body [, array $fill = null ]);
```

If body is array, it will be passed trough `json_encode`. Possible improvement to allow HTTP form serialization from array.

#### Using named routes (rests)

When constructing long remote URL's, it's easier to remember them by short keywords, especially if they are called from multiple places. Like `allmembers` instead of `/lists/members/all/pages`.

Sometimes these contain also unique parameters, which needs to be filled in per each request.

To set named routes, pass simple key => value array. You can call this multiple times, with array being merged to already existing routes. I.e. from `.ini` configuration
``` php
$wcurl->setRests( array(
	'allmembers'	=> '/lists/members/all/pages',
	'withVariable'	=> '/lists/members/%%memberID%%/pages',
	'updateEmail'	=> '/lists/members/%%memberID%%/update'
	) );
```
URL variables are wrapped in two `%` from both sides.

To use named route, pass it's name instead of full URL.

``` php
$response = $wcurl->get( 'allmembers' );
```

### Fill / URLs with variables

To fill existing URL either from your custom or trough `setRests()` available ones, pass `$key => $value` array as last parameter to method.

``` php
$response = $wcurl->get( 'withVariable', array('memberID' => 'abcID') );
```
or
``` php
$wcurl->post('updateEmail',
			array('email'=>'andzs@pilskalns.lv'),
			array('memberID' =>'abcID')
		);
```

### Using INI configuration

If you need only occasionally call external API's, adding configuration from `beforeRoute` will initialize class every time. PHP is fast, but still.

If you put all configuration in your main `ini` file, class can be initialized only on first required use of it. I.e. when your code decides to send get(). At that moment, if class is not registered in Prefab, it will be built from INI config exactly as needed.

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
```

### Using with multiple API's
When calling `\wcurl::instance()` it is built like singleton class, thus in any place in code, same instance is returned. To force fresh instance from class, use something like
``` php
$apiTwo = new wcurl([$iniName | $optionsArray]);
```
And then `$apiTwo` can be stored in F3 hive.


There's a lot to improve, but currently will be making features I need. If something not possible for your use case, submit an issue or even PR.

Though this plugin could be developed solely as standalone class/tool, it will be used in context of [Fat Free Framework](https://fatfreeframework.com) (F3), so naturally got few cool F3 features as dependencies - `Prefab`, `Cache` and `Web`.

Thanks to F3 developers for this wonderful framework. If you are looking for such tool, but don't use F3, then think twice - Why you are not using F3 yet?
