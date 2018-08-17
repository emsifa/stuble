<?php

namespace Emsifa\Stuble;

use Symfony\Component\Yaml\Yaml;
use Yosymfony\Toml\Toml;
use Yosymfony\Toml\TomlBuilder;

class Result
{

    protected $rawContent;
    protected $content;

    protected $options = [];

    public function __construct(string $content)
    {
        $this->rawContent = $content;
        list($this->content, $this->options) = $this->parseContent($content);
    }

    public function getRawContent()
    {
        return $this->rawContent;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getSavePath()
    {
        return $this->getOption('path') ?: '';
    }

    public function getAppendOption()
    {
        $append = $this->getOption('append');
        if (empty($append)) {
            return null;
        }

        $defaults = [
            'after' => null,
            'before' => null,
            'line' => null,
        ];

        if (is_string($append)) {
            return array_merge($defaults, [
                'file' => $append,
            ]);
        } else {
            return array_merge($defaults, $append);
        }
    }

    public function getOption(string $key)
    {
        return isset($this->options[$key]) ? $this->options[$key] : null;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function __toString()
    {
        return $this->getContent();
    }

    protected function parseContent(string $content)
    {
        $options = [];
        $regexOptions = "/^===\n\r?(?<options>(.*\n\r?)*)===\n\r?/";
        if (preg_match($regexOptions, $content, $match)) {
            $content = preg_replace($regexOptions, "", $content);
            $options = $match['options'];
            try {
                $options = Toml::parse($options);
            } catch (\Exception $e) {
                $options = Yaml::parse($options);
            }
        }

        return [$content, $options];
    }

}