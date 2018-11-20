<?php
namespace havana;

class Exception extends \Exception
{

}

class App
{
	private $res = ['get' => [], 'post' => []];
	private $commands = [];

	private $dir;

	private $func = null;

	/**
	 * @param string $dir Path to the application's directory. In most cases pass `__DIR__`.
	 */
	function __construct($dir)
	{
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
		$this->res['get'][$path] = $func;
	}

	function post($path, $func)
	{
		$this->res['post'][$path] = $func;
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
		$op = strtolower($_SERVER['REQUEST_METHOD']);
		if (!isset($this->res[$op])) {
			return response::make(response::STATUS_METHOD_NOT_ALLOWED);
		}

		$url = parse_url($_SERVER['REQUEST_URI']);
		$requestedPath = $url['path'];

		$match_args = [];
		$match = null;
		foreach ($this->res[$op] as $path => $func) {
			$args = \havana_internal\match_url($requestedPath, $path);
			if ($args === false) {
				continue;
			}

			if (!$match || count($args) > count($match_args)) {
				$match = $func;
				$match_args = $args;
			}
		}

		if (!$match) {
			return response::make(response::STATUS_NOTFOUND);
		}

		// If the given handler is a class, call its "run" method.
		// If not, call the handler as a function.
		if (!is_callable($match) && class_exists($match)) {
			$match = [new $match, 'run'];
		}
		$val = call_user_func_array($match, $match_args);
		return response::make($val);
	}
}
