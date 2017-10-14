<?php
namespace havana;

use PDO;

class dummy_statement
{
    public $rows;

    function fetchAll($type)
    {
        if ($type != PDO::FETCH_NUM) {
            return $this->rows;
        }
        return array_map(function($row) {
            return array_values($row);
        }, $this->rows);
    }

    function closeCursor()
    {
        $this->rows = null;
    }
}

class dbclient_dummy extends dbclient
{
    private $func;
    function __construct($url)
    {
        $url = parse_url($url);
        $this->func = [$url['host'], substr($url['path'], 1)];
    }

    function run($query, $args) {
        $st = new dummy_statement();
        $st->rows = call_user_func($this->func, $query, $args);
        return $st;
    }
}
