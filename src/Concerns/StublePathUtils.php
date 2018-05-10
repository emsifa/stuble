<?php

namespace Emsifa\Stuble\Concerns;

trait StublePathUtils
{

    public function hasPath(string $key): bool
    {
        return array_key_exists($key, $this->paths);
    }

    public function getPath(string $key): ? string
    {
        return $this->hasPath($key) ? $this->paths[$key]['path'] : null;
    }

    public function setPath(string $key, string $path, int $priority = 0): void
    {
        $this->paths[$key] = [
            'path' => $path,
            'priority' => $priority
        ];
    }

    public function getPaths(): array
    {
        return $this->paths;
    }

    public function getSortedPathNames()
    {
        $paths = $this->paths;
        uasort($paths, function ($a, $b) {
            return $a['priority'] > $b['priority'] ? -1 : 1;
        });

        return array_keys($paths);
    }

}