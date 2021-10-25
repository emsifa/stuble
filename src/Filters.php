<?php

namespace Emsifa\Stuble;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;

class Filters
{
    protected static ?Inflector $inflector = null;

    protected static function inflector(): Inflector
    {
        if (! static::$inflector) {
            static::$inflector = InflectorFactory::create()->build();
        }

        return static::$inflector;
    }

    public static function replace(string $str, $from, $to): string
    {
        return str_replace($from, $to, $str);
    }

    public static function singular(string $str): string
    {
        return static::inflector()->singularize($str);
    }

    public static function plural(string $str): string
    {
        return static::inflector()->pluralize($str);
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
