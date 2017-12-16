<?php

namespace havana;

class App
{
	private $res = ['get' => [], 'post' => []];
	private $commands = [];

	private $dir;

	private $func = null;

	function __construct($dir)
	{
		$GLOBALS['__APPDIR'] = $dir;
		$this->func = function() {
			return $this->serve();
		};
		$this->dir = $dir;

		// Read the .env file if it exists.
		$env = $this->dir.'/.env';
		if (file_exists($env)) {
			\havana_internal\env::parse($env);
		}

		$this->addLoader();
	}

	function middleware($func)
	{
		$runNext = $this->func;
		$this->func = function() use ($runNext, $func) {
			return response::make($func($runNext));
		};
	}

	/**
	 * Registers a class loader that loads classes from the
	 * application's directory.
	 */
	private function addLoader()
	{
		spl_autoload_register(function($className) {
			$path = $this->dir.'/classes/'.$className.'.php';
			if (file_exists($path)) {
				require_once($path);
			}
		});
	}

	function get($path, $func)
	{
		$this->res['get'][$path] = $func;
	}

	function post($path, $func)
	{
		$this->res['post'][$path] = $func;
	}

	function cmd($name, $func) {
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
