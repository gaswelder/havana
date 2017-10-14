<?php

require __DIR__.'/main.php';

use havana\dbobject;
use PHPUnit\Framework\TestCase;

putenv('DATABASE=dummy://dfc/query');

class dfc
{
	private static $expect = [];

	static function expect($query, $args, $result)
	{
		self::$expect[] = [$query, $args, $result];
	}

	static function query($query, $args)
	{
		$r = array_pop(self::$expect);
		if (!$r) {
			throw new Exception("unexpected query: $query");
		}
		if ($r[0] != $query || ($r[1] <=> $args) != 0) {
			throw new Exception("expected $r[0], got $query");
		}
		return $r[2];
		
	}
}

class foo extends dbobject
{
	const TABLE_NAME = 'foo';

	public $name;
	public $date;
}

class MainTest extends TestCase
{
	function test()
	{
		dfc::expect('SELECT "id", "name", "date" FROM "foo" WHERE "id" = ?', [42], [['id' => 42, 'name' => 'foo', 'date' => 0]]);
		$obj = foo::get(42);
		$this->assertTrue($obj != null);
	}
}
