<?php

namespace havana_internal;

class router
{
    private $routes = [];

    /**
     * Adds a route.
     * 
     * @param string $pattern
     * @param string $method
     * @param callable $func
     */
    function add($pattern, $method, $func)
    {
        if (!isset($this->routes[$pattern])) {
            $this->routes[$pattern] = new route($pattern);
        }
        $this->routes[$pattern]->$method = $func;
    }

    function find($method, $url)
    {
        // Find the route that matches the url.
        $matches = array_filter($this->routes, function (route $route) use ($url) {
            return $route->matches($url['path']);
        });
        // Order the routes by specificity.
        usort($matches, function (route $a, route $b) {
            $s1 = $a->specificity();
            $s2 = $b->specificity();
            if ($s2 > $s1) return 1;
            if ($s2 < $s1) return -1;
            return 0;
        });

        // Keep the ones that allow the requested method.
        $routes = array_filter($matches, function (route $route) use ($method) {
            return $route->$method != null;
        });

        // Just take the first one that's left.
        $route = reset($routes);

        return [$matches, $route];
    }
}
