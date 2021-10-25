<?php

namespace Emsifa\Stuble;

class Stuble
{
    use Concerns\StublePathUtils;

    public const KEY_ENV_PATH = 'STUBLE_HOME';
    public const KEY_WORKING_PATH = '.';

    protected $paths = [];

    protected $defaultPath;

    public function __construct()
    {
        $this->registerDefaultPaths();
    }

    private function registerDefaultPaths(): void
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

    public function getStubsFilesFromPath(string $pathname): array
    {
        if (! $this->hasPath($pathname)) {
            throw new \UnexpectedValueException("Cannot get stubs files from path '{$pathname}'. Path '{$pathname}' is not available.");
        }

        $path = $this->getPath($pathname);
        $stubs = $this->getStubsFilesFromDirectory($path.'/stubs');

        return array_map(function ($filepath) use ($pathname, $path) {
            return $this->makeStubPathInfo($filepath, $pathname);
        }, $stubs);
    }

    /**
     * @return (mixed|string)[]
     *
     * @psalm-return array<mixed|string>
     */
    protected function getStubsFilesFromDirectory(string $dir, $baseDir = null): array
    {
        if (! $baseDir) {
            $baseDir = $dir;
        }

        if (! is_dir($dir) || ! is_readable($dir)) {
            return [];
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        $stubFiles = [];
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $stubFiles = array_merge($stubFiles, $this->getStubsFilesFromDirectory($path, $baseDir));
            } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'stub') {
                $stubFiles[] = str_replace($baseDir . '/', '', $path);
            }
        }

        return $stubFiles;
    }

    public function hasStub(string $stub): bool
    {
        $stubPath = $this->findStubPath($stub);

        return ! is_null($stubPath);
    }

    public function findStub(string $stub): ?Stub
    {
        $stubPath = $this->findStubPath($stub);

        if (! $stubPath) {
            return null;
        }

        $stuble = new Stub($stubPath);
        $stuble->filename = pathinfo($stubPath, PATHINFO_FILENAME);
        $initFiles = $this->getStubleInitFiles($stubPath);
        foreach ($initFiles as $initFile) {
            $this->includeStubleInitFile($initFile, $stuble);
        }

        return $stuble;
    }

    public function makeStub(string $stub): Stub
    {
        $stuble = $this->findStub($stub);
        if (! $stuble) {
            throw new \UnexpectedValueException("Cannot make stub '{$stub}'. Stub file '{$stub}' doesn't exists.");
        }

        return $stuble;
    }

    /**
     * @return false|string
     */
    public function findStubPath(string $stub): string|false
    {
        list($pathnames, $stub) = $this->parsePath($stub);
        foreach ($pathnames as $pathname) {
            $path = $this->getPath($pathname);
            if (! $path) {
                continue;
            }

            $stubPath = rtrim($path, '/') . '/' . ltrim($stub, '/');
            if (is_file($stubPath)) {
                return $stubPath;
            }
        }

        return false;
    }

    public function findStubsFiles(string $query, $subdirs = true): array
    {
        list($pathnames, $query) = $this->parsePath($query);

        $results = [];
        foreach ($pathnames as $pathname) {
            $path = $this->getPath($pathname).'/stubs';
            $files = $this->findStubsFilesFromDirectory($path, $query, $subdirs);
            $results = array_merge($results, array_map(function ($filepath) use ($pathname) {
                return $this->makeStubPathInfo($filepath, $pathname);
            }, $files));
        }

        return $results;
    }

    /**
     * @param true $subdirs
     *
     * @return (mixed|string)[]|false
     *
     * @psalm-return array<mixed|string>|false
     */
    protected function findStubsFilesFromDirectory(string $dir, string $query, bool $subdirs = true): array|false
    {
        $dirPath = "{$dir}/{$query}";
        $filePath = "{$dir}/{$query}.stub";
        $wildcardPath = "{$dir}/{$query}.stub";
        $files = [];

        if (is_numeric(strpos($query, '*'))) {
            return glob($wildcardPath);
        } elseif (is_dir($dirPath)) {
            $files = glob($dirPath . '/*.stub');
        } elseif (is_file($filePath)) {
            $files = [$filePath];
        }

        if ($subdirs && is_dir($dirPath)) {
            $subdirs = array_filter(array_diff(scandir($dirPath), ['.', '..']), function ($file) use ($dirPath) {
                return is_dir($dirPath.'/'.$file);
            });

            foreach ($subdirs as $subdir) {
                $files = array_merge($files, $this->findStubsFilesFromDirectory($dirPath, $subdir, true));
            }
        }

        return $files;
    }

    protected function parsePath(string $path): array
    {
        $exp = explode(':', $path, 2);

        if (count($exp) > 1) {
            return [explode('|', $exp[0]), $exp[1]];
        } else {
            return [$this->getSortedPathNames(), $path];
        }
    }

    /**
     * @return string[]
     *
     * @psalm-return list<string>
     */
    protected function getStubleInitFiles(string $file): array
    {
        $pathnames = $this->getSortedPathNames();
        $dir = null;
        foreach ($pathnames as $path) {
            $stubsPath = $this->getPath($path).'/stubs';
            if (strpos($file, $stubsPath) === 0) {
                $dir = $stubsPath;

                break;
            }
        }

        $files = [];
        $paths = explode("/", str_replace($dir.'/', "", dirname($file)));

        do {
            $path = $dir . '/' . implode("/", $paths);
            $files = array_merge($files, glob($path.'/stuble-init.php'));
        } while (array_pop($paths));

        return array_reverse($files);
    }

    protected function includeStubleInitFile(string $initFile, Stub $stuble): void
    {
        require($initFile);
    }

    /**
     * @return string[]
     *
     * @psalm-return array{path: string, source: string, source_path: string}
     */
    protected function makeStubPathInfo(string $filepath, string $pathname): array
    {
        $path = $this->getPath($pathname);

        return [
            'path' => $filepath,
            'source' => $pathname,
            'source_path' => str_replace($path, '', $filepath),
        ];
    }
}
