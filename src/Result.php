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
        [$content, $options] = $this->splitOptionsFromContent($content);

        $options = $options ? $this->parseOptions($options) : [];

        return [$content, $options];
    }

    protected function splitOptionsFromContent(string $content)
    {
        $lines = explode("\n", $content);
        if (isset($lines[0]) && trim($lines[0]) !== "===") {
            return [$content, ""];
        }

        $optionsLines = [];
        $wrapper = 0;
        while ($line = array_shift($lines)) {
            if (trim($line) === "===") {
                $wrapper++;
            } else {
                $optionsLines[] = $line;
            }

            if ($wrapper === 2) {
                return [implode("\n", $lines), implode("\n", $optionsLines)];
            }
        }

        return [$content, ""];
    }

    protected function parseOptions(string $options)
    {
        try {
            return Toml::parse($options);
        } catch(\Exception $e) {
            return Yaml::parse($options);
        }
    }

}