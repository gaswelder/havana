<?php
class dbclient
{
	/*
	 * The connection object
	 */
	private $db = null;

	/*
	 * Number of rows affected by the last query
	 */
	private $affected_rows = 0;

	function __construct($url)
	{
		$u = parse_url($url);
		if (!isset($u['scheme'])) {
			throw new Exception("Invalid URL");
		}

		if (!isset($u['user'])) {
			$u['user'] = null;
		}
		if (!isset($u['pass'])) {
			$u['pass'] = null;
		}

		$dbname = substr($u['path'], 1);
		switch ($u['scheme']) {
		case 'mysql':
			$spec = "mysql:dbname=$dbname;host=$u[host]";
			break;
		case 'sqlite':
			$spec = 'sqlite:'.realpath($dbname);
			break;
		default:
			throw new Exception("Unknown database: $u[scheme]");
		}

		$this->db = new PDO($spec, $u['user'], $u['pass']);

		/*
		 * If we work with mysql and mysqlnd is used, get it to
		 * preserve value types.
		 */
		if ($u['scheme'] == 'mysql') {
			$dr = $this->db->getAttribute(PDO::ATTR_CLIENT_VERSION);
			if (strpos($dr, 'mysqlnd') !== false) {
				$this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
			}
			/*
			 * The `charset` parameter in DSN doesn't work
			 * reliably, thus this query.
			 */
			$this->db->exec("SET NAMES UTF8");
			/*
			 * Use standard quoting, not mysql-specific.
			 */
			$this->db->exec("SET SESSION sql_mode = 'ANSI'");
		}
	}

	/*
	 * Executes a query, returns true or false
	 */
	function exec($query, $__args__ = null)
	{
		$st = $this->run(func_get_args());
		if (!$st) {
			return false;
		}
		$st->closeCursor();
		return true;
	}

	/*
	 * Queries and returns multiple rows
	 */
	function getRecords($query, $__args__ = null)
	{
		$st = $this->run(func_get_args());
		$rows = $st->fetchAll(PDO::FETCH_ASSOC);
		$st->closeCursor();
		return $rows;
	}

	/*
	 * Queries and returns one row
	 */
	function getRecord($query, $__args__ = null)
	{
		$st = $this->run(func_get_args());
		$rows = $st->fetchAll(PDO::FETCH_ASSOC);
		$st->closeCursor();
		if (empty($rows)) {
			return null;
		}
		if (count($rows) > 1) {
			trigger_error("getRecord received more than one row");
		}
		return $rows[0];
	}

	/*
	 * Queries and returns one column as an array
	 */
	function getValues($query, $args = null)
	{
		$st = $this->run(func_get_args());
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

	/*
	 * Queries and returns one value
	 */
	function getValue($query, $__args__ = null)
	{
		$st = $this->run(func_get_args());
		$values = [];
		while (1) {
			$row = $st->fetch(PDO::FETCH_NUM);
			if (!$row) {
				break;
			}
			$values[] = $row[0];
		}
		$st->closeCursor();
		if (empty($values)) {
			return null;
		}

		if (count($values) > 1) {
			trigger_error("getValue received more than one value");
		}
		return $values[0];
	}

	private function run($args)
	{
		$this->affected_rows = 0;
		$tpl = array_shift($args);
		$st = $this->db->prepare($tpl);
		if (!$st) {
			trigger_error("Couldn't prepare $tpl");
			return null;
		}
		if (!$st->execute($args)) {
			trigger_error("Couldn't run $tpl");
			return null;
		}

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

	function begin()
	{
		if (!$this->db->beginTransaction()) {
			trigger_error("Couldn't start transaction");
		}
	}

	function end()
	{
		if (!$this->db->commit()) {
			trigger_error("Couldn't commit transaction");
		}
	}

	function cancel()
	{
		if (!$this->db->rollback()) {
			trigger_error("Couldn't cancel transaction");
		}
	}
}
