<?php

namespace havana_internal;

class user_role
{
    const prefix = 'userdata';

	private $session;
	private $name;

	function __construct($name)
	{
        /*
		 * Type names must be non-empty strings without the slash
		 * character since we use it to build session keys.
		 */
        if (!is_string($name) || $name == '' || strpos($name, '/') !== false) {
            throw new \Exception("invalid role name: '$name'");
        }
        $this->session = new session(self::prefix.'/'.$name);
        $this->session->set('.', $name);
	}

	function get($k, $default = null) {
		return $this->session->get($k, $default);
	}

	function set($k, $v) {
		$this->session->set($k, $v);
	}

	function del($k) {
		$this->session->del($k);
    }
    
    function clear() {
		$this->session->clear();
	}
}
