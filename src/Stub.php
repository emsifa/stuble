<?php

namespace Emsifa\Stuble;

use Closure;

class Stub
{
    use Concerns\FilterUtils;
    use Concerns\HelperUtils;

    protected $stub;
    protected $params;

    public function __construct(string $filepath)
    {
        if (!file_exists($filepath)) {
            throw new \Exception("Stub file '{$filepath}' not found.");
        }

        static::initializeGlobalFilters();
        static::initializeGlobalHelpers();

        $this->filepath = realpath($filepath);
        $this->stub = file_get_contents($this->filepath);
        $this->params = $this->parseParams($this->stub);

        $this->filters = static::$globalFilters;
        $this->helpers = static::$globalHelpers;
    }

    public function getFilepath()
    {
        return $this->filepath;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getParamsValues($includeHelper = true)
    {
        $values = [];

        foreach ($this->params as $param) {
            if (!$includeHelper && $param['type'] == Parser::TYPE_HELPER) {
                continue;
            }

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

    public function render(array $values = []): Result
    {
        $this->setParamsValues($values);

        $params = $this->getParams();
        $paramsValues = $this->getParamsValues();

        usort($params, function ($a, $b) {
            return strlen($a['code']) < strlen($b['code']);
        });

        $stub = $this->stub;
        foreach ($params as $param) {
            if ($param['type'] == 'arg') {
                continue;
            }

            if ($param['type'] == Parser::TYPE_HELPER) {
                $value = $this->applyHelper($param['key'], $this->resolveArgs($param['args'], $paramsValues));
            } else {
                $value = $param['value'] ?: $paramsValues[$param['key']];
            }

            foreach ($param['filters'] as $filter) {
                $value = $this->applyFilter($filter['key'], $value, $this->resolveArgs($filter['args'], $paramsValues));
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

    protected function parseParams(string $stub): array
    {
        $params = Parser::parse($stub);

        $keys = array_unique(array_map(function ($param) {
            return $param['key'];
        }, $params));

        foreach ($params as $i => $param) {
            if (isset($param['args'])) {
                foreach ($param['args'] as $arg) {
                    if ($arg['type'] == Parser::PARAM_VAR && !in_array($arg['value'], $keys)) {
                        $params[] = [
                            'type' => 'arg',
                            'key' => $arg['value'],
                            'value' => '',
                            'code' => null,
                            'filters' => []
                        ];
                    }
                }
            }

            foreach ($param['filters'] as $filter) {
                foreach ($filter['args'] as $arg) {
                    if ($arg['type'] == Parser::PARAM_VAR && !in_array($arg['value'], $keys)) {
                        $params[] = [
                            'type' => 'arg',
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

    protected function resolveArgs(array $args, array $paramsValues)
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
