<?php

namespace Emsifa\Stuble\Commands;

use Rakit\Console\Command;

class ListCommand extends StubleCommand
{

    protected $signature = "
        ls
        {keyword?}
        {--G|global::Get stubs files from STUBS_PATH only.}
        {--L|local::Get stubs files from current path only.}
        {--f|flatten::Flatten results (not grouped by directory).}
    ";

    protected $description = "Show list available stubs.";

    public function handle($keyword = null)
    {
        $files = $this->getFiles();

        if ($keyword) {
            $files = $this->filterFiles($files, $keyword);
        }

        $global = $this->option('global');
        $local = $this->option('local');
        $all = (!$global && !$local) || ($global && $local);

        if (empty($files)) {
            $suffix = $keyword ? " with keyword '{$keyword}'" : "";
            if ($all) {
                $this->error("No stubs files found{$suffix}.");
            } elseif ($local) {
                $this->error("No stubs files found in this directory{$suffix}.");
            } elseif ($global) {
                $this->error("No stubs files found in your STUBS_PATH{$suffix}.");
            }
            exit;
        }

        $ungroupeds = [];
        $groupeds = [];
        foreach ($files as $file) {
            $dir = dirname($file['path']);

            if ($dir == '.') {
                $ungroupeds[] = $file;
            } else {
                if (!isset($groupeds[$dir])) {
                    $groupeds[$dir] = [];
                }
                $groupeds[$dir][] = $file;
            }
        }

        $flatten = $this->option('flatten');
        $count = count($files);
        $n = 0;
        $d = strlen((string) $count);

        if ($ungroupeds) {
            print(PHP_EOL);
            foreach ($ungroupeds as $file) {
                $num = str_pad(++$n, $d, ' ', STR_PAD_LEFT);
                $this->showFile($num, $file);
            }
        }

        foreach ($groupeds as $dir => $files) {
            if (!$flatten) {
                print(PHP_EOL);
                $this->writeln(str_repeat(' ', $d)."  {$dir}", "green");
            }
            foreach ($files as $file) {
                $num = str_pad(++$n, $d, ' ', STR_PAD_LEFT);
                $this->showFile($num, $file);
            }
        }
    }

    protected function showFile ($num, $file)
    {
        $this->write($num.".", "brown");
        $this->write(" [{$file['type']}]", "blue");
        $this->writeln(" {$file['path']}");
    }

    public function getFiles()
    {
        $globalFiles = [];
        $localFiles = [];
        $global = $this->option('global');
        $local = $this->option('local');
        $all = (!$global && !$local) || ($global && $local);

        $envPath = $this->getEnvPath();
        $workingPath = $this->getWorkingPath();

        if ($global && !$envPath) {
            throw new \Exception("Cannot get files from global path. You must set STUBS_PATH in your environment variable.");
        }

        if (($global || $all) && $envPath) {
            $globalFiles = $this->getStubsFilesFromDirectory($envPath);
        }

        if ($local || $all) {
            $localFiles = $this->getStubsFilesFromDirectory($workingPath.'/stubs');
        }

        $files = [];
        foreach ($localFiles as $file) {
            $files[$file] = [
                'type' => 'L',
                'path' => $file
            ];
        }
        foreach ($globalFiles as $file) {
            if (isset($files[$file])) continue;
            $files[$file] = [
                'type' => 'G',
                'path' => $file
            ];
        }

        return array_values($files);
    }

    protected function filterFiles(array $files, string $keyword)
    {
        return array_filter($files, function ($file) use ($keyword) {
            return is_numeric(strpos($file['path'], $keyword));
        });
    }

    protected function getStubsFilesFromDirectory(string $dir, $baseDir = null)
    {
        if (!$baseDir) {
            $baseDir = $dir;
        }

        if (!is_dir($dir)) {
            return [];
        }

        if (!is_readable($dir)) {
            $this->writeln("[warning] Cannot read directory '{$dir}'", "yellow");
            return [];
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