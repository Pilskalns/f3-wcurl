# F3-wcurl

## Table of Contents

* [wcurl](#wcurl)
    * [__construct](#__construct)
    * [get](#get)
    * [post](#post)
    * [delete](#delete)
    * [setOptions](#setoptions)
    * [setLogin](#setlogin)
    * [setBasicAuth](#setbasicauth)
    * [setQueryToken](#setquerytoken)

## wcurl

PHP cURL wrapper tailored for use within F3 framework



* Full name: \wcurl
* Parent class: 


### __construct



``` php
\wcurl::__construct(  $identity = &#039;wcurl&#039; )
```




**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$identity` | **** |  |




---

### get

HTTP GET method

``` php
$wcurl->get( string $url, mixed $fill = null, mixed $ttl = true )
```




**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$url` | **string** | Relative or named URL |
| `$fill` | **mixed** | Array of url params to fill in |
| `$ttl` | **mixed** | How long to cache response |




---

### post

HTTP POST method

``` php
$wcurl->post( string $url, array $body = null, array $fill = null, boolean $encodeJSON = true ): array
```




**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$url` | **string** |  |
| `$body` | **array** |  |
| `$fill` | **array** |  |
| `$encodeJSON` | **boolean** |  |




---

### delete

HTTP DELETE method

``` php
$wcurl->delete( string $url, array $body = null, array $fill = null, boolean $encodeJSON = true ): array
```




**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$url` | **string** |  |
| `$body` | **array** |  |
| `$fill` | **array** |  |
| `$encodeJSON` | **boolean** |  |




---

### setOptions

Change wcurl options by passing array of them. Will merge with existing config.

``` php
$wcurl->setOptions( array $options )
```

For initial configuration use new \wcurl


**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$options` | **array** |  |




---

### setLogin

Set callback function to handle authentication

``` php
$wcurl->setLogin( string $cb )
```

If any request results in HTTP 401 or 403 code, `wcurl` calls Login callback function and then repeats original request. If again there is error, it is returned to original function result. `wcurl` stores cookies in temporary file unique to API root. This cookie file is included in every request.
Callback must return true, if login success, otherwise it would fail to auto-repeat request after auth success.

**N!B! If login doesn't works, but still `return true`, it can cause `request->login->request->login...` infinitive loop**


**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$cb` | **string** | should be valid callback for `call_user_func()` |




---

### setBasicAuth

Set Basic Authentication

``` php
$wcurl->setBasicAuth( string $user, string $password )
```




**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$user` | **string** |  |
| `$password` | **string** |  |




---

### setQueryToken



``` php
$wcurl->setQueryToken(  $key,  $value )
```




**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$key` | **** |  |
| `$value` | **** |  |




---



--------
