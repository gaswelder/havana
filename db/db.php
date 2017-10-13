<?php
namespace havana;

require __DIR__.'/dbclient_mysql.php';
require __DIR__.'/dbclient_sqllite.php';

use PDO;

class dbclient
{
	static function make($url)
	{
		$u = parse_url($url);
		if (!isset($u['scheme'])) {
			throw new Exception("Invalid URL: $url");
		}

		switch ($u['scheme']) {
			case 'mysql': return new dbclient_mysql($url);
			case 'sqlite': return new dbclient_sqlite($url);
			default: throw new Exception("Unknown database type: $u[scheme]");
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

	/*
	 * Executes a query, returns true or false
	 */
	function exec($query, ...$args)
	{
		$st = $this->run($query, $args);
		if (!$st) {
			return false;
		}
		$st->closeCursor();
		return true;
	}

	// Queries and returns multiple rows.
	function getRows($query, ...$args)
	{
		$st = $this->run($query, $args);
		$rows = $st->fetchAll(PDO::FETCH_ASSOC);
		$st->closeCursor();
		return $rows;
	}

	// Queries and returns one row from the result.
	// Returns null if there are no rows in the result.
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

	// Queries and returns one column as an array
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

	// Queries and returns a single value.
	// Returns null if there are now rows in the result.
	function getValue($query, ...$args)
	{
		$st = $this->run($query, $args);
		$row = $st->fetch(PDO::FETCH_NUM);
		$st->closeCursor();
		if (!$row) return null;
		return $row[0];
	}

	// Runs the given query with the given arguments.
	// The arguments list is given as array.
	// Returns the prepared statement after its execution.
	protected function run($query, $args)
	{
		$this->affected_rows = 0;
		$st = $this->db->prepare($query);
		$st->execute($args);
		$this->affected_rows = $st->rowCount();
		return $st;
	}

	function affectedRows()
	{
		return $this->affected_rows;
	}

	function insert($table, $record)
	{
		$header = $this->header_string($record);
		$tuple = $this->tuple_string($record);

		$st = $this->db->prepare("INSERT INTO `$table` $header VALUES $tuple");
		$st->execute(array_values($record));
		return $this->db->lastInsertId();
	}

	private function header_string($record)
	{
		$cols = array_keys($record);
		return '(`'.implode('`, `', $cols).'`)';
	}

	private function tuple_string($record)
	{
		$n = count($record);
		$placeholders = array_fill(0, $n, '?');
		return '('.implode(', ', $placeholders).')';
	}

	function update($table, $values, $filter)
	{
		$q = 'UPDATE "'.$table.'" SET ';

		$args = [];

		$set = [];
		foreach ($values as $field => $value) {
			$set[] = '"'.$field.'" = ?';
			$args[] = $value;
		}
		$q .= implode(', ', $set);

		$where = [];
		foreach ($filter as $field => $value) {
			$where[] = '"'.$field.'" = ?';
			$args[] = $value;
		}

		$q .= ' WHERE '.implode(' AND ', $where);

		$st = $this->db->prepare($q);
		$r = $st->execute($args);
		return $this->affectedRows();
	}

	/**
	 * Begins a new transaction.
	 *
	 * @throws PDOException
	 */
	function begin()
	{
		$this->db->beginTransaction();
	}

	/**
	 * Ends the current transation.
	 *
	 * @throws PDOException
	 */
	function end()
	{
		$this->db->commit();
	}

	/**
	 * Cancels the current transaction.
	 *
	 * @throws PDOException
	 */
	function cancel()
	{
		$this->db->rollback();
	}
}
