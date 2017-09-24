<?php
class dbobject
{
	const TABLE_NAME = '__OVERRIDE_THIS!';
	const TABLE_KEY = 'id';

	// Route access to "id" property to the appropriate field depending
	// on the TABLE_KEY constant.
	function __get($k)
	{
		if ($k == 'id' && static::TABLE_KEY != 'id') {
			$k = static::TABLE_KEY;
			if (property_exists($this, $k)) {
				return $this->$k;
			}
			return null;
		}
		throw new Exception("unknown property: $k");
	}

	function save()
	{
		$data = [];
		foreach ($this as $k => $v) {
			$data[$k] = $v;
		}

		$key = static::TABLE_KEY;
		if (array_key_exists($key, $data)) {
			$filter = [$key => $data[$key]];
			unset($data[$key]);
			db()->update(static::TABLE_NAME, $data, $filter);
		}
		else {
			$this->id = db()->insert(static ::TABLE_NAME, $data);
		}

		return $this->id;
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
		$l = new static ();
		foreach ($row as $k => $v) {
			$l->$k = $v;
		}
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
		$table_name = static::TABLE_NAME;
		$key = static::TABLE_KEY;

		$row = db()->getRecord('select * from "'.$table_name.'" where "'.$key.'" = ?', $id);
		if (!$row) {
			return null;
		}
		$obj = self::fromRow($row);
		return $obj;
	}

	/**
	 * Returns array of objects with the given identifiers.
	 *
	 * @return array
	 */
	static function getMultiple($ids)
	{
		$r = [];
		foreach($ids as $id) {
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
		$q = 'SELECT id FROM '.static::TABLE_NAME.' WHERE '.implode(' AND ', $cond);
		$id = call_user_func_array([db(), 'getValue'], array_merge([$q], $values));
		if ($id) return static::get($id);
		$obj = new static();
		foreach ($filter as $k => $v) {
			$obj->$k = $v;
		}
		$obj->save();
		return $obj;
	}

	static function find($filter)
	{
		$filter = array_merge(static::getBaseFilter(), $filter);
		$cond = [];
		$values = [];
		foreach ($filter as $field => $value) {
			if ($value === null) {
				$cond[] = '"'.$field.'" IS NULL';
				continue;
			}
			$cond[] = '"'.$field.'" = ?';
			$values[] = $value;
		}
		$q = 'SELECT * FROM '.static::TABLE_NAME;
		if (!empty($cond)) {
			$q .= ' WHERE '.implode(' AND ', $cond);
		}
		$rows = call_user_func_array([db(), 'getRecords'], array_merge([$q], $values));
		return static::fromRows($rows);
	}

	protected static function getBaseFilter()
	{
		return [];
	}
}
