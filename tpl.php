<?php

use Appget\Exception;

function tpl($name, $vars = [])
{
	$__name = $name;
	$__path = $GLOBALS['__APPDIR'] . "/templates/$name.php";

	if (!file_exists($__path)) {
		throw new Exception("could not find template file '$name'");
	}
	$src = file_get_contents($__path);
	preg_match_all('/\{\{(.*?)\}\}/', $src, $m);
	foreach ($m[0] as $i => $s) {
		$src = str_replace($s, '<?= htmlspecialchars(' . $m[1][$i] . ') ?>', $src);
	}

	//$__path = tempnam(sys_get_temp_dir(), 'tpl');
	$__path = sys_get_temp_dir() . '/' . basename($name) . '-' . md5($src);
	file_put_contents($__path, $src);
	unset($src);
	unset($name);
	unset($m);
	unset($s);

	extract($vars);
	ob_start();
	try {
		include $__path;
		return ob_get_clean();
	} catch (Exception $e) {
		ob_clean();
		throw new Exception("error in template '$__name': " . $e->getMessage(), $e->getCode(), $e);
	}
}
