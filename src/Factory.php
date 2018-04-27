<?php

namespace Emsifa\Stuble;

class Factory
{
    use Concerns\FactoryPathUtils;

    const KEY_ENV_PATH = 'STUBLE_HOME';
    const KEY_WORKING_PATH = '.';

    protected $paths = [];

    protected $defaultPath;

    public function __construct()
    {
        $this->registerDefaultPaths();
    }

    private function registerDefaultPaths() : void
    {
        $this->setPath(static::KEY_WORKING_PATH, realpath('.'), 999);
        if ($envPath = getenv(static::KEY_ENV_PATH)) {
            $this->setPath(static::KEY_ENV_PATH, $envPath, 0);
        }
    }

    public function getStubsFiles(): array
    {
        $paths = $this->getSortedPathNames();
        $files = [];

        foreach ($paths as $path) {
            $files = array_merge($files, $this->getStubsFilesFromPath($path));
        }

        return $files;
    }
    public function getMergedStubsFiles(): array
    {
        $allFiles = $this->getStubsFiles();

        $files = [];
        foreach ($allFiles as $file) {
            if (isset($files[$file['source_path']])) {
                continue;
            }

            $files[$file['source_path']] = $file;
        }

        return array_values($files);
    }

    public function getStubsFilesFromPath(string $key): array
    {
        if (!$this->hasPath($key)) {
            throw new \UnexpectedValueException("Cannot get stubs files from path '{$key}'. Path '{$key}' is not available.");
        }

        $path = $this->getPath($key);
        $stubs = $this->getStubsFilesFromDirectory($path);

        return array_map(function ($filepath) use ($key, $path) {
            return [
                'path' => $filepath,
                'source' => $key,
                'source_path' => str_replace($path, '', $filepath)
            ];
        }, $stubs);
    }

    protected function getStubsFilesFromDirectory(string $dir, $baseDir = null)
    {
        if (!$baseDir) {
            $baseDir = $dir;
        }

        if (!is_dir($dir) || !is_readable($dir)) {
            return [];
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        $stubs = [];
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $stubs = array_merge($stubs, $this->getStubsFilesFromDirectory($path, $baseDir));
            } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'stub') {
                $stubs[] = str_replace($baseDir . '/', '', $path);
            }
        }

        return $stubs;
    }

    public function hasStub(string $stub): bool
    {
        $stubPath = $this->findStubPath($stub);
        return !is_null($stubPath);
    }

    public function findStub(string $stub): ?Stuble
    {
        $stubPath = $this->findStubPath($stub);

        if (!$stubPath) {
            return null;
        }

        return new Stuble($stubPath);
    }

    public function makeStub(string $stub): Stuble
    {
        $stuble = $this->findStub($stub);
        if (!$stuble) {
            throw new \UnexpectedValueException("Cannot make stub '{$stub}'. Stub file '{$stub}' doesn't exists.");
        }
        return $stuble;
    }

    public function findStubPath(string $stub): ?string
    {
        list($pathnames, $stub) = $this->parsePath($stub);
        foreach ($pathnames as $pathname) {
            $path = $this->getPath($pathname);
            if (!$path) {
                continue;
            }

            $stubPath = rtrim($path, '/') . '/' . ltrim($stub, '/');
            if (is_file($stubPath)) {
                return $stubPath;
            }
        }

        return false;
    }

    protected function parsePath(string $path): array
    {
        $exp = explode(':', $path, 2);

        if (count($exp) > 1) {
            return [[$exp[0]], $exp[1]];
        } else {
            return [$this->getSortedPathNames(), $path];
        }
    }

    protected function getStubleInitFiles(string $file)
    {
        if (strpos($file, $this->getWorkingPath()) === 0) {
            $dir = $this->getWorkingPath().'/stubs';
        } elseif (strpos($file, $this->getEnvPath()) === 0) {
            $dir = $this->getEnvPath();
        } else {
            throw new \UnexpectedValueException("Failed to get stuble init files from '{$file}'. Argument 1 must be a path from global path or current path.");
        }

        $files = [];
        $paths = explode("/", str_replace($dir.'/', "", dirname($file)));

        do {
            $path = $dir . '/' . implode("/", $paths);
            $files = array_merge($files, glob($path.'/stuble-init.php'));
        } while (array_pop($paths));

        return array_reverse($files);
    }

    protected function includeStubleInitFile(string $initFile, Stuble $stuble)
    {
        require($initFile);
    }

}