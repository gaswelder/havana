<?php
namespace havana;

use Exception;

class dbobject
{
	const TABLE_NAME = '__OVERRIDE_THIS!';
	const TABLE_KEY = 'id';
	const DATABASE = null;

	/**
	 * Returns the underlying database object.
	 *
	 * @return dbclient
	 */
	static function db()
	{
		return db(static::DATABASE);
	}

	// Route access to "id" property to the appropriate field depending
	// on the TABLE_KEY constant.
	function __get($k)
	{
		if ($k == 'id') {
			$k = static::TABLE_KEY;
		}
		if ($k == static::TABLE_KEY) {
			if (property_exists($this, $k)) {
				return $this->$k;
			}
			return null;
		}
		throw new Exception("unknown property: $k in class '" . static::class . "'");
	}

	function __set($k, $v)
	{
		$key = static::TABLE_KEY;
		if ($k == 'id') {
			$k = $key;
		}
		if ($k == $key) {
			$this->$key = $v;
			return;
		}
		throw new Exception("unknown property: $k in class '" . static::class . "'");
	}

	function save()
	{
		$data = [];
		foreach (static::fields() as $key) {
			$data[$key] = $this->$key;
		}
		$data = $this->formatData($data);

		$key = static::TABLE_KEY;
		if (array_key_exists($key, $data) && $data[$key]) {
			$filter = [$key => $data[$key]];
			unset($data[$key]);
			static::db()->update(static::TABLE_NAME, $data, $filter);
		} else {
			$this->$key = static::db()->insert(static::TABLE_NAME, $data);
		}

		return $this->$key;
	}

	protected function formatData($data)
	{
		return $data;
	}

	protected function parseData($data)
	{
		return $data;
	}

	private function assign($data)
	{
		$data = $this->parseData($data);
		foreach (static::fields() as $k) {
			if (isset($data[$k])) {
				$this->$k = $data[$k];
			}
		}
	}

	static function fromRows($rows)
	{
		$list = [];
		foreach ($rows as $row) {
			$list[] = self::fromRow($row);
		}
		return $list;
	}

	static function fromRow($row)
	{
		if (!$row) return null;
		$l = new static();
		$l->assign($row);
		return $l;
	}

	/**
	 * Returns the object with the given identifier or null
	 * if there is no such object in the database.
	 *
	 * @return static|null
	 */
	static function get($id)
	{
		$key = static::TABLE_KEY;
		$r = static::find([$key => $id]);
		return isset($r[0]) ? $r[0] : null;
	}

	/**
	 * Returns array of objects with the given identifiers.
	 *
	 * @return array
	 */
	static function getMultiple($ids)
	{
		$r = [];
		foreach ($ids as $id) {
			$r[] = self::get($id);
		}
		return $r;
	}

	/**
	 * Returns the first instance matching the given filter.
	 * If there is no match, creates and saves a new instance and returns it.
	 *
	 * @param array $filter
	 * @return static
	 */
	static function findOrCreate($filter)
	{
		$cond = [];
		$values = [];
		foreach ($filter as $field => $value) {
			$cond[] = "$field = ?";
			$values[] = $value;
		}
		$q = 'SELECT id FROM "' . static::TABLE_NAME . '" WHERE ' . implode(' AND ', $cond);
		$id = call_user_func_array([static::db(), 'getValue'], array_merge([$q], $values));
		if ($id) return static::get($id);
		$obj = new static();
		foreach ($filter as $k => $v) {
			$obj->$k = $v;
		}
		$obj->save();
		return $obj;
	}

	static function find($filter, $order = null)
	{
		$keys = static::fields();
		$filter = array_merge(static::getBaseFilter(), $filter);
		$rows = static::db()->select(static::TABLE_NAME, $keys, $filter, $order);
		return static::fromRows($rows);
	}

	static function findOne($filter, $order = null)
	{
		$r = static::find($filter, $order);
		return isset($r[0]) ? $r[0] : null;
	}

	protected static function getBaseFilter()
	{
		return [];
	}

	// Returns list of column names for this object
	private static function fields()
	{
		$keys = array_keys(get_class_vars(static::class));
		$id = static::TABLE_KEY;
		if (!in_array($id, $keys)) {
			array_unshift($keys, $id);
		}
		return $keys;
	}
}
