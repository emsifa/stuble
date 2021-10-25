<?php

namespace Emsifa\Stuble\Concerns;

use Closure;
use Emsifa\Stuble\Stub;

trait HelperUtils
{
    protected static $hasInitializeGlobalHelpers = false;
    protected static $globalHelpers = [];
    protected $helpers = [];

    public static function initializeGlobalHelpers()
    {
        if (static::$hasInitializeGlobalHelpers) {
            return;
        }

        $putHelper = function (string $file, ...$args) {
            $dir = dirname($this->getFilepath());
            $params = $this->getParamsValues();
            $filepath = $dir.'/'.ltrim($file, '/');

            if (pathinfo($filepath, PATHINFO_EXTENSION) != 'stub') {
                $filepath .= '.stub';
            }

            $stuble = new Stub($filepath);

            return $stuble->render($params);
        };

        $baseHelpers = [
            'date' => 'date',
            'put' => $putHelper,
        ];

        foreach ($baseHelpers as $key => $helper) {
            if (! static::hasGlobalHelper($key)) {
                static::globalHelper($key, $helper);
            }
        }

        static::$hasInitializeGlobalHelpers = true;
    }

    public static function globalHelper(string $key, callable $helper)
    {
        static::$globalHelpers[$key] = $helper;
    }

    public static function hasGlobalHelper(string $key)
    {
        return isset(static::$globalHelpers[$key]);
    }

    public function helper(string $key, callable $helper)
    {
        $this->helpers[$key] = $helper;
    }

    public function hasHelper(string $key)
    {
        return isset($this->helpers[$key]);
    }

    public function applyHelper(string $key, array $args)
    {
        if (! isset($this->helpers[$key])) {
            throw new \RuntimeException("Helper '{$key}' is not defined.");
        }

        $helper = $this->helpers[$key];
        if ($helper instanceof Closure) {
            $helper = $helper->bindTo($this);
        }

        return call_user_func_array($helper, $args);
    }
}
