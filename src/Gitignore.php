<?php

namespace Emsifa\Stuble;

class Gitignore
{
    /**
     * Path to .gitignore file
     *
     * @var string
     */
    protected string $gitignoreFile;

    /**
     * Gitignore rule patterns
     *
     * @var array
     */
    protected array $patterns = [];

    /**
     * Constructor
     *
     * @param string $gitignoreFile Path to .gitignore file
     */
    public function __construct(string $gitignoreFile)
    {
        $this->gitignoreFile = $gitignoreFile;
        $this->patterns = $this->parseFile($gitignoreFile);
    }

    /**
     * Get base directory absolute path
     * based on .gitignore file directory
     *
     * @return string|false
     */
    public function getBaseDirectory(): string|false
    {
        return realpath(dirname($this->gitignoreFile));
    }

    /**
     * Get parsed .gitignore rule patterns
     *
     * @return array
     */
    public function getParsedPatterns(): array
    {
        return $this->patterns;
    }

    /**
     * Check if given filepath is ignored by .gitignore
     *
     * @param  string $filepath
     * @return bool
     */
    public function isIgnoring(string $filepath): bool
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

    /**
     * Parse gitignore patterns from given file
     *
     * @param  string $file  .gitignore file path
     * @return array
     */
    protected function parseFile(string $file): array
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

    /**
     * Parse pattern from given string $line
     *
     * @param  string $line  Gitignore line content
     * @return array
     */
    protected function parsePattern(string $line): array
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
