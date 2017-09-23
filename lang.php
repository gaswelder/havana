<?php
/*
The `lang` extension is a simpler alternative to `gettext`. It may be chosen for
smaller sites where setting up `gettext` wouldn't give much advantage or on
hostings where `gettext` isn't available or doesn't work properly.

The functions that accept a `lang` argument expect it to be in the HTTP
`accept-language` format, which is:

	1*8ALPHA *( "-" 1*8ALPHA)

Examples are `en`, `en-GB`, `my-funky-dialect`.

Translation file for language `lang` is expected to be at
`<appdir>/lang/<lowecase(lang)>`. That is, the translation file for language
`en-GB` would be `<appdir>/lang/en-gb`.

The file format is plain text with a repeated sequence of lines:
(message line, translation line, empty line). An example would be:

	Hello
	Hey man

	How do you do?
	Wassup?

	Popular items
	Hot stuff
*/


class lang
{
	private static $lang = null;
	private static $dicts = array();

	/*
	 * Returns true if there is a file for the given language.
	 */
	static function have($lang)
	{
		if (!self::valid($lang)) {
			error("Invalid language id: '$lang'");
			return false;
		}
		$path = self::path($lang);
		return file_exists($path);
	}

	/*
	 * Sets default language for lookups.
	 */
	static function set($lang)
	{
		if (!self::valid($lang)) {
			trigger_error("Invalid language id: '$lang'");
			return;
		}
		self::$lang = $lang;
	}

	/*
	 * Returns the default language name used for lookups.
	 */
	static function get()
	{
		return self::$lang;
	}

	/*
	 * Returns the translation for `msg`, if there is one, or `msg` itself
	 * if there isn't.
	 */
	static function lookup($msgid)
	{
		$lang = self::$lang;
		if (!$lang) {
			return $msgid;
		}
		if (!isset(self::$dicts[$lang])) {
			self::load_dict($lang);
		}
		if (array_key_exists($msgid, self::$dicts[$lang])) {
			return self::$dicts[$lang][$msgid];
		}
		return $msgid;
	}

	private static function load_dict($lang)
	{
		$path = self::path($lang);
		if (file_exists($path)) {
			$dict = self::parse($path);
		}
		else {
			$dict = array();
		}
		self::$dicts[$lang] = $dict;
	}

	/*
	 * Returns true if the given language identifier is valid.
	 */
	private static function valid($lang)
	{
		/*
		 * The form of HTTP 'accept-language' token:
		 * 1*8ALPHA *( "-" 1*8ALPHA)
		 * Valid examples are "havaho-funky-dialect" and "en-US".
		 */
		$alpha8 = '[a-zA-Z]{1,8}';
		return preg_match("/$alpha8(-$alpha8)*/", $lang);
	}

	/*
	 * Parses the language file with the given path and returns the
	 * dictionary.
	 */
	private static function parse($path)
	{
		$dict = array();
		$lines = array_map('trim', file($path));
		$n = count($lines);

		$i = 0;
		while ($i < $n-1) {
			$msgid = $lines[$i++];
			$text = $lines[$i++];
			$dict[$msgid] = $text;

			if ($i >= $n) break;

			if ($lines[$i++]) {
				warning("Empty line expected at file $path, line ".($i+1));
				break;
			}
		}
		return $dict;
	}

	/*
	 * Returns path for the given language file.
	 */
	private static function path($lang)
	{
		$path = 'lang/'.strtolower($lang);
		return $path;
	}
}
