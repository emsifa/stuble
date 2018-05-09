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

    public function getParams()
    {
        return $this->params;
    }

    public function __toString()
    {
        return $this->getContent();
    }

    protected function parseContent(string $content)
    {
        $params = [];
        $regexParams = "/^===\n(.*\n)*===/";
        if (preg_match($regexParams, $content)) {
            $content = preg_replace($regexParams, "$1", $content);
            $lines = explode("\n", $content);
            while (count($lines)) {
                $line = array_shift($lines);
                $matched = preg_match("/(?<key>\w+): (?<val>[^\n]+)/", $line, $match);
                if (!$matched) {
                    array_unshift($lines, $line);
                    break;
                }

                $params[$match['key']] = trim($match['val']);
            }
            $content = implode("\n", $lines);
        }

        return [$content, $params];
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