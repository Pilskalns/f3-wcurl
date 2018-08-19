<?php

function pre($obj = null, $escape = false){
	echo "<pre>";
	if($escape){
		$obj = htmlspecialchars ( print_r($obj, true) );
	}
	print_r($obj);
	echo "</pre>";
}

function dump($obj = null){
	echo "<pre>";
	var_dump($obj);
	echo "</pre>";
}

function json($obj = null, $pretty = true){
	if($pretty){	
		echo json_encode($obj, JSON_PRETTY_PRINT);
	} else {
		echo json_encode($obj);
	}
}
if(!function_exists('isjson') ){
	function isjson($str) {
		$json = json_decode($str);
		return $json && $str != $json;
	}
}

function startsWith($haystack, $needle){
    return strpos($haystack, $needle) === 0;
}
function endsWith($haystack, $needle){
    return strrpos($haystack, $needle) + strlen($needle) ===
        strlen($haystack);
}

function joinPaths($args) {
    // $args = func_get_args();
    $paths = array();
    foreach ($args as $arg) {
        $paths = array_merge($paths, (array)$arg);
    }

    $paths = array_map(create_function('$p', 'return trim($p, "/");'), $paths);
    $paths = array_filter($paths);
    return join('/', $paths);
}


function getDirContents($dir, $root = false, &$results = array() ){
    $files = scandir($dir);

    foreach($files as $key => $value){
        // $path = realpath($dir.DIRECTORY_SEPARATOR.$value);
        $path = ($dir.DIRECTORY_SEPARATOR.$value);
        if(!is_dir($path)) {
            $results[] = $path;
        } else if($value != "." && $value != "..") {
            getDirContents($path, false, $results[$path]);
            // $results[] = $path;
        }
    }

    return $results;
}

function humanTiming($time){
	$time = time() - $time; // to get the time since that moment
	$time = ($time<1)? 1 : $time;
	$tokens = array (
		31536000 => 'year',
		2592000 => 'month',
		604800 => 'week',
		86400 => 'day',
		3600 => 'hour',
		60 => 'minute',
		1 => 'second'
	);

	foreach ($tokens as $unit => $text) {
		if ($time < $unit) continue;
		$numberOfUnits = floor($time / $unit);
		return $numberOfUnits.' '.$text.(($numberOfUnits>1)?'s':'');
	}

}

function human_filesize($bytes, $decimals = 2) {
    $size = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f ", $bytes / pow(1024, $factor)) . @$size[$factor];
}

function removeDirectory($path) {
	$files = glob($path . '/*');
	foreach ($files as $file) {
		is_dir($file) ? removeDirectory($file) : unlink($file);
	}
	rmdir($path);
	return;
}
