<?php
function db()
{
	static $client = null;

	if (!$client) {
		$url = getenv('DATABASE');
		if (!$url) {
			throw new Exception("Missing DATABASE env var");
		}
		$client = new dbclient($url);
	}
	return $client;
}

function dd()
{
	call_user_func_array('var_dump', func_get_args());
	exit;
}

