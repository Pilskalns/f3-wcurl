<?php


require_once("../vendor/autoload.php");
require_once("../helpers.php");

// Set up
$test=new Test;
$f3 = Base::instance();
$f3->config('tests.ini');

// TEST: load class
$test->expect(
    class_exists ('wcurl'),
    '\wcurl class loaded'
);
$wcurl = \wcurl::instance();

// TEST: build a class and check returned configuration
$initConfig = $f3->get('wcurl');
$buildConfig = $wcurl->getOptions();
foreach($initConfig as $key => $value){
    $test->expect(
        array_key_exists($key, $buildConfig) &&
        gettype($value)===gettype($buildConfig[$key]) &&
        $value===$buildConfig[$key],
        "'$key' configured as expected"
    );
}

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



// Display the results; not MVC but let's keep it simple
foreach ($test->results() as $result) {
    if ($result['status'])
        pre('Pass: '.$result['text']);
    else
        pre('FAIL: '.$result['text'].' ('.$result['source'].')');
}