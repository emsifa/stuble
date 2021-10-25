<?php

namespace Emsifa\Stuble\Concerns;

use Closure;
use Emsifa\Stuble\Filters;

trait FilterUtils
{
    protected static $hasInitializeGlobalFilters = false;
    protected static $globalFilters = [];
    protected $filters = [];

    /**
     * @return void
     */
    public static function initializeGlobalFilters()
    {
        if (static::$hasInitializeGlobalFilters) {
            return;
        }

        $baseFilters = [
            'lower' => 'strtolower',
            'upper' => 'strtoupper',
            'ucfirst' => 'ucfirst',
            'ucwords' => 'ucwords',
            'kebab' => [Filters::class, 'kebab'],
            'snake' => [Filters::class, 'snake'],
            'camel' => [Filters::class, 'camel'],
            'pascal' => [Filters::class, 'pascal'],
            'studly' => [Filters::class, 'studly'],
            'title' => [Filters::class, 'title'],
            'words' => [Filters::class, 'words'],
            'plural' => [Filters::class, 'plural'],
            'singular' => [Filters::class, 'singular'],
            'replace' => [Filters::class, 'replace'],
        ];

        foreach ($baseFilters as $key => $filter) {
            if (! static::hasGlobalFilter($key)) {
                static::globalFilter($key, $filter);
            }
        }

        static::$hasInitializeGlobalFilters = true;
    }

    public static function globalFilter(string $key, callable $filter): void
    {
        static::$globalFilters[$key] = $filter;
    }

    public static function hasGlobalFilter(string $key): bool
    {
        return isset(static::$globalFilters[$key]);
    }

    public function filter(string $key, callable $filter): void
    {
        $this->filters[$key] = $filter;
    }

    public function hasFilter(string $key): bool
    {
        return isset($this->filters[$key]);
    }

    public function applyFilter(string $key, string $value, array $args)
    {
        if (! isset($this->filters[$key])) {
            throw new \RuntimeException("Filter '{$key}' is not defined.");
        }

        $filter = $this->filters[$key];
        if ($filter instanceof Closure) {
            $filter = $filter->bindTo($this);
        }

        return call_user_func_array($filter, array_merge([$value], $args));
    }
}
