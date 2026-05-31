<?php

namespace think;

// PHP 8.5 compat: suppress legacy type errors
set_error_handler(function($severity, $message, $file, $line) {
    if (strpos($message, "in_array()") !== false || strpos($message, "Undefined constant") !== false) {
        return true;
    }
    return false;
});

defined('MN') or define('MN','index');
define('ROOT_PATH', __DIR__ . '/');
define('APP_PATH', __DIR__ . '/app');
if((isset($_SERVER['REQUEST_SCHEME']) && 'https' == $_SERVER['REQUEST_SCHEME']) || (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' == $_SERVER['HTTP_X_FORWARDED_PROTO']) || (isset($_SERVER['HTTP_X_CLIENT_SCHEME']) && 'https' == $_SERVER['HTTP_X_CLIENT_SCHEME'])){
	define('PRE_URL','https://'.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME']));
}else{
	define('PRE_URL','https://'.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME']));
}
//require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendors/autoload.php';

// 执行HTTP应用并响应
$http = (new App())->http;

$response = $http->run();

$response->send();

$http->end($response);
