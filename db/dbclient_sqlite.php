<?php
namespace havana;

use PDO;

class dbclient_sqlite extends dbclient
{
    function __construct($url)
    {
        $u = parse_url($url);
        
        $path = $u['host'];
        if (isset($u['path'])) {
            $path .= $u['path'];
        }

        $path = $GLOBALS['__APPDIR'] . '/' . $path;
        $spec = 'sqlite:'.realpath($path);
        $this->db = new PDO($spec, null, null);
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
}
