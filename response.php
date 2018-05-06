<?php
namespace havana;

use havana_internal\mime;

class response
{
	const STATUS_OK = 200;
	const STATUS_NOT_MODIFIED = 304;
	const STATUS_BADREQ = 400;
	const STATUS_FORBIDDEN = 403;
	const STATUS_NOTFOUND = 404;
	const STATUS_METHOD_NOT_ALLOWED = 405;
	const STATUS_SERVER_ERROR = 500;

	private $content = '';
	private $status = self::STATUS_OK;
	private $headers = [
		'Content-Type' => 'text/html; charset=utf-8'
	];

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

	/**
	 * Sets the response's status.
	 *
	 * @param int $code HTTP status code
	 * @return self
	 */
	function setStatus($code)
	{
		if (!isset(self::$codes[$code])) {
			panic("Unknown status code: $code");
		}
		$this->status = $code;
		return $this;
	}

	/**
	 * Sets the response's header.
	 *
	 * @param string $name Header name
	 * @param string $value Header value
	 * @return self
	 */
	function setHeader($name, $value)
	{
		$this->headers[$name] = $value;
		return $this;
	}

	/**
	 * Sets the response's content.
	 *
	 * @param string|resource $content
	 * @return self
	 */
	function setContent($content)
	{
		$this->content = $content;
		return $this;
	}

	function flush()
	{
		$code = $this->status;
		$str = self::$codes[$code];

		header("$_SERVER[SERVER_PROTOCOL] $code $str");
		foreach ($this->headers as $name => $value) {
			header("$name: $value");
		}

		if ($this->content === null) {
			return;
		}

		if (is_resource($this->content)) {
			fpassthru($this->content);
			fclose($this->content);
			return;
		}

		if (is_string($this->content)) {
			echo $this->content;
			return;
		}

		panic('Unknown type of content: ' . gettype($this->content));
	}

	/**
	 * Creates a response with JSON content.
	 *
	 * @param mixed $data JSON-encodable data
	 * @return self
	 */
	static function json($data)
	{
		$r = new self();
		$r->setContent(json_encode($data));
		$r->setHeader('Content-Type', 'application/json; charset=utf-8');
		return $r;
	}

	/**
	 * Create a redirection response.
	 *
	 * @param string $url
	 * @param int $code
	 * @return self
	 */
	static function redirect($url, $code = 302)
	{
		$r = new self();
		$r->setHeader('Location', $url);
		$r->setStatus($code);
		return $r;
	}

	/**
	 * Returns response that serves static file from the given filesystem path.
	 *
	 * @param string $path Path to the file
	 * @return self
	 */
	static function staticFile($path)
	{
		$type = mime::type($path);
		if (!$type) {
			$type = 'application/octet-stream';
		}

		$etag = md5_file($path);

		$r = new self();
		$r->setHeader('Content-Length', filesize($path));
		$r->setHeader('ETag', $etag);
		$r->setHeader('Content-Type', $type);

		if (self::cacheValid($path, $etag)) {
			$r->setStatus(self::STATUS_NOT_MODIFIED);
			return $r;
		}

		$r->setContent(fopen($path, 'rb'));
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

	/**
	 * Adds headers making the response download as a file.
	 *
	 * @param string $name File name for the user agent.
	 * @param string $type MIME type of the file. If omitted, will be inferred from the file name.
	 * @return self
	 */
	function downloadAs($name = null, $type = null)
	{
		if (!$type && $name) {
			$type = mime::type($name);
		}
		if (!$type) {
			$type = 'application/octet-stream';
		}
		$s = 'attachment';
		if ($name) $s .= ';filename="' . $name . '"';

		$this->setHeader('Content-Disposition', $s);
		$this->setHeader('Content-Type', $type);
		return $this;
	}

	/**
	 * Converts loose return value to a response object.
	 *
	 * @param mixed $val Value to return to the client
	 * @return self
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
		if (is_int($val) && isset(self::$codes[$val])) {
			$r->setStatus($val);
			$r->setContent(self::$codes[$val]);
			return $r;
		}
		if (is_string($val) || is_resource($val)) {
			$r->setContent($val);
			return $r;
		}

		panic("Unknown response value type: " . gettype($val));
	}
}
