<?php

namespace Emsifa\Stuble;

class Stuble
{

    protected static $hasInitializeGlobalFilters = false;
    protected static $globalFilters = [];

    protected $stub;
    protected $params;
    protected $filters = [];

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
        ];

        foreach ($baseFilters as $key => $filter) {
            if (!static::hasGlobalFilter($key)) {
                static::globalFilter($key, $filter);
            }
        }

        static::$hasInitializeGlobalFilters = true;
    }

    public static function globalFilter(string $key, callable $filter)
    {
        static::$globalFilters[$key] = $filter;
    }

    public static function hasGlobalFilter(string $key)
    {
        return isset(static::$globalFilters[$key]);
    }

    public function __construct(string $stub)
    {
        static::initializeGlobalFilters();

        $this->stub = $stub;
        $this->params = $this->parseParams($stub);
        $this->filters = static::$globalFilters;
    }

    public function filter(string $key, callable $filter)
    {
        $this->filters[$key] = $filter;
    }

    public function hasFilter(string $key)
    {
        return isset($this->filters[$key]);
    }

    public function applyFilter(string $key, string $value)
    {
        if (!isset($this->filters[$key])) {
            throw new \RuntimeException("Filter '{$key}' is not defined.");
        }

        return call_user_func($this->filters[$key], $value);
    }

    public function getParams() : array
    {
        return $this->params;
    }

    public function getParamsValues()
    {
        $values = [];

        foreach ($this->params as $param) {
            if (!isset($values[$param['key']])) {
                $values[$param['key']] = $param['value'];
            }

            if (empty($values[$param['key']]) && $param['value']) {
                $values[$param['key']] = $param['value'];
            }
        }

        return $values;
    }

    public function setParamsValues(array $values)
    {
        $this->params = $this->fillValues($this->params, $values);
    }

    public function render(array $values = []) : Result
    {
        $params = $this->fillValues($this->params, $values);
        usort($params, function ($a, $b) {
            return strlen($a['match']) < strlen($b['match']);
        });

        $stub = $this->stub;
        foreach ($params as $param) {
            $value = $param['value'];
            foreach ($param['filters'] as $filter) {
                $value = static::applyFilter($filter, $value);
            }
            $stub = str_replace($param['match'], $value, $stub);
        }

        return new Result($stub);
    }

    protected function fillValues(array $params, array $values)
    {
        foreach ($params as $i => $param) {
            if (isset($values[$param['key']])) {
                $params[$i]['value'] = $values[$param['key']];
            }
        }
        return $params;
    }

    protected function parseParams(string $stub) : array
    {
        $regex = "\{\?(?<key>\w+)(\[(?<val>[^\]]+)\])?(::(?<filters>\w+(\.\w+)*))?\?\}";
        preg_match_all("/{$regex}/", $stub, $matchs);
        $params = [];

        foreach ($matchs['key'] as $i => $key) {
            $params[] = [
                'key' => $key,
                'match' => $matchs[0][$i],
                'filters' => $this->parseFilters($matchs['filters'][$i]),
                'value' => $matchs['val'][$i]
            ];
        }

        return $params;
    }

    protected function parseFilters(string $str)
    {
        if (!$str) {
            return [];
        }

        return explode('.', $str);
    }

}