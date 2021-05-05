<?php

namespace havana;

class request
{
	private static $init = false;
	private static $post = [];
	private static $get = [];

	/**
	 * Returns the request's method.
	 */
	static function method(): string
	{
		return $_SERVER['REQUEST_METHOD'];
	}

	static function get($key)
	{
		self::init();
		if (array_key_exists($key, self::$get)) {
			return self::$get[$key];
		} else {
			return null;
		}
	}

	/**
	 * Returns value of the given POST field.
	 *
	 * @param string $key
	 * @return string|array|null
	 */
	static function post($key)
	{
		self::init();
		if (array_key_exists($key, self::$post)) {
			return self::$post[$key];
		} else {
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
		// getallheaders is not available on all servers.
		$key = 'HTTP_' . str_replace('-', '_', strtoupper($name));
		if (isset($_SERVER[$key])) {
			return $_SERVER[$key];
		}
		return null;
	}

	private static function init()
	{
		if (self::$init) {
			return;
		}
		self::$init = true;

		self::$post = [];
		self::$get = [];

		foreach ($_POST as $k => $v) {
			self::$post[$k] = $v;
		}
		foreach ($_GET as $k => $v) {
			self::$get[$k] = $v;
		}
	}

	static function url()
	{
		if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
			$protocol = "https";
		} else {
			$protocol = "http";
		}
		$domain = $protocol . '://' . $_SERVER['HTTP_HOST'];
		return new url($domain . $_SERVER['REQUEST_URI']);
	}

	/**
	 * Returns contents of $_FILES normalized as array.
	 */
	private static function php_files($input_name) {
		if (!isset($_FILES[$input_name])) {
			return [];
		}
		if (!is_array($_FILES[$input_name]['name'])) {
			return [$_FILES[$input_name]];
		}

		$files = [];
		$fields = [
			"type",
			"tmp_name",
			"error",
			"size",
			"name"
		];
		foreach ($_FILES[$input_name]['name'] as $i => $name) {
			$input = [];
			foreach ($fields as $f) {
				$input[$f] = $_FILES[$input_name][$f][$i];
			}
			$files[] = $input;
		}
		return $files;
	}

	/**
	 * Returns files that were uploaded without errors.
	 * 
	 * @return array
	 */
	static function uploads($input_name)
	{
		$uploads = [];
		foreach (self::php_files($input_name) as $file) {
			// Happens with multiple file inputs with the same name marked with '[]'.
			if ($file['error'] == UPLOAD_ERR_NO_FILE) {
				continue;
			}
			if ($file['error'] || !$file['size']) {
				continue;
			}
			unset($file['error']);
			$uploads[] = new upload($file);
		}
		return $uploads;
	}

	/**
	 * Returns request's body as a string.
	 *
	 * @return string
	 */
	static function body()
	{
		return file_get_contents('php://input');
	}
}
