<?php

$constants = get_defined_constants();
$sslVersions= [];
foreach ($constants as $key => $value) {
    if(strlen($key)<17)
        continue;
    if(substr ($key, 0, 16)=='CURL_SSLVERSION_')
        $sslVersions[]=$key;
        // echo substr ($key, 0, 16).PHP_EOL;
        // continue;
    // CURL_SSLVERSION_
    
}
$sslVersions = [
    CURL_SSLVERSION_DEFAULT,
    CURL_SSLVERSION_TLSv1,
    CURL_SSLVERSION_TLSv1_0,
    CURL_SSLVERSION_TLSv1_1,
    CURL_SSLVERSION_TLSv1_2,
    CURL_SSLVERSION_SSLv2,
    CURL_SSLVERSION_SSLv3,
];

print_r(curl_version());
print_r($sslVersions);

foreach ($sslVersions as $sslVersion) {

    $uri = "https://api.reporting.cloud";

    
    printf("Trying %d", $sslVersion);
    echo PHP_EOL;

    $ch = curl_init($uri);

    curl_setopt($ch, CURLOPT_VERBOSE        , true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER , 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT , 0);
    curl_setopt($ch, CURLOPT_TIMEOUT        , 2);
    curl_setopt($ch, CURLOPT_SSLVERSION     , $sslVersion);

    if (curl_exec($ch) === false) {
        echo "==ERROR".PHP_EOL;
        var_dump(curl_error($ch));
    } else {
        curl_close($ch);
    }
    echo PHP_EOL;
    echo "=======";
    echo PHP_EOL;
    echo PHP_EOL;

}

exit(1);