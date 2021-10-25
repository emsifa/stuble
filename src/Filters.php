<?php

namespace Emsifa\Stuble;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;

class Filters
{
    protected static ?Inflector $inflector = null;

    /**
     * Get or create new inflector instance
     *
     * @return Inflector
     */
    protected static function inflector(): Inflector
    {
        if (! static::$inflector) {
            static::$inflector = InflectorFactory::create()->build();
        }

        return static::$inflector;
    }

    /**
     * Replace filter
     * Used to replace part of given string into another string
     *
     * Example Usage (in .stub file):
     * {? parameter_name.replace("before", "after") ?}
     *
     * @param  string $str  Parameter value
     * @param  string $from String to replace
     * @param  string $to   Replacement string
     * @return string
     */
    public static function replace(string $str, string $from, string $to): string
    {
        return str_replace($from, $to, $str);
    }

    /**
     * Singular Filter
     * Used to transform plural form of a word into its singular form
     *
     * Example Usage (in .stub file):
     * {? parameter_name.singular ?}
     *
     * @param  string $str Parameter value
     * @return string
     */
    public static function singular(string $str): string
    {
        return static::inflector()->singularize($str);
    }

    /**
     * Plural Filter
     * Used to transform singular form of a word into its plural form
     *
     * Example Usage (in .stub file):
     * {? parameter_name.plural ?}
     *
     * @param  string $str Parameter value
     * @return string
     */
    public static function plural(string $str): string
    {
        return static::inflector()->pluralize($str);
    }

    /**
     * Kebab Filter
     * Used to transform string into kebab-case.
     * Eg: "foo bar" -> "foo-bar"
     *
     * Example Usage (in .stub file):
     * {? parameter_name.kebab ?}
     *
     * @param  string $str Parameter value
     * @return string
     */
    public static function kebab(string $str): string
    {
        return str_replace(' ', '-', static::words($str));
    }

    /**
     * Snake Filter
     * Used to transform string into snake_case.
     * Eg: "foo bar" -> "foo_bar"
     *
     * Example Usage (in .stub file):
     * {? parameter_name.snake ?}
     *
     * @param  string $str Parameter value
     * @return string
     */
    public static function snake(string $str): string
    {
        return str_replace(' ', '_', static::words($str));
    }

    /**
     * Camel Filter
     * Used to transform string into camelCase.
     * Eg: "foo bar" -> "fooBar"
     *
     * Example Usage (in .stub file):
     * {? parameter_name.camel ?}
     *
     * @param  string $str Parameter value
     * @return string
     */
    public static function camel(string $str): string
    {
        return lcfirst(static::studly($str));
    }

    /**
     * Pascal Filter
     * Used to transform string into PascalCase/StudlyCaps.
     * Eg: "foo bar" -> "FooBar"
     *
     * Example Usage (in .stub file):
     * {? parameter_name.pascal ?}
     *
     * @param  string $str Parameter value
     * @return string
     */
    public static function pascal(string $str): string
    {
        return static::studly($str);
    }

    /**
     * Studly Filter
     * Used to transform string into StudlyCaps (same as PascalCase).
     * Eg: "foo bar" -> "FooBar"
     *
     * Example Usage (in .stub file):
     * {? parameter_name.studly ?}
     *
     * @param  string $str Parameter value
     * @return string
     */
    public static function studly(string $str): string
    {
        $str = ucwords(str_replace(['-', '_'], ' ', $str));

        return str_replace(' ', '', $str);
    }

    /**
     * Title Filter
     * Used to transform string into title case (ucwords).
     * Eg: "foo bar" -> "Foo Bar"
     *
     * Example Usage (in .stub file):
     * {? parameter_name.title ?}
     *
     * @param  string $str Parameter value
     * @return string
     */
    public static function title(string $str): string
    {
        return ucwords(static::words($str));
    }

    /**
     * Words Filter
     * It transform capital letters into lowercase and add a space before it.
     * Eg: "PostCategory" -> "post category"
     *
     * Example Usage (in .stub file):
     * {? parameter_name.words ?}
     *
     * @param  string $str Parameter value
     * @return string
     */
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
