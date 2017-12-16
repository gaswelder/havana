<?php

namespace havana;

use havana_internal\mime;

class response
{
	public $content;
	public $type;
	public $status;
	private $headers = [];

	const STATUS_OK = 200;
	const STATUS_BADREQ = 400;
	const STATUS_FORBIDDEN = 403;
	const STATUS_NOTFOUND = 404;
	const STATUS_METHOD_NOT_ALLOWED = 405;
	const STATUS_SERVER_ERROR = 500;

	private static $codes = array(
		'200' => 'OK',
		'201' => 'Created',
		'202' => 'Accepted',
		'301' => 'Moved Permanently',
		'302' => 'Found',
		'303' => 'See Other',
		'304' => 'Not Modified',
		'400' => 'Bad Request',
		'401' => 'Unauthorized',
		'403' => 'Forbidden',
		'404' => 'Not Found',
		'405' => 'Method Not Allowed',
		'406' => 'Not Acceptable',
		'410' => 'Gone',
		'500' => 'Internal Server Error',
		'503' => 'Service Unavailable'
	);

	function __construct($code = 200, $content = null, $type = null)
	{
		if (!$type) {
			$type = 'text/html; charset=utf-8';
		}
		$this->type = $type;
		$this->content = $content;
		$this->status = $code;
	}

	function download($name, $type = null)
	{
		if ($name && !$type) {
			$type = mime::type($name);
			if (!$type) {
				trigger_error("Unknown MIME type for '$name'");
				$type = 'application/octet-stream';
			}
		}
		$this->header('Content-Disposition: attachment; filename="'.$name.'"');
		return $this;
	}

	function header($s)
	{
		$this->headers[] = $s;
	}

	function flush()
	{
		$code = $this->status;
		if (!isset(self::$codes[$code])) {
			trigger_error("Unknown HTTP error number: $code");
			$str = 'unknown';
		}
		else {
			$str = self::$codes[$code];
		}

		if ($this->content === null && $this->status != 200) {
			$this->content = "$this->status";
		}

		header("$_SERVER[SERVER_PROTOCOL] $code $str");
		header('Content-Type: '.$this->type);
		foreach ($this->headers as $h) {
			header($h);
		}

		if ($this->content === null) {
			return;
		}

		if (is_resource($this->content)) {
			fpassthru($this->content);
			fclose($this->content);
		}
		else if (is_string($this->content)) {
			header('Content-Length: ' . strlen($this->content));
			echo $this->content;
		}
		else {
			trigger_error('Unknown kind of content: '.gettype($this->content));
			echo $this->content;
		}
	}

	static function json($data)
	{
		$str = json_encode($data);
		return new response(200, $str, 'application/json; charset=utf-8');
	}

	static function redirect($url, $code = 302)
	{
		$r = new response($code);
		$r->header('Location: '.$url);
		return $r;
	}

	// Returns response that serves static file from the given filesystem path.
	static function staticFile($path)
	{
		$type = mime::type($path);
		if (!$type) {
			$type = 'application/octet-stream';
		}

		$etag = md5_file($path);

		$r = new response();
		$r->header('Content-Length: '.filesize($path));
		$r->header('ETag: '.$etag);
		$r->header('Content-Type: '.$type);

		if (self::cacheValid($path, $etag)) {
			$r->status = 304;
			return $r;
		}

		$r->content = fopen($path, 'rb');
		return $r;
	}

	private static function cacheValid($path, $etag)
	{
		$sum = request::header('If-None-Match');
		$date = request::header('If-Modified-Since');
		if (!$sum && !$date) {
			return false;
		}

		if ($sum) {
			$sums = array_map('trim', explode(',', $sum));
			if (!in_array($etag, $sums)) {
				return false;
			}
		}

		if ($date) {
			$t = strtotime($date);
			if (filemtime($path) > $t) {
				return false;
			}
		}
		return true;
	}

	static function download_file($path, $name = null, $type = null)
	{
		if ($name && !$type) {
			$type = mime::type($name);
			if (!$type) {
				trigger_error("Unknown MIME type for '$name'");
				$type = 'application/octet-stream';
			}
		}

		$f = fopen($path, 'rb');
		$r = new response(200, $f, $type);
		$s = 'Content-Disposition: attachment';
		if ($name) {
			$s .= ';filename="'.$name.'"';
		}
		$r->header($s);
		$size = filesize($path);
		$r->header('Content-Length: '.$size);
		return $r;
	}

	/*
	 * Converts loose return value to a response object
	 */
	static function make($val)
	{
		if ($val instanceof response) {
			return $val;
		}
		if (is_array($val)) {
			return self::json($val);
		}
		$r = new response();
		if ($val === null) {
			return $r;
		}
		if (is_int($val)) {
			$r->status = $val;
			return $r;
		}
		if (is_string($val) || is_resource($val)) {
			$r->content = $val;
			return $r;
		}

		trigger_error("Unknown response: ".gettype($val));
		return self::make(self::ERROR);
	}
}
