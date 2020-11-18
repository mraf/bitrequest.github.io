<?php
// API
function api($url, $data, $headers, $ct, $cf, $meta, $fn) {
    $cache_refresh = ($cf == "1w") ? 604800 : ($cf == "1d") ? 86400 : ($cf == "1h") ? 3600 : 3600;
    $cache_folder = "cache/" . $cf . "/";
    $filename = ($fn) ? $fn : md5($data . $url);
    $cache_file = $cache_folder . $filename;
    $cache_monitor = $cache_folder . "cachemonitor";
    $time = time();
    $cache_content = json_encode(array(
        "br_cached" => $time
    ));
    $file_time = filemtime($cache_file);
    $cached_time = ($file_time) ? $file_time : $time;
    $time_in_cache = $time - $cached_time;
    $cache_object = array(
        "filename" => $filename,
        "title" => $time_in_cache . " of " . $ct . " seconds in cache",
        "unix_timestamp" => $time,
        "unix_timestamp_of_cached_file" => $cached_time,
        "cache_time" => $ct,
        "time_in_cache" => $time_in_cache
    );
    if (file_exists($cache_file) && $time_in_cache < $ct) {
        $cache_contents = file_get_contents($cache_file);
        $meta_contents = array(
            "br_cache" => $cache_object,
            "br_result" => json_decode($cache_contents, true)
        );
        $cache_result = ($meta === false) ? json_decode($cache_contents, true) : $meta_contents;
        $cm_time = filemtime($cache_monitor);
        $folder_time = ($cm_time) ? $cm_time : $time;
        if ($time - $cache_refresh > $folder_time) {
	        $files = glob($cache_folder . "*");
	         // clear all expired cache
            foreach ($files as $file) {
	            if ($time - filemtime($file) > $cache_refresh) {
		            unlink($file);
	            }
            }
            if (!file_exists($cache_monitor)) {
                file_put_contents($cache_monitor, $cache_content);
            }
        }
        return $cache_result;
        exit();
    }
    else {
        $apiresult = ($url) ? curl_get($url, $data, $headers) : $data;
        if ($apiresult) {
            if (!is_dir($cache_folder)) {
                mkdir($cache_folder, 0777, true);
            }
            file_put_contents($cache_file, $apiresult);
            if (!file_exists($cache_monitor)) { // create cache monitor if not exists
	            file_put_contents($cache_monitor, $cache_content);
            }
            $meta_contents = array(
	            "br_cache" => $cache_object,
	            "br_result" => json_decode($apiresult, true)
	        );
			$result = ($meta === false) ? json_decode($apiresult, true) : $meta_contents;
            return $result;
        }
        else {
            if (file_exists($cache_file)) {
                unlink($cache_file);
            }
        }
    }
}

// CURL
function curl_get($url, $data, $headers) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    if (!empty($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3); //timeout in seconds
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        return error_object("411", curl_error($ch));
    }
    curl_close($ch);
    if ($result) {
        return $result;
    }
    else {
        return error_object("411", "no result");
    }
}

function error_object($code, $message) {
    return json_encode(array(
        "error" => array(
            "code" => $code,
            "message" => $message
        )
    ));
}

?>