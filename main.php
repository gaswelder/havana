<?php

use havana\dbclient;

/**
 * Throws a new exception with the given message.
 */
function panic($message)
{
	throw new havana\Exception($message);
}

set_error_handler(function ($errno, $msg, $path, $line, $context) {
	panic("$msg at $path:$line");
});

/**
 * @return dbclient
 */
function db($url = null)
{
	static $clients = [];

	if (!$url) {
		$url = getenv('DATABASE');
	}
	if (!$url) {
		throw new Exception("Missing DATABASE env var");
	}
	if (!isset($clients[$url])) {
		$clients[$url] = dbclient::make($url);
	}
	return $clients[$url];
}

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
require __DIR__ . '/db/db.php';
require __DIR__ . '/db/dbobject.php';
require __DIR__ . '/tpl.php';
require __DIR__ . '/url.php';
require __DIR__ . '/user.php';

require __DIR__ . '/private/router.php';
require __DIR__ . '/private/mime.php';
