<?php
class dbobject
{
	const TABLE_NAME = null;
	const TABLE_KEY = 'id';

	function save()
	{
		$data = [];
		foreach ($this as $k => $v) {
			$data[$k] = $v;
		}

		$key = static ::TABLE_KEY;
		if (array_key_exists($key, $data)) {
			$filter = [$key => $data[$key]];
			unset($data[$key]);
			db()->update(static ::TABLE_NAME, $data, $filter);
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
		$table_name = static ::TABLE_NAME;

		$row = db()->getRecord('select * from "'.$table_name.'" where id = ?',
			$id);
		if (!$row) {
			return null;
		}
		$obj = self::fromRow($row);
		$obj->id = $id;
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
}
