<?php
use havana\dbclient;

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