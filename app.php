<?php

namespace havana;

use havana_internal\router;

class Exception extends \Exception
{ }

class App
{
	private $commands = [];
	private $dir;
	private $router;

	private $func = null;

	/**
	 * @param string $dir Path to the application's directory. In most cases pass `__DIR__`.
	 */
	function __construct($dir)
	{
		$this->router = new router();
		$GLOBALS['__APPDIR'] = $dir;
		$this->func = function () {
			return $this->serve();
		};
		$this->dir = $dir;

		// Read the .env file if it exists.
		$env = $this->dir . '/.env';
		if (file_exists($env)) {
			\havana_internal\env::parse($env);
		}

		// Register a class loader that loads classes from the
		// application's directory.
		spl_autoload_register(function ($className) {
			$path = $this->dir . '/classes/' . $className . '.php';
			if (file_exists($path)) {
				require_once($path);
			}
		});

		$this->commands['server'] = function () {
			$addr = 'localhost:8080';
			error_log("Starting server at $addr");
			system("php -S $addr -t public");
		};
	}

	/**
	 * Adds a middleware function.
	 *
	 * @param callable $func
	 */
	function middleware($func)
	{
		$runNext = $this->func;
		$this->func = function () use ($runNext, $func) {
			return response::make($func($runNext));
		};
	}

	function get($path, $func)
	{
		$this->router->add($path, 'get', $func);
	}

	function post($path, $func)
	{
		$this->router->add($path, 'post', $func);
	}

	/**
	 * Defines a console command.
	 *
	 * @param string $name Console command name
	 * @param callable $func Callable that will be called for this command
	 */
	function cmd($name, $func)
	{
		$this->commands[$name] = $func;
	}

	/**
	 * Runs the application.
	 */
	public function run()
	{
		if (php_sapi_name() == 'cli') {
			$args = $_SERVER['argv'];
			$cmd = $args[1];
			call_user_func_array($this->commands[$cmd], $args);
			return;
		}
		$next = $this->func;
		$response = $next();
		$response->flush();
	}

	private function serve()
	{
		$method = strtolower($_SERVER['REQUEST_METHOD']);
		$url = parse_url($_SERVER['REQUEST_URI']);
		[$matches, $route] = $this->router->find($method, $url);

		if (count($matches) == 0) {
			return response::make(response::STATUS_NOTFOUND);
		}
		if (!$route) {
			return response::make(response::STATUS_METHOD_NOT_ALLOWED);
		}
		return response::make($route->exec($method, $url['path']));
	}
}
