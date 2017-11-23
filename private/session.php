<?php

namespace havana_internal;

class session
{
	private $name;

	function __construct($name)
	{
		$this->name = $name;
	}

	private function fullKey($key)
	{
		return $this->name.'/'.$key;
	}

	function set($key, $value)
	{
		$key = $this->fullKey($key);
		$_SESSION[$key] = $value;
	}

	function del($key)
	{
		$key = $this->fullKey($key);
		unset($_SESSION[$key]);
	}

	function get($key, $default = null)
	{
		$key = $this->fullKey($key);
		return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }
    
    function clear()
    {
        $prefix = $this->name.'/';
        foreach (array_keys($_SESSION) as $k) {
            if (strpos($k, $prefix) === 0) {
                unset($_SESSION[$k]);
            }
        }
    }
}
