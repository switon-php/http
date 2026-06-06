<?php

declare(strict_types=1);

use Switon\Core\App;
use Switon\Http\RequestInterface;
use Switon\Http\UrlGeneratorInterface;

if (!function_exists('attr_nv')) {
    function attr_nv(string $name, string $default = ''): string
    {
        $request = App::get(RequestInterface::class);
        return sprintf('name="%s" value="%s"', $name, e($request->get($name, $default)));
    }
}

if (!function_exists('attr_inv')) {
    function attr_inv(string $name, string $default = ''): string
    {
        if ($pos = strpos($name, '[')) {
            $id = substr($name, $pos + 1, -1);
        } else {
            $id = $name;
        }

        $request = App::get(RequestInterface::class);

        return sprintf('id="%s" name="%s" value="%s"', $id, $name, e($request->get($name, $default)));
    }
}

if (!function_exists('action')) {
    function action(string|array $args = [], bool|string $scheme = false): string
    {
        return App::get(UrlGeneratorInterface::class)->action($args, $scheme);
    }
}

if (!function_exists('url')) {
    function url(string|array $args = [], bool|string $scheme = false): string
    {
        return App::get(UrlGeneratorInterface::class)->url($args, $scheme);
    }
}
