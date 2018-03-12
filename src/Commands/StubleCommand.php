<?php

namespace Emsifa\Stuble\Commands;

use Emsifa\Stuble\Stuble;
use Rakit\Console\Command;

abstract class StubleCommand extends Command
{

    protected function findStubsFiles(string $stub)
    {
        return $this->findStubsFilesFromWorkingPath($stub) ?: $this->findStubsFilesFromEnvPath($stub);
    }

    protected function findStubsFilesFromWorkingPath(string $stub)
    {
        return $this->findStubsFilesFromDirectory($this->getWorkingPath().'/stubs', $stub);
    }

    protected function findStubsFilesFromEnvPath(string $stub)
    {
        $envPath = $this->getEnvPath();

        if (!$envPath) {
            return [];
        }

        return $this->findStubsFilesFromDirectory($envPath, $stub);
    }

    protected function findStubsFilesFromDirectory(string $dir, string $stub)
    {
        $dirPath = "{$dir}/{$stub}";
        $filePath = "{$dir}/{$stub}.stub";
        $wildcardPath = "{$dir}/{$stub}.stub";
        $files = [];

        if (is_numeric(strpos($stub, '*'))) {
            return glob($wildcardPath);
        } elseif (is_dir($dirPath)) {
            $files = glob($dirPath.'/*.stub');
        } elseif(is_file($filePath)) {
            $files = [$filePath];
        }

        return $files;
    }

    protected function getWorkingPath()
    {
        return realpath('.');
    }

    protected function getEnvPath()
    {
        return getenv('STUBS_PATH');
    }

    protected function includesStubleInits(Stuble $stuble, string $file)
    {
        if (strpos($file, $this->getWorkingPath()) === 0) {
            $this->includeStubleInitsFromDirectory($stuble, $file, $this->getWorkingPath().'/stubs');
        } elseif (strpos($file, $this->getEnvPath()) === 0) {
            $this->includeStubleInitsFromDirectory($stuble, $file, $this->getEnvPath());
        }
    }

    protected function includeStubleInitsFromDirectory(Stuble $stuble, string $file, string $dir)
    {
        $paths = explode("/", str_replace($dir.'/', "", dirname($file)));
        $path = "";

        while (!empty($paths)) {
            $initFile = $path ? "{$dir}{$path}/stuble-init.php" : "{$dir}/stuble-init.php";
            if (file_exists($initFile)) {
                $this->includeStubleInitFile($initFile, $stuble);
            }
            $path .= "/" . array_shift($paths);
        }
    }

    protected function includeStubleInitFile(string $initFile, Stuble $stuble)
    {
        require($initFile);
    }

}