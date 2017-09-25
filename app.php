<?php

class App
{
	private $res = ['get' => [], 'post' => []];
	private $prefix = '';

	private $before = [];

	private $dir;

	private $func = null;

	function __construct($dir)
	{
		$this->func = function() {
			return $this->serve();
		};
		$this->dir = $dir;
		$this->parseEnv();
		$this->addLoader();
	}

	function middleware($func)
	{
		$runNext = $this->func;
		$this->func = function() use ($runNext, $func) {
			return response::make($func($runNext));
		};
	}

	function setPrefix($pref)
	{
		$this->prefix = $pref;
	}

	private function parseEnv()
	{
		$path = $this->dir.'/.env';
		if (!file_exists($path)) {
			return;
		}
		$lines = array_map('trim', file($path));
		foreach ($lines as $line) {
			if (strlen($line) == 0 || $line[0] == '#') {
				continue;
			}

			list($name, $val) = array_map('trim', explode('=', $line, 2));
			if (getenv($name) !== false) {
				continue;
			}
			putenv("$name=$val");
			$_ENV[$name] = $val;
		}
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
		$f = str_replace('//', '/', $this->prefix.'/'.$path);
		$this->res['get'][$f] = $func;
	}

	function post($path, $func)
	{
		$f = str_replace('//', '/', $this->prefix.'/'.$path);
		$this->res['post'][$f] = $func;
	}

	function beforeDispatch($func)
	{
		$this->before[] = $func;
	}

	/**
	 * Runs the application.
	 */
	public function run()
	{
		$GLOBALS['__APPDIR'] = $this->dir;
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

		foreach ($this->before as $func) {
			$r = call_user_func($func, $requestedPath);
			if ($r) {
				return response::make($r);
			}
		}

		$match_args = [];
		$match = null;
		foreach ($this->res[$op] as $path => $func) {
			$args = match_url($requestedPath, $path);
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
