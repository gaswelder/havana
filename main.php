<?php

set_error_handler(function ($errno, $msg, $path, $line, $context) {
	throw new Exception("$msg at $path:$line");
});

function dd()
{
	call_user_func_array('var_dump', func_get_args());
	exit;
}

function dump()
{
	call_user_func_array('var_dump', func_get_args());
}

function registerClasses($dir)
{
	spl_autoload_register(function ($className) use ($dir) {
		$path = $dir . '/' . str_replace('\\', '/', $className) . '.php';
		if (file_exists($path)) {
			require_once($path);
		}
	});
}

registerClasses(__DIR__);


require __DIR__ . '/app.php';
require __DIR__ . '/lang.php';
require __DIR__ . '/response.php';
require __DIR__ . '/request.php';
require __DIR__ . '/upload.php';
require __DIR__ . '/tpl.php';
require __DIR__ . '/url.php';
require __DIR__ . '/user.php';

require __DIR__ . '/private/router.php';
require __DIR__ . '/private/mime.php';
