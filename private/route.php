<?php
namespace havana_internal;

class route
{
    public $get;
    public $post;
    private $pattern;

    function __construct($pattern)
    {
        $this->pattern = new pattern($pattern);
    }

    /**
     * Returns true if the given URL path matches this route.
     *
     * @param string $path
     * @return bool
     */
    function matches($path)
    {
        return $this->pattern->match($path) !== false;
    }

    /**
     * Calls the handler for the given method and URL path.
     * Returns whatever the handler returns.
     *
     * @param string $method
     * @param string $path
     * @return mixed
     */
    function exec($method, $path)
    {
        $args = $this->pattern->match($path);
        return call_user_func_array($this->$method, $args);
    }
}

class pattern
{
    private $parts = [];

    function __construct($string)
    {
        $pat_parts = explode('/', trim($string, '/'));
        foreach ($pat_parts as $part) {
            $this->parts[] = new expr($part);
        }
    }

    function match($url)
    {
        $uri_parts = array_map('urldecode', explode('/', trim($url, '/')));
        if (count($uri_parts) != count($this->parts)) {
            return false;
        }

        $args = [];
        foreach ($uri_parts as $i => $part) {
            if (!$this->parts[$i]->match($part, $m)) {
                return false;
            }
            $args = array_merge($args, array_slice($m, 1));
        }
        return $args;
    }
}

class expr
{
    private $s;
    private $toks = [];

    function __construct($s)
    {
        $this->s = $s;
        while (strlen($this->s) > 0) {
            $this->read();
        }
    }

    function match($s, &$m)
    {
        $p = $this->toPCRE();
        return preg_match($p, $s, $m);
    }

    function toPCRE()
    {
        $s = '';
        foreach ($this->toks as $tok) {
            if ($tok[0] == 'lit') {
                $s .= preg_quote($tok[1]);
            } else {
                $s .= "($tok[1])";
            }
        }

        $delims = ['/', '@'];

        foreach ($delims as $delim) {
            if (strpos($s, $delim) === false) {
                return $delim . '^' . $s . '$' . $delim;
            }
        }

        throw new Exception("Couldn't find suitable delimiter for regular expression: $s");
    }

    private function read()
    {
        if ($this->s[0] == '{') {
            $p = strpos($this->s, '}');
            if ($p) {
                $tok = ['pat', substr($this->s, 1, $p - 1)];
                $this->s = substr($this->s, $p + 1);
                $this->toks[] = $tok;
                return;
            }
        }

        $p = strpos($this->s, '{');
        if (!$p) {
            $p = strlen($this->s);
        }
        $tok = ['lit', substr($this->s, 0, $p)];
        $this->s = substr($this->s, $p);
        $this->toks[] = $tok;
    }
}
