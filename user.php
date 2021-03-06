<?php

namespace havana;

require __DIR__ . '/private/session.php';
require __DIR__ . '/private/user_role.php';

use havana_internal\user_role;

class user
{
	private static $roles = [];
	private static $init = false;

	private static function init()
	{
		if (self::$init) return;
		self::$init = true;
		if (!isset($_SESSION)) {
			session_start();
		}

		$keys = [];
		foreach (array_keys($_SESSION) as $k) {
			$parts = explode('/', $k);
			// Look for keys in format "<prefix>/<name>/."
			if (count($parts) == 3 && $parts[0] == user_role::prefix && $parts[2] == '.') {
				$keys[] = $parts[1];
			}
		}

		foreach ($keys as $k) {
			self::$roles[$k] = new user_role($k);
		}
	}

	static function addRole($name, $id = null)
	{
		// If this role already exists, clear its data.
		$role = self::getRole($name);
		if ($role) {
			$role->clear();
		}
		self::$roles[$name] = new user_role($name, $id);
	}

	static function getRole($name)
	{
		self::init();
		if (!isset(self::$roles[$name])) return null;
		return self::$roles[$name];
	}

	static function removeRole($name)
	{
		self::init();
		$role = isset(self::$roles[$name]) ? self::$roles[$name] : null;
		if ($role) {
			$role->clear();
		}
		unset(self::$roles[$name]);
	}
}
