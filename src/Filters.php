<?php

namespace Emsifa\Stuble;

use Doctrine\Common\Inflector\Inflector;

class Filters
{
    public static function replace(string $str, $from, $to): string
    {
        return str_replace($from, $to, $str);
    }

    public static function singular(string $str): string
    {
        return Inflector::singularize($str);
    }

    public static function plural(string $str): string
    {
        return Inflector::pluralize($str);
    }

    public static function kebab(string $str): string
    {
        return str_replace(' ', '-', static::words($str));
    }

    public static function snake(string $str): string
    {
        return str_replace(' ', '_', static::words($str));
    }

    public static function camel(string $str): string
    {
        return lcfirst(static::studly($str));
    }

    public static function pascal(string $str): string
    {
        return static::studly($str);
    }

    public static function studly(string $str): string
    {
        $str = ucwords(str_replace(['-', '_'], ' ', $str));
        return str_replace(' ', '', $str);
    }

    public static function title(string $str): string
    {
        return ucwords(static::words($str));
    }

    public static function words(string $str): string
    {
        $delimiter = ' ';
        if (! ctype_lower($str)) {
            $str = preg_replace('/\s+/u', '', ucwords($str));
            $str = strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1'.$delimiter, $str));
        }
        return $str;
    }
}
