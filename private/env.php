<?php

namespace havana_internal;

class env
{
	/**
	 * Reads environment variables from the given env-file.
	 * Existing variables are not overwritten.
	 *
	 * @param string $path
	 */
	static function parse($path)
	{
		$lines = array_map('trim', file($path));
		foreach ($lines as $line) {
			if (strlen($line) == 0 || $line[0] == '#') {
				continue;
			}

			list($name, $val) = array_map('trim', explode('=', $line, 2));
			if (getenv($name) !== false) {
				continue;
			}
			putenv("$name=$val");
			$_ENV[$name] = $val;
		}
	}
}
