<?php

error_reporting( E_ALL );
ini_set('log_errors', '1');
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('error_log', 'php_errors.log');

require_once("vendor/autoload.php");
require_once("helpers.php");
// require_once("../vendor/autoload.php");
// require_once("../helpers.php");

function hello(){}

// Set up
$test=new Test;
$f3 = Base::instance();
$f3->config('tests/tests.ini');
// $f3->config('tests.ini');
$f3->set('DEBUG',3);

// $SKIP_NETWORK_REQUESTS = true;

// TEST: load class
$test->expect(
    class_exists ('wcurl'),
    '\wcurl class loaded'
);
$wcurl = \wcurl::instance();
$f3->run();

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

if(!$SKIP_NETWORK_REQUESTS){
    // TEST: named route
    $users = $wcurl->get('getusers');
    $test->expect(
        $users['status']['http_code']==200,
        'Named route works'
    );
    // TEST: named route with fill
    $users = $wcurl->get('getuserID', ['id'=>2]);
    $test->expect(
        $users['status']['http_code']==200,
        'Named route with fill'
    );

    // TEST: HTTP GET
    $users = $wcurl->get('users');
    $test->expect(
        $users['status']['http_code']==200,
        'Perform HTTP GET request'
    );

    // TEST: Receive 404 on GET request
    $users = $wcurl->get('users/23');
    $test->expect(
        $users['status']['http_code']==404,
        'Receive 404 on GET request'
    );

    // TEST: HTTP POST with FORM data
    $formData = ['name'=>'morpheus'];
    $users = $wcurl->post('/users', $formData, null, false );
    $test->expect(
        $users['status']['http_code']==201 &&
        $users['response']['name']==$formData['name'],
        'Perform HTTP POST request with FORM data'
    );

    // TEST: HTTP POST with JSON
    // response data check specific to reqres.in API returned format
    $users = $wcurl->post('/users', $formData );
    $test->expect(
        $users['status']['http_code']==201 &&
        array_key_exists( json_encode($formData), $users['response'] ),
        'Perform HTTP POST request with JSON data'
    );

    // TEST: HTTP DELETE
    $users = $wcurl->delete('/users/2');
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
    echo file_get_contents( 'php_errors.log' );
    exit(1);
}
