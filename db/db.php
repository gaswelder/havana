<?php
namespace havana;

require __DIR__ . '/dbclient_mysql.php';
require __DIR__ . '/dbclient_sqlite.php';
require __DIR__ . '/dbclient_dummy.php';

use PDO;
use PDOException;
use DB\SQL;

class DBException extends Exception
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

class dbclient
{
	static function make($url)
	{
		$u = parse_url($url);
		if (!isset($u['scheme'])) {
			throw new Exception("Invalid URL: $url");
		}

		switch ($u['scheme']) {
			case 'mysql':
				return new dbclient_mysql($url);
			case 'sqlite':
				return new dbclient_sqlite($url);
			case 'dummy':
				return new dbclient_dummy($url);
			default:
				throw new Exception("Unknown database type: $u[scheme]");
		}
	}

	/*
	 * The connection object
	 */
	protected $db = null;

	/*
	 * Number of rows affected by the last query
	 */
	private $affected_rows = 0;

	/**
	 * Executes a query, returns true or false.
	 *
	 * @param string $query Query template
	 * @param mixed $args Template arguments
	 * @return bool
	 */
	function exec($query, ...$args)
	{
		if (empty($args)) {
			return $this->db->exec($query);
		}
		$st = $this->run($query, $args);
		if (!$st) {
			return false;
		}
		$st->closeCursor();
		return true;
	}

	/**
	 * Queries and returns multiple rows as array of arrays.
	 *
	 * @param string $query Query template
	 * @param mixed $args Template arguments
	 * @return array
	 */
	function getRows($query, ...$args)
	{
		$st = $this->run($query, $args);
		$rows = $st->fetchAll(PDO::FETCH_ASSOC);
		$st->closeCursor();
		return $rows;
	}

	/**
	 * Queries and returns one row from the result.
	 * Returns null if there are no rows in the result.
	 *
	 * @param string $query Query template
	 * @param mixed $args Template arguments
	 * @return array|null
	 */
	function getRow($query, ...$args)
	{
		$st = $this->run($query, $args);
		$rows = $st->fetchAll(PDO::FETCH_ASSOC);
		$st->closeCursor();
		if (empty($rows)) {
			return null;
		}
		return $rows[0];
	}

	/**
	 * Queries and returns one column as an array.
	 *
	 * @param string $query Query template
	 * @param mixed $args Template arguments
	 * @return array
	 */
	function getValues($query, ...$args)
	{
		$st = $this->run($query, $args);
		$values = [];
		while (1) {
			$row = $st->fetch(PDO::FETCH_NUM);
			if (!$row) {
				break;
			}
			$values[] = $row[0];
		}
		$st->closeCursor();
		return $values;
	}

	/**
	 * Queries and returns a single value.
	 * Returns null if there are now rows in the result.
	 *
	 * @param string $query Query template
	 * @param mixed $args Template arguments
	 * @return mixed|null
	 */
	function getValue($query, ...$args)
	{
		$st = $this->run($query, $args);
		$row = $st->fetch(PDO::FETCH_NUM);
		$st->closeCursor();
		if (!$row) return null;
		return $row[0];
	}

	/**
	 * Inserts a row given as a dict into the specified table.
	 * Returns primary key for the inserted row.
	 *
	 * @param string $table
	 * @param array $row
	 * @return mixed
	 */
	function insert($table, $row)
	{
		list($query, $args) = SQL::insert($table, $row);
		$st = $this->run($query, $args);
		$st->closeCursor();
		return $this->db->lastInsertId();
	}

	/**
	 * Updates the specified table setting values from the 'values' dict
	 * where rows match the given filter.
	 * Returns number of affected rows.
	 *
	 * @param string $table
	 * @param array $values Field->value map to set
	 * @param array $filter Field->value rows filter
	 * @return int
	 */
	function update($table, $values, $filter)
	{
		list($query, $args) = sqlWriter::update($table, $values, $filter);
		$st = $this->run($query, $args);
		$st->closeCursor();
		return $this->affectedRows();
	}

	// Composes a select query from the given arguments
	// and returns results from getRows call with that query.
	function select($table, $fields, $filter, $order)
	{
		list($query, $args) = sqlWriter::select($table, $fields, $filter, $order);
		$rows = call_user_func_array([$this, 'getRows'], array_merge([$query], $args));
		return $rows;
	}

	// Runs the given query with the given arguments.
	// The arguments list is given as array.
	// Returns the prepared statement after its execution.
	protected function run($query, $args)
	{
		$this->affected_rows = 0;
		try {
			$st = $this->db->prepare($query);
			$st->execute($args);
			$this->affected_rows = $st->rowCount();
		} catch (PDOException $e) {
			$msg = $e->getMessage() . '; query: ' . $query;
			throw new DBException($msg, 0, $e, $query);
		}
		return $st;
	}

	function affectedRows()
	{
		return $this->affected_rows;
	}
}
