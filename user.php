<?php

namespace havana;

/*
 * Session interface for storing authentication results and other data.
 */
class user
{
	/*
	 * Since we share a global storage, we localize our data by adding
	 * a prefix, so saving data with key "key" is really saving it with
	 * key KEY_PREFIX/$type/key.
	 */
	const KEY_PREFIX = '_userdata_';

	private static $type = 'guest';

	/*
	 * Switches to another user type. If the user was not authenticated
	 * for that type, all data values will be null and false will be
	 * returned.
	 */
	static function select($type)
	{
		if (!self::type_valid($type)) {
			trigger_error("Invalid type name");
			return false;
		}

		if (self::have_type($type)) {
			self::$type = $type;
			return true;
		}
		else {
			return false;
		}
	}

	/*
	 * Adds given credentials pair to the existing set. If a pair with
	 * the same type exists, it is removed before the new one is added.
	 */
	static function auth($type, $id = null)
	{
		if (!self::type_valid($type)) {
			trigger_error("Invalid type name: $type");
			return;
		}

		if (self::have_type($type)) {
			self::clear($type);
		}

		self::$type = $type;
		self::sset('type', $type);
		self::sset('id', $id);
	}

	/*
	 * Transfers all data from the guest to another identity.
	 */
	static function transfer($type)
	{
		if (!self::have_type($type)) {
			trigger_error("Can't transfer data, no type '$type'");
			return;
		}

		$s = &self::s();

		/*
		 * Get all data assigned to the guest identity.
		 */
		$data = array();
		$pref = self::key('guest', '');
		foreach ($s as $k => $v) {
			if (strpos($k, $pref) !== 0) continue;
			$name = substr($k, strlen($pref));
			$data[$name] = $v;
		}

		/*
		 * Add that data to the new identity.
		 */
		$pref = self::key($type, '');
		foreach ($data as $k => $v) {
			$s[$pref.$k] = $v;
		}

		/*
		 * Clear the guest data.
		 */
		self::clear('guest');
	}

	/*
	 * Returns currently selected user type.
	 */
	static function type()
	{
		return self::$type;
	}

	/*
	 * Returns currently selected user identifier.
	 */
	static function id()
	{
		return self::sget('id');
	}

	/*
	 * Store arbitrary key-value pair.
	 */
	static function set($key, $value)
	{
		self::sset('data-'.$key, $value);
	}

	/*
	 * Retrieve arbitrary key-value pair.
	 */
	static function get($key)
	{
		return self::sget('data-'.$key);
	}

	/*
	 * Returns a string describing the user identity,
	 * for example "admin#13@127.0.0.1"
	 */
	static function tag()
	{
		$tag = self::type();
		if (!$tag) {
			$tag = 'nobody';
		}

		$id = self::id();
		if ($id) {
			$tag .= "#$id";
		}

		$tag .= '@'.$_SERVER['REMOTE_ADDR'];
		return $tag;
	}

	private static function have_type($type)
	{
		if ($type == 'guest') return true;
		$k = self::key($type, 'type');
		$s = self::s();
		return isset($s[$k]);
	}

	/*
	 * Removes the credentials pair with the given type, and all
	 * associated data.
	 */
	static function clear($type)
	{
		$prefix = self::key($type, '');
		$s = &self::s();
		$K = array_keys($s);
		foreach ($K as $k) {
			if (strpos($k, $prefix) === 0) {
				unset($s[$k]);
			}
		}
		self::$type = 'guest';
	}

	/*
	 * Returns actual session data key for the given user key.
	 */
	private static function key($type, $key)
	{
		return self::KEY_PREFIX.'/'.$type.'/'.$key;
	}

	/*
	 * Tells whether the type name is valid.
	 */
	private static function type_valid($type)
	{
		/*
		 * Type names must be non-empty strings without the slash
		 * character since we use it to build session keys.
		 */
		return (is_string($type) && $type != '' && strpos($type, '/') === false);
	}

	/*
	 * Initializes the session if needed.
	 * Returns a reference to the $_SESSION superglobal.
	 */
	private static function &s()
	{
		if (!isset($_SESSION)) {
			session_start();
		}
		return $_SESSION;
	}

	/*
	 * Save a key-value pair. $value set to null means "delete".
	 */
	private static function sset($key, $value)
	{
		$key = self::key(self::$type, $key);
		$s = &self::s();
		if ($value === null) {
			unset($s[$key]);
		}
		else {
			$s[$key] = $value;
		}
	}

	/*
	 * Returns the value stored with the given key. Returns $default if
	 * the value is not set.
	 */
	private static function sget($key, $default = null)
	{
		$key = self::key(self::$type, $key);
		$s = &self::s();
		if (!isset($s[$key])) {
			return $default;
		}
		else {
			return $s[$key];
		}
	}
}
