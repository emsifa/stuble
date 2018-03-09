<?php

namespace Emsifa\Stuble\Commands;

use Rakit\Console\Command;

class ListCommand extends StubleCommand
{

    protected $signature = "
        ls
        {keyword?}
        {--G|global::Get stubs files from STUBS_PATH only.}
        {--H|here::Get stubs files from current path only.}
    ";

    protected $description = "Show list available stubs.";

    public function handle($keyword = null)
    {
        $files = $this->getFiles();

        if ($keyword) {
            $files = $this->filterFiles($files, $keyword);
        }

        $global = $this->option('global');
        $here = $this->option('here');
        $all = (!$global && !$here) || ($global && $here);

        if (empty($files)) {
            $suffix = $keyword ? " with keyword '{$keyword}'" : "";
            if ($all) {
                $this->error("No stubs files found{$suffix}.");
            } elseif ($here) {
                $this->error("No stubs files found in this directory{$suffix}.");
            } elseif ($global) {
                $this->error("No stubs files found in your STUBS_PATH{$suffix}.");
            }
            exit;
        }

        $groupeds = [];
        foreach ($files as $file) {
            $dir = dirname($file);
            if (!isset($groupeds[$dir])) {
                $groupeds[$dir] = [];
            }
            $groupeds[$dir][] = $file;
        }

        foreach ($groupeds as $dir => $files) {
            print(PHP_EOL);
            $this->writeln("[{$dir}]", "green");
            foreach ($files as $file) {
                $this->writeln("- {$file}", "blue");
            }
        }
    }

    public function getFiles()
    {
        $files = [];
        $global = $this->option('global');
        $here = $this->option('here');
        $all = (!$global && !$here) || ($global && $here);

        $envPath = $this->getEnvPath();
        $workingPath = $this->getWorkingPath();

        if ($global && !$envPath) {
            throw new \Exception("Cannot get files from global path. You must set STUBS_PATH in your environment variable.");
        }

        if (($global || $all) && $envPath) {
            $files = array_merge($files, $this->getStubsFilesFromDirectory($envPath));
        }

        if ($here || $all) {
            $files = array_merge($files, $this->getStubsFilesFromDirectory($workingPath));
        }

        return $files;
    }

    protected function filterFiles(array $files, string $keyword)
    {
        return array_filter($files, function ($file) use ($keyword) {
            return is_numeric(strpos($file, $keyword));
        });
    }

    protected function getStubsFilesFromDirectory(string $dir, $baseDir = null)
    {
        if (!$baseDir) {
            $baseDir = $dir;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        $stubs = [];
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                $stubs = array_merge($stubs, $this->getStubsFilesFromDirectory($path, $baseDir));
            } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'stub') {
                $stubs[] = str_replace($baseDir.'/', '', $path);
            }
        }

        return $stubs;
    }

}