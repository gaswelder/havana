<?php

use PHPUnit\Framework\TestCase;
use Appget\App;

class AppTest extends TestCase
{
	function test()
	{
		$app = new App(__DIR__);
		$app->get('/', function () {
			//
		});

		$app->define('options', '/', function () {
			//
		});
	}
}
