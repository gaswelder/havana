<?php
class request
{
	private static $init = false;
	private static $post = null;
	private static $get = null;

	static function get($key)
	{
		self::init();
		if (array_key_exists($key, self::$get)) {
			return self::$get[$key];
		}
		else {
			return null;
		}
	}

	static function post($key)
	{
		self::init();
		if (array_key_exists($key, self::$post)) {
			return self::$post[$key];
		}
		else {
			return null;
		}
	}

	static function posts($_keys_)
	{
		$keys = func_get_args();
		$data = array();
		foreach ($keys as $k) {
			$data[$k] = self::post($k);
		}
		return $data;
	}

	static function header($name)
	{
		/*
		 * We would use getallheaders, but that is not available
		 * of all servers.
		 */
		$key = 'HTTP_'.str_replace('-', '_', strtoupper($name));
		if (isset($_SERVER[$key])) {
			return $_SERVER[$key];
		}
		return null;
	}

	private static function init()
	{
		if (self::$init) return;
		self::$init = true;

		self::$post = array();
		self::$get = array();

		$mq = get_magic_quotes_gpc();
		foreach ($_POST as $k => $v) {
			if ($mq) {
				$k = stripslashes($k);
				$v = self::recurse($v, 'stripslashes');
			}
			self::$post[$k] = $v;
		}

		foreach ($_GET as $k => $v) {
			if ($mq) {
				$k = stripslashes($k);
				$v = self::recurse($v, 'stripslashes');
			}
			self::$get[$k] = $v;
		}
	}

	private static function recurse($value, $func)
	{
		if (is_array($value)) {
			return array_map($func, $value);
		}
		else {
			return $func($value);
		}
	}

	static function domain()
	{
		if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
			$protocol = "https";
		}
		else {
			$protocol = "http";
		}
		return $protocol.'://'.$_SERVER['HTTP_HOST'];
	}

	static function url()
	{
		return self::domain().$_SERVER['REQUEST_URI'];
	}
}

?>
