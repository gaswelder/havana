<?php

namespace DB;

use PDOException;

class Exception extends \Exception
{ }

class QueryException extends Exception
{
    private $pdoException;
    private $query;

    function __construct($message, $code = 0, PDOException $previous = null, $query)
    {
        parent::__construct($message, $code, $previous);
        $this->pdoException = $previous;
        $this->query = $query;
    }

    function getQuery()
    {
        return $this->query;
    }
}
