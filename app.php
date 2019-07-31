<?php

namespace havana;

use Appget\Env;
use Appget\internal\router;

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
		$endPath = $this->dir . '/.env';
		if (file_exists($endPath)) {
			Env::parse($endPath);
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

	function mount($path, $pattern, $resource)
	{
		// File-level
		$methods = ['get', 'put', 'patch', 'delete'];
		foreach ($methods as $method) {
			if (method_exists($resource, $method)) {
				$this->router->add("$path/$pattern", $method, [$resource, $method]);
			}
		}

		// Directory-level
		if (method_exists($resource, 'create')) {
			$this->router->add($path, 'post', [$resource, 'create']);
		}
		if (method_exists($resource, 'getAll')) {
			$this->router->add($path, 'get', [$resource, 'getAll']);
		}
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
		[$resource, $args] = $this->router->find($url['path']);

		if (!$resource) {
			return response::make(response::STATUS_NOTFOUND);
		}
		if (!isset($resource[$method])) {
			return response::make(response::STATUS_METHOD_NOT_ALLOWED);
		}
		$r = call_user_func_array($resource[$method], $args);
		return response::make($r);
	}
}
