<?php
namespace havana;

class url
{
	private $data;
	private $original;

	function __construct($url)
	{
		$this->original = $url;
		$this->data = parse_url($url);

		$domain = "$this->scheme://$this->host";
		if ($this->port) {
			$domain .= ":$this->port";
		}
		$this->data['domain'] = $domain;
	}

	function __toString()
	{
		return $this->original;
	}

	function __get($k)
	{
		return isset($this->data[$k]) ? $this->data[$k] : null;
	}

	function isUnder($prefix)
	{
		$path = $this->path;
		return $path == $prefix || strpos($path, $prefix . '/') === 0;
	}
}
