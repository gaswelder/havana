<?php
class response
{
	public $content;
	public $type;
	public $status = 200;
	private $headers = [];

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

	function __construct($content = null, $type = null)
	{
		if (!$type) {
			$type = 'text/html; charset=utf-8';
		}
		$this->type = $type;
		$this->content = $content;
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
		header("$_SERVER[SERVER_PROTOCOL] $code $str");
		header('Content-Type: '.$this->type);

		foreach ($this->headers as $h) {
			header($h);
		}

		if ($this->content === null && $this->status != 200) {
			$this->content = "$this->status";
		}

		if ($this->content === null) {
			return;
		}

		if (is_resource($this->content)) {
			fpassthru($this->content);
			fclose($this->content);
		}
		else if (is_string($this->content)) {
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
		return new response($str, 'application/json; charset=utf-8');
	}

	static function redirect($url, $code = 302)
	{
		$r = new response();
		$r->status = $code;
		$r->header('Location: '.$url);
		return $r;
	}

	static function download($content, $name = null, $type = null)
	{
		if ($name && !$type) {
			$type = mime::type($name);
			if (!$type) {
				trigger_error("Unknown MIME type for '$name'");
				$type = 'application/octet-stream';
			}
		}

		$r = new response($content, $type);

		$s = 'Content-Disposition: attachment';
		if ($name) {
			$s .= ';filename="'.$name.'"';
		}
		$r->header($s);

		if (is_string($content)) {
			$size = strlen($content);
			$r->header('Content-Length: '.$size);
		}

		return $r;
	}

	static function static_file($path)
	{
		$type = mime::type($path);
		if (!$type) {
			trigger_error("Unknown MIME type for '$name'");
			$type = 'application/octet-stream';
		}

		$size = filesize($path);
		$f = fopen($path, 'rb');
		$r = new response($f, $type);
		$r->header('Content-Length: '.$size);
		return $r;
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
		$r = new response($f, $type);
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
		if (is_int($val)) {
			$code = $val;
			$r = new response();
			$r->status = $code;
			return $r;
		}
		if (is_string($val)) {
			return new response($val);
		}
		if ($val === null) {
			return new response(null);
		}

		trigger_error("Unknown response: ".gettype($val));
		return self::make(self::ERROR);
	}
}
