<?php

namespace DB;

use PDO;

class dbclient_sqlite extends Client
{
    function __construct($path)
    {
        if (!file_exists($path)) {
            throw new Exception("sqlite file does not exist: " . $path);
        }
        $spec = 'sqlite:' . realpath($path);
        $this->db = new PDO($spec, null, null);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
}
