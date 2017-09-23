<?php

class uploads
{
	private static $files = array();

	/*
	 * Returns array of "dicts" {type, tmp_name, size, name}
	 * describing files uploaded through the input with the given name.
	 * Returns empty array if there is no such input.
	 */
	static function get($input_name)
	{
		if (!isset(self::$files[$input_name])) {
			self::$files[$input_name] = self::prepare_files($input_name);
		}
		return self::$files[$input_name];
	}

	private static function prepare_files($input_name)
	{
		if (!isset($_FILES[$input_name])) {
			return array();
		}

		/*
		 * Get file descriptions
		 */
		$files = array();
		if (!is_array($_FILES[$input_name]['name'])) {
			$files[] = $_FILES[$input_name];
		}
		else {
			$fields = array(
				"type",
				"tmp_name",
				"error",
				"size",
				"name"
			);
			foreach ($_FILES[$input_name]['name'] as $i => $name) {
				$input = array();
				foreach ($fields as $f) {
					$input[$f] = $_FILES[$input_name][$f][$i];
				}
				$files[] = $input;
			}
		}

		/*
		 * Filter out files with errors
		 */
		$ok = array();
		foreach ($files as $file) {
			/*
			 * This happens with multiple file inputs with the same
			 * name marked with '[]'.
			 */
			if ($file['error'] == UPLOAD_ERR_NO_FILE) {
				continue;
			}

			if ($file['error'] || !$file['size']) {
				$errstr = self::errstr($file['error']);
				warning("Upload of file '$file[name]' failed ($errstr, size=$file[size])");
				continue;
			}
			unset($file['error']);

			$size = round($file['size']/1024, 2);
			// h3::log("Upload: $file[name] ($size KB, $file[type])");

			$ok[] = $file;
		}

		return $ok;
	}

	static function save($files, $dest_dir)
	{
		if (empty($files)) {
			return [];
		}

		/*
		 * Make sure dest_dir ends with a slash.
		 */
		if (substr($dest_dir, -1) != '/') {
			$dest_dir .= '/';
		}

		/*
		 * Create the directory if needed.
		 */
		if (!is_dir($dest_dir) && !@mkdir($dest_dir)) {
			trigger_error("Could not create upload directory '$dest_dir'");
			return array();
		}

		$paths = array();
		foreach ($files as $file) {
			$path = self::save_file($file, $dest_dir);
			if ($path) {
				$paths[] = $path;
			}
		}
		return $paths;
	}

	/*
	 * Saves the file to the given directory and returns
	 * the full path of the saved file.
	 */
	private static function save_file($file, $dest_dir)
	{
		$path = self::newpath($file, $dest_dir);
		if (!$path) {
			trigger_error("Could not determine path for an uploaded file");
			return null;
		}

		if (!move_uploaded_file($file['tmp_name'], $path)) {
			trigger_error("Could not move uploaded file $f[tmp_name]");
			return null;
		}

		// h3::log("Upload: save $file[name] to $path");
		return $path;
	}

	/*
	 * Generates a name for the given file to be stored in the
	 * 'dest_dir' directory.
	 */
	private static function newpath($file, $dest_dir)
	{
		/*
		 * Generate a path for the new file.
		 */
		$name = self::newname($file);
		$path = $dest_dir.$name;

		/*
		 * Avoid duplicate filenames by adding a counter if necessary.
		 */
		$i = 0;
		while (file_exists($path)) {
			warning("Filename collision in uploads::newpath: $path");
			$i++;
			if ($i >= 10) {
				$path = null;
				break;
			}
			$path = $dest_dir.self::noext($name)."-$i".self::ext($name);
		}
		/*
		 * If counter didn't work, resort to adding a random string.
		 */
		if (!$path) {
			$path = $dest_dir.self::noext($name).'-'.uniqid().self::ext($name);
		}
		return $path;
	}

	/*
	 * Creates a name for the given file
	 */
	private static function newname($file)
	{
		/*
		 * If $file has a special 'save_name' field, we will use it.
		 */
		if (isset($file['save_name'])) {
			$name = $file['save_name'];
			if (strpos($name, '/') !== false) {
				error("Invalid save_name for an uploaded file");
				return null;
			}
			return $name;
		}

		/*
		 * Determine the extension based on the MIME type and file name
		 * given by the user agent.
		 */
		$ext = mime::ext($file['type']);
		if ($ext === null) {
			warning("Unknown uploaded file type: $file[type]");
			$ext = self::ext($file['name']);
		}
		if ($ext == '' && strpos($file['name'], '.') !== false) {
			warning("File '$file[name]' uploaded as octet-stream");
			$ext = self::ext($file['name']);
		}
		if ($ext == '.php') {
			warning(".php file uploaded");
			$ext .= '.txt';
		}
		return uniqid().$ext;
	}

	/*
	 * Returns maximum upload size defined
	 * by current configuration
	 */
	static function maxsize()
	{
		$s1 = self::parse_size(ini_get('post_max_size'));
		$s2 = self::parse_size(ini_get('upload_max_filesize'));
		if ($s1 == -1) return $s2;
		if ($s2 == -1) return $s1;
		return min($s1, $s2);
	}

	private static function parse_size($s)
	{
		if ($s == '-1' || $s === false) return -1;
		preg_match('/^(\d+)(.*?)$/', $s, $m);
		$units = array(
			'K' => 1024,
			'M' => 1024 * 1024,
			'G' => 1024 * 1024 * 1024
		);
		$u = $m[2];
		if (!isset($units[$u])) {
			trigger_error("Unknown size unit in '$s'");
			return -1;
		}
		return $m[1] * $units[$u];
	}

	private static function ext($filename)
	{
		$ext = pathinfo($filename, PATHINFO_EXTENSION);
		if ($ext != '') $ext = '.'.$ext;
		return strtolower($ext);
	}

	private static function noext($filename)
	{
		return pathinfo($filename, PATHINFO_FILENAME);
	}

	private static function errstr($errno)
	{
		switch ($errno) {
		case UPLOAD_ERR_OK:
			return "no error";
		case UPLOAD_ERR_INI_SIZE:
			return "the file exceeds the 'upload_max_filesize' limit";
		case UPLOAD_ERR_FORM_SIZE:
			return "the file exceeds the 'MAX_FILE_SIZE' directive that was specified in the HTML form";
		case UPLOAD_ERR_PARTIAL:
			return "the file was only partially uploaded";
		case UPLOAD_ERR_NO_FILE:
			return "no file was uploaded";
		case UPLOAD_ERR_NO_TMP_DIR:
			return "missing temporary folder";
		case UPLOAD_ERR_CANT_WRITE:
			return "failed to write file to disk";
		default:
			return "unknown error ($errno)";
		}
	}
}

?>
