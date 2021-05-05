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

	static function files($input_name)
	{
		if (!isset($_FILES[$input_name])) {
			return array();
		}

		$files = array();
		if (!is_array($_FILES[$input_name]['name'])) {
			$files[] = $_FILES[$input_name];
		} else {
			$fields = array(
				"type",
				"tmp_name",
				"error",
				"size",
				"name"
			);
			foreach ($_FILES[$input_name]['name'] as $i => $name) {
				$input = array();
				foreach ($fields as $f) {
					$input[$f] = $_FILES[$input_name][$f][$i];
				}
				$files[] = $input;
			}
		}

		/*
		 * Filter out files with errors
		 */
		$ok = array();
		foreach ($files as $file) {
			/*
			 * This happens with multiple file inputs with the same
			 * name marked with '[]'.
			 */
			if ($file['error'] == UPLOAD_ERR_NO_FILE) {
				continue;
			}

			if ($file['error'] || !$file['size']) {
				$errstr = self::errstr($file['error']);
				warning("Upload of file '$file[name]' failed ($errstr, size=$file[size])");
				continue;
			}
			unset($file['error']);

			$size = round($file['size'] / 1024, 2);
			// h3::log("Upload: $file[name] ($size KB, $file[type])");

			$ok[] = $file;
		}

		return array_map(function ($file) {
			return new upload($file);
		}, $ok);
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
