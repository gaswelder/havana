<?php

use PHPUnit\Framework\TestCase;
use Appget\internal\router;

class routerTest extends TestCase
{
    function testMethods()
    {
        $r = new router();
        $get = function () { };
        $post = function () { };
        $options = function () { };
        $r->add('/', 'get', $get);
        $r->add('/', 'post', $post);
        $r->add('/', 'options', $options);

        [$match] = $r->find('/');
        $this->assertEquals($get, $match['get']);
        $this->assertEquals($post, $match['post']);
        $this->assertEquals($options, $match['options']);
    }
}
