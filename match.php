<?php
class expr
{
	private $s;
	private $toks = [];

	function __construct($s)
	{
		$this->s = $s;
		while (strlen($this->s) > 0) {
			$this->read();
		}
	}

	function match($s, &$m)
	{
		$p = $this->toPCRE();
		return preg_match($p, $s, $m);
	}

	function toPCRE()
	{
		$s = '';
		foreach ($this->toks as $tok) {
			if ($tok[0] == 'lit') {
				$s .= preg_quote($tok[1]);
			}
			else {
				$s .= "($tok[1])";
			}
		}

		$delims = ['/', '@'];

		foreach ($delims as $delim) {
			if (strpos($s, $delim) === false) {
				return $delim.'^'.$s.'$'.$delim;
			}
		}

		throw new Exception("Couldn't find suitable delimited for regular expression: $s");
	}

	private function read()
	{
		if ($this->s[0] == '{') {
			$p = strpos($this->s, '}');
			if ($p) {
				$tok = ['pat', substr($this->s, 1, $p-1)];
				$this->s = substr($this->s, $p+1);
				$this->toks[] = $tok;
				return;
			}
		}

		$p = strpos($this->s, '{');
		if (!$p) {
			$p = strlen($this->s);
		}
		$tok = ['lit', substr($this->s, 0, $p)];
		$this->s = substr($this->s, $p);
		$this->toks[] = $tok;
	}
}

function match_url($uri, $pat)
{
	$uri = trim($uri, '/');
	$pat = trim($pat, '/');

	if ($uri === '' && $pat === '') {
		//return [];
	}

	$uri_parts = explode('/', $uri);
	$pat_parts = explode('/', $pat);

	if (count($uri_parts) != count($pat_parts)) {
		return false;
	}

	$args = [];

	foreach ($uri_parts as $i => $part) {
		$expr = new expr($pat_parts[$i]);
		if (!$expr->match($part, $m)) {
			return false;
		}
		$args = array_merge($args, array_slice($m, 1));
	}

	return $args;
}

