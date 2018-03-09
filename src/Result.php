<?php

namespace Emsifa\Stuble;

class Result
{

    protected $content;

    protected $params = [];

    public function __construct(string $content)
    {
        list($this->content, $this->params) = $this->parseContent($content);
    }

    public function getRawContent()
    {
        return $this->unparseContent($this->content, $this->params);
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getSavePath()
    {
        return $this->getParam('path') ?: '';
    }

    public function getParam(string $key)
    {
        return isset($this->params[$key]) ? $this->params[$key] : null;
    }

    public function __toString()
    {
        return $this->getContent();
    }

    protected function parseContent(string $content)
    {
        $lines = explode("\n", $content);

        $params = [];
        $regex = "/^## (?<key>\w+)\:(?<val>[^\n]+)/";

        while (count($lines)) {
            $line = array_shift($lines);
            $matched = preg_match($regex, $line, $match);
            if (!$matched) {
                array_unshift($lines, $line);
                break;
            }

            $params[$match['key']] = trim($match['val']);
        }

        return [implode("\n", $lines), $params];
    }

    protected function unparseContent(string $content, array $params)
    {
        $paramsContent = [];
        foreach ($params as $key => $val) {
            $paramsContent[] = "## {$key}: {$val}";
        }

        return implode("\n", $paramsContent) . "\n" . $content;
    }

}