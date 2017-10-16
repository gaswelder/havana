<?php
namespace havana_internal;

class mime
{
	private static $types = array(
		'.pdf' => 'application/pdf',
		'.xml' => 'application/xml',
		'.gz' => 'application/gzip',
		'.xls' => 'application/vnd.ms-excel',
		'.xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'.zip' => 'application/zip',
		'.sh' => 'application/x-shellscript',
		'.png' => 'image/png',
		'.jpg' => 'image/jpeg',
		'.gif' => 'image/gif',
		'' => 'application/octet-stream'
	);

	/*
	 * Returns suggested MIME type for the given filename.
	 * Returns null if the extension of the filename is unknown.
	 */
	static function type($filename)
	{
		/*
		 * Get extension
		 */
		$pos = strrpos($filename, '.');
		if ($pos === false) {
			$ext = '';
		}
		else {
			$ext = substr($filename, $pos);
		}
		/*
		 * Look it up in the table
		 */
		$ext = strtolower($ext);
		if (isset(self::$types[$ext])) {
			return self::$types[$ext];
		}
		return null;
	}

	static function ext($type)
	{
		$type = strtolower($type);
		foreach (self::$types as $ext => $ptype) {
			if ($ptype == $type) {
				return $ext;
			}
		}
		return null;
	}
}

?>
