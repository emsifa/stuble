<?php

namespace Emsifa\Stuble;

class Parser
{
    public const TYPE_PARAM = 100;
    public const TYPE_HELPER = 101;

    public const CONTENT = 0;
    public const OPEN_TAG = 1;
    public const CLOSING_TAG = 2;
    public const CLOSE_TAG = 3;
    public const PARAM_KEY = 4;
    public const OPEN_DEFAULT_VALUE = 5;
    public const DEFAULT_VALUE = 6;
    public const CLOSE_DEFAULT_VALUE = 7;
    public const FILTER_KEY = 8;
    public const OPEN_FILTER_PARAMS = 9;
    public const PARAM_VALUE = 10;
    public const PARAM_STR = 11;
    public const CLOSING_PARAM = 12;
    public const PARAM_VAR = 13;
    public const PARAM_NUM = 14;
    public const CLOSE_FILTER_PARAMS = 15;

    /**
     * @return ((array[]|mixed)[][]|mixed|string)[][]
     *
     * @psalm-return list<array{key: ''|mixed, type: ''|mixed, value: ''|mixed, filters: array<int, array{key: mixed, args: list<array{type: mixed, value: mixed}>}>, code: mixed, args: list<array{type: mixed, value: mixed}>}>
     */
    public static function parse(string $str): array
    {
        $codes = static::lex($str);
        $results = [];
        foreach ($codes as $code) {
            $parsed = [
                'key' => '',
                'type' => '',
                'value' => '',
                'filters' => [],
                'code' => $code['code'],
                'args' => [],
            ];

            foreach ($code['tokens'] as $data) {
                switch ($data[0]) {
                    case static::PARAM_KEY:
                        $parsed['type'] = $data[1];
                        $parsed['key'] = $data[2];

                        break;

                    case static::DEFAULT_VALUE:
                        $parsed['value'] = $data[1];

                        break;

                    case static::FILTER_KEY:
                        $parsed['filters'][] = [
                            'key' => $data[1],
                            'args' => [],
                        ];

                        break;

                    case static::PARAM_VALUE:
                        if (! count($parsed['filters'])) {
                            $parsed['args'][] = ['type' => $data[1], 'value' => $data[2]];
                        } else {
                            $parsed['filters'][count($parsed['filters']) - 1]['args'][] = ['type' => $data[1], 'value' => $data[2]];
                        }

                        break;
                }
            }

            $results[] = $parsed;
        }

        return $results;
    }

    public static function lex(string $str)
    {
        $tags = [];
        $n = 0;
        $tok = "";
        $states = [static::CONTENT];
        $chars = str_split($str);
        $regexParam = "/^[a-z][a-z0-9_-]*$/i";
        $open = false;
        $code = "";

        $reset = function () use (&$tags, &$n, &$tok, &$states): void {
            $tok = "";
            $tags[$n] = [
                'tokens' => [],
            ];
            $states = [static::CONTENT];
        };

        $close = function () use (&$tags, &$n, &$reset, &$open, &$code): void {
            $tags[$n]['tokens'][] = [static::CLOSE_TAG];
            $tags[$n]['code'] = $code;
            $open = false;
            $code = "";
            $n += 1;
            $reset();
        };

        foreach ($chars as $i => $char) {
            $prev = $i > 0 ? $chars[$i - 1] : null;
            $state = $states[count($states) - 1];
            if ($open) {
                
            }

            switch ($state) {

                case static::CONTENT:
                    if (static::endsWith($tok.$char, "{?")) {
                        $states[] = static::OPEN_TAG;
                        $tags[$n]['tokens'][] = [static::OPEN_TAG];
                        $tok = "";
                        $code = "{?";
                        $open = true;
                    } else {
                        $tok .= $char;
                    }

                    break;

                case static::OPEN_TAG:
                    if (static::isWhitespace($char)) {
                        $tok = "";
                    } elseif (static::strIs($char, "/[a-z]/i")) {
                        $states[] = static::PARAM_KEY;
                        $tok = $char;
                    } else {
                        $reset();
                    }

                    break;

                case static::PARAM_KEY:
                    if (static::strIs($tok.$char, $regexParam)) {
                        $tok .= $char;
                    } elseif ($char == "[") {
                        $tags[$n]['tokens'][] = [static::PARAM_KEY, static::TYPE_PARAM, $tok];
                        $states[] = static::OPEN_DEFAULT_VALUE;
                        $tok = "[";
                    } elseif ($char == "(") {
                        $tags[$n]['tokens'][] = [static::PARAM_KEY, static::TYPE_HELPER, $tok];
                        $states[] = static::OPEN_FILTER_PARAMS;
                        $tok = "";
                    } elseif (static::isWhitespace($char)) {
                        $tags[$n]['tokens'][] = [static::PARAM_KEY, static::TYPE_PARAM, $tok];
                        $states[] = static::CLOSING_TAG;
                    } elseif ($char == ".") {
                        $tags[$n]['tokens'][] = [static::PARAM_KEY, static::TYPE_PARAM, $tok];
                        $states[] = static::FILTER_KEY;
                        $tok = "";
                    } else {
                        $reset();
                    }

                    break;

                case static::OPEN_DEFAULT_VALUE:
                    if ($char == '"') {
                        $states[] = static::DEFAULT_VALUE;
                        $tok = "";
                    } else {
                        $reset();
                    }

                    break;

                case static::DEFAULT_VALUE:
                    if ($char != '"') {
                        $tok .= $char;
                    } elseif ($prev == "\\") {
                        $tok .= $char; // char is "
                    } else {
                        $tags[$n]['tokens'][] = [static::DEFAULT_VALUE, str_replace("\\\"", "\"", $tok)];
                        $states[] = static::CLOSE_DEFAULT_VALUE;
                        $tok = "";
                    }

                    break;

                case static::CLOSE_DEFAULT_VALUE:
                    if ($char == "]") {
                        $tok = "";
                    } elseif ($char == ".") {
                        $states[] = static::FILTER_KEY;
                        $tok = "";
                    } elseif (static::isWhitespace($char)) {
                        $states[] = static::CLOSING_TAG;
                        $tok = "";
                    } elseif ($char == "?") {
                        $states[] = static::CLOSING_TAG;
                        $tok = "?";
                    } else {
                        $reset();
                    }

                    break;

                case static::FILTER_KEY:
                    if (static::strIs($tok.$char, $regexParam)) {
                        $tok .= $char;
                    } elseif ($char == "(") {
                        $tags[$n]['tokens'][] = [static::FILTER_KEY, $tok];
                        $states[] = static::OPEN_FILTER_PARAMS;
                        $tok = "";
                    } elseif ($char == ".") {
                        $tags[$n]['tokens'][] = [static::FILTER_KEY, $tok];
                        $tok = "";
                    } elseif (static::isWhitespace($char)) {
                        $tags[$n]['tokens'][] = [static::FILTER_KEY, $tok];
                        $states[] = static::CLOSING_TAG;
                        $tok = "";
                    } elseif ($char == "?") {
                        $states[] = static::CLOSING_TAG;
                        $tok = "?";
                    } else {
                        $reset();
                    }

                    break;

                case static::OPEN_FILTER_PARAMS:
                    if (static::isWhitespace($char)) {
                        $tok = "";
                    } elseif ($char == '"') {
                        // $states[] = static::PARAM_VALUE;
                        $states[] = static::PARAM_STR;
                        $tok = '';
                    } elseif (is_numeric($char)) {
                        // $states[] = static::PARAM_VALUE;
                        $states[] = static::PARAM_NUM;
                        $tok = $char;
                    } elseif (static::strIs($char, "/[a-z]/i")) {
                        // $states[] = static::PARAM_VALUE;
                        $states[] = static::PARAM_VAR;
                        $tok = $char;
                    } elseif ($char == ")") {
                        $states[] = static::CLOSE_FILTER_PARAMS;
                        $tok = "";
                    } else {
                        $reset();
                    }

                    break;

                case static::PARAM_STR:
                    if ($char != '"') {
                        $tok .= $char;
                    } else {
                        if ($prev == "\\") {
                            $tok .= $char;
                        } else {
                            $tags[$n]['tokens'][] = [static::PARAM_VALUE, static::PARAM_STR, str_replace("\\\"", "\"", $tok)];
                            $states[] = static::CLOSING_PARAM;
                            $tok = "";
                        }
                    }

                    break;

                case static::PARAM_NUM:
                    if (is_numeric($tok.$char)) {
                        $tok .= $char;
                    } elseif (static::isWhitespace($char)) {
                        $tags[$n]['tokens'][] = [static::PARAM_VALUE, static::PARAM_NUM, $tok];
                        $states[] = static::CLOSING_PARAM;
                        $tok = "";
                    } elseif ($char == ")") {
                        $tags[$n]['tokens'][] = [static::PARAM_VALUE, static::PARAM_NUM, $tok];
                        $states[] = static::CLOSE_FILTER_PARAMS;
                        $tok = "";
                    } elseif ($char == ",") {
                        $tags[$n]['tokens'][] = [static::PARAM_VALUE, static::PARAM_NUM, $tok];
                        $states[] = static::OPEN_FILTER_PARAMS;
                        $tok = "";
                    } else {
                        $reset();
                    }

                    break;

                case static::PARAM_VAR:
                    if (static::strIs($tok.$char, $regexParam)) {
                        $tok .= $char;
                    } elseif (static::isWhitespace($char)) {
                        $tags[$n]['tokens'][] = [static::PARAM_VALUE, static::PARAM_VAR, $tok];
                        $states[] = static::CLOSING_PARAM;
                        $tok = "";
                    } elseif ($char == ")") {
                        $tags[$n]['tokens'][] = [static::PARAM_VALUE, static::PARAM_VAR, $tok];
                        $states[] = static::CLOSE_FILTER_PARAMS;
                        $tok = "";
                    } elseif ($char == ",") {
                        $tags[$n]['tokens'][] = [static::PARAM_VALUE, static::PARAM_VAR, $tok];
                        $states[] = static::OPEN_FILTER_PARAMS;
                        $tok = "";
                    } else {
                        $reset();
                    }

                    break;

                case static::CLOSING_PARAM:
                    if (static::isWhitespace($char)) {
                        $tok = "";
                    } elseif ($char == ",") {
                        $states[] = static::OPEN_FILTER_PARAMS;
                        $tok = "";
                    } elseif ($char == ")") {
                        $states[] = static::CLOSE_FILTER_PARAMS;
                        $tok = "";
                    } else {
                        $reset();
                    }

                    break;

                case static::CLOSE_FILTER_PARAMS:
                    if ($char == ".") {
                        $states[] = static::FILTER_KEY;
                        $tok = "";
                    } elseif (static::isWhitespace($char)) {
                        $states[] = static::CLOSING_TAG;
                    }

                    break;

                case static::CLOSING_TAG:
                    if (static::isWhitespace($char)) {
                        $tok = "";
                    } elseif ($char == "?") {
                        $tok = "?";
                    } elseif ($tok.$char == "?}") {
                        $close();
                    } else {
                        $reset();
                    }

                    break;
            }
        }

        array_pop($tags);

        return $tags;
    }

    protected static function strIs(string $str, string $rg): bool
    {
        return (bool) preg_match($rg, $str);
    }

    protected static function isWhitespace(string $str): bool
    {
        return (bool) static::strIs($str, "/\s+/");
    }

    protected static function strStart(string $str, string $start): bool
    {
        return strpos($str, $start) === 0;
    }

    protected static function endsWith(string $str, string $start): bool
    {
        return strpos($str, $start) === strlen($str) - strlen($start);
    }
}
