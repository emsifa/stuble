<?php

namespace Emsifa\Stuble;

class Gitignore
{
    protected $gitignoreFile;
    protected $patterns = [];

    public function __construct($gitignoreFile)
    {
        $this->gitignoreFile = $gitignoreFile;
        $this->patterns = $this->parseFile($gitignoreFile);
    }

    public function getBaseDirectory()
    {
        return realpath(dirname($this->gitignoreFile));
    }

    public function getParsedPatterns()
    {
        return $this->patterns;
    }

    public function isIgnoring($filepath)
    {
        foreach ($this->getParsedPatterns() as $pattern) {
            if ($pattern['regex']) {
                $match = (bool) preg_match($pattern['regex'], $filepath);
            } elseif ($pattern['is_dir']) {
                $match = strpos($filepath, $pattern['path']) === 0;
            } else {
                $match = $pattern['path'] == $filepath;
            }

            if ($match) {
                return ! $pattern['is_negate'];
            }
        }

        return false;
    }

    protected function parseFile($file)
    {
        $content = file_get_contents($file);
        $lines = explode("\n", $content);

        $patterns = [];

        foreach ($lines as $line) {
            $line = trim($line);
            $isEmpty = empty($line);
            $isComment = substr($line, 0, 1) == "#";

            if ($isEmpty || $isComment) {
                continue;
            }

            $line = explode("#", $line)[0];
            $pattern = $this->parsePattern($line);

            if ($pattern['is_negate']) {
                // Make negation as priority
                array_unshift($patterns, $pattern);
            } else {
                array_push($patterns, $pattern);
            }
        }

        return $patterns;
    }

    protected function parsePattern($line)
    {
        $dir = $this->getBaseDirectory();

        $isNegate = substr($line, 0, 1) == "!";
        $hasAsterisk = is_int(strpos($line, "*"));

        $path = $isNegate ? $dir.'/'.trim(substr($line, 1), '/') : $dir.'/'.trim($line, '/');

        $regex = $hasAsterisk ? "/".str_replace("\\*", "[^\/]+", preg_quote($path, "/"))."/" : null;

        return [
            'pattern' => $line,
            'path' => $path,
            'regex' => $regex,
            'is_negate' => $isNegate,
            'is_dir' => ! $hasAsterisk ? is_dir($path) : false,
        ];
    }
}
