<?php

namespace Emsifa\Stuble;

use Closure;

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
            'lower'     => 'strtolower',
            'upper'     => 'strtoupper',
            'ucfirst'   => 'ucfirst',
            'ucwords'   => 'ucwords',
            'kebab'     => [Filters::class, 'kebab'],
            'snake'     => [Filters::class, 'snake'],
            'camel'     => [Filters::class, 'camel'],
            'pascal'    => [Filters::class, 'pascal'],
            'studly'    => [Filters::class, 'studly'],
            'title'     => [Filters::class, 'title'],
            'words'     => [Filters::class, 'words'],
            'plural'    => [Filters::class, 'plural'],
            'singular'  => [Filters::class, 'singular'],
            'replace'   => [Filters::class, 'replace'],
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
        if ($filter instanceof Closure) {
            $filter = $filter->bindTo($this);
        }

        $this->filters[$key] = $filter;
    }

    public function hasFilter(string $key)
    {
        return isset($this->filters[$key]);
    }

    public function applyFilter(string $key, string $value, array $args)
    {
        if (!isset($this->filters[$key])) {
            throw new \RuntimeException("Filter '{$key}' is not defined.");
        }

        return call_user_func_array($this->filters[$key], array_merge([$value], $args));
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
        $this->setParamsValues($values);

        $params = $this->getParams();
        $paramsValues = $this->getParamsValues();

        usort($params, function ($a, $b) {
            return strlen($a['code']) < strlen($b['code']);
        });

        $stub = $this->stub;
        foreach ($params as $param) {
            if ($param['is_arg']) continue;

            $value = $param['value'];
            foreach ($param['filters'] as $filter) {
                $value = $this->applyFilter($filter['key'], $value, $this->resolveFilterArgs($filter['args'], $paramsValues));
            }

            $stub = str_replace($param['code'], $value, $stub);
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
        $params = Parser::parse($stub);

        $keys = array_unique(array_map(function ($param) {
            return $param['key'];
        }, $params));

        foreach ($params as $i => $param) {
            $params[$i]['is_arg'] = false;
            foreach ($param['filters'] as $filter) {
                foreach ($filter['args'] as $arg) {
                    if ($arg['type'] == Parser::PARAM_VAR && !in_array($arg['value'], $keys)) {
                        $params[] = [
                            'is_arg' => true,
                            'key' => $arg['value'],
                            'value' => '',
                            'code' => null,
                            'filters' => []
                        ];
                    }
                }
            }
        }

        return $params;
    }

    protected function resolveFilterArgs(array $args, array $paramsValues)
    {
        $results = [];
        foreach ($args as $arg) {
            if ($arg['type'] == Parser::PARAM_STR) {
                $results[] = (string) $arg['value'];
            } elseif ($arg['type'] == Parser::PARAM_NUM) {
                $results[] = is_numeric(strpos($arg['value'], '.')) ? (float) $arg['value'] : (int) $arg['value'];
            } elseif ($arg['type'] == Parser::PARAM_VAR) {
                $results[] = isset($paramsValues[$arg['value']]) ? $paramsValues[$arg['value']] : null;
            }
        }
        return $results;
    }

}