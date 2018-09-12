<?php

error_reporting( E_ALL );
ini_set('log_errors', '1');
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('error_log', 'php_errors.log');

// require_once("vendor/autoload.php");
// require_once("helpers.php");
// require_once("../vendor/autoload.php");
// require_once("../helpers.php");

foreach(['vendor/autoload.php', 'helpers.php', '../vendor/autoload.php', '../helpers.php'] as $file){
    if(file_exists($file))
        require_once($file);
}

function hello(){}

// Set up
$test=new Test;
$f3 = Base::instance();
foreach(['tests/tests.ini','tests.ini'] as $file){
    if(file_exists($file))
        $f3->config($file);
}
$f3->set('DEBUG',3);

$SKIP_NETWORK_REQUESTS = true;

// TEST: load class
$test->expect(
    class_exists ('wcurl'),
    '\wcurl class loaded'
);
$wcurl = \wcurl::instance();
// $f3->run();

// TEST: build a class and check returned configuration
$initConfig = $f3->get('wcurl');
$buildConfig = $wcurl->getOptions();
foreach($initConfig as $key => $value){
    $test->expect(
        array_key_exists($key, $buildConfig) &&
        gettype($value)===gettype($buildConfig[$key]) &&
        $value===$buildConfig[$key],
        "Option '$key' configured as expected"
    );
}
echo "<pre>";
$users = $wcurl->get('getusers', null, ['ttl'=>0, 'headers'=>'Accept: plaint/text', 'curlopt'=>[CURLOPT_USERAGENT => 'fancy UA'], 'rests'=>['getuserIDtwo' => '/users/%%id%%?page=2']]);
if(!$SKIP_NETWORK_REQUESTS){
    // TEST: named route
    $users = $wcurl->get('getusers');
    if($users['error'])
        pre($users);
    $test->expect(
        $users['status']['http_code']==200,
        'Named route works'
    );
    // TEST: named route with fill
    $users = $wcurl->get('getuserID', ['id'=>2]);
    if($users['error'])
        pre($users);
    $test->expect(
        $users['status']['http_code']==200,
        'Named route with fill'
    );

    // TEST: HTTP GET
    $users = $wcurl->get('users');
    if($users['error'])
        pre($users);
    $test->expect(
        $users['status']['http_code']==200,
        'Perform HTTP GET request'
    );

    // TEST: Receive 404 on GET request
    $users = $wcurl->get('users/23');
    if($users['error'])
        pre($users);
    $test->expect(
        $users['status']['http_code']==404,
        'Receive 404 on GET request'
    );

    // TEST: HTTP POST with FORM data
    $formData = ['name'=>'morpheus'];
    $users = $wcurl->post('/users', null, $formData, false );
    if($users['error'])
        pre($users);
    $test->expect(
        $users['status']['http_code']==201 &&
        $users['response']['name']==$formData['name'],
        'Perform HTTP POST request with FORM data'
    );

    // TEST: HTTP POST with JSON
    // response data check specific to reqres.in API returned format
    $users = $wcurl->post('/users', null, $formData );
    if($users['error'])
        pre($users);
    $test->expect(
        $users['status']['http_code']==201 &&
        array_key_exists( json_encode($formData), $users['response'] ),
        'Perform HTTP POST request with JSON data'
    );

    // TEST: HTTP DELETE
    $users = $wcurl->delete('/users/2');
    if($users['error'])
        pre($users);
    $test->expect(
        $users['status']['http_code']==204,
        'Perform HTTP DELETE request'
    );
}

// Display the results; not MVC but let's keep it simple
$error = false;
foreach ($test->results() as $result) {
    if ($result['status'])
        echo 'Pass: '.$result['text'].PHP_EOL;
    else {
        echo 'FAIL: '.$result['text'].' ('.$result['source'].')'.PHP_EOL;
        $error = true;
    }
}
if($error){
    echo PHP_EOL."One or more tests failed".PHP_EOL;
    if(file_exists('php_errors.log'))
        echo file_get_contents( 'php_errors.log' );
    exit(1);
}
