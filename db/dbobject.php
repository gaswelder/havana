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
}

