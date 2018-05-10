<?php

namespace Emsifa\Stuble\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Emsifa\Stuble\Stuble;

class Ls extends StubleCommand
{

    protected $name = 'ls';
    protected $description = 'Show list available stubs.';
    protected $help = '';

    protected $args = [
        'keyword' => [
            'type' => InputArgument::OPTIONAL,
            'description' => 'Search keyword.'
        ]
    ];

    protected $options = [
        'global' => [
            'alias' => 'G',
            'description' => 'Show global stubs only.'
        ],
        'local' => [
            'alias' => 'L',
            'description' => 'Show local stubs (from current directory) only.'
        ],
        'flatten' => [
            'alias' => 'f',
            'description' => 'Flatten results (not grouped by directory).'
        ]
    ];

    protected function handle()
    {
        $keyword = $this->argument('keyword');

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
                $this->error("No stubs files found in your \${STUBLE_HOME}/stubs{$suffix}.");
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
        $d = strlen((string)$count);

        if ($ungroupeds) {
            print(PHP_EOL);
            foreach ($ungroupeds as $file) {
                $num = str_pad(++$n, $d, ' ', STR_PAD_LEFT);
                $this->showFile($num, $file, $keyword);
            }
        }

        foreach ($groupeds as $dir => $files) {
            if (!$flatten) {
                print(PHP_EOL);
                $this->success(str_repeat(' ', $d) . "  {$dir}");
            }
            foreach ($files as $file) {
                $num = str_pad(++$n, $d, ' ', STR_PAD_LEFT);
                $this->showFile($num, $file, $keyword);
            }
        }
    }

    protected function showFile($num, $file, $keyword)
    {
        $filetype = $file['source'] == Stuble::KEY_WORKING_PATH ? 'L' : 'G';
        $filepath = $file['source_path'];

        if ($keyword) {
            $filepath = preg_replace("/{$keyword}/i", "<fg=yellow;options=bold>$0</>", $filepath);
        }

        $this->writeln("<fg=magenta>{$num}.</> <fg=blue>[{$filetype}]</> {$filepath}");
    }

    public function getFiles()
    {
        $globalFiles = [];
        $localFiles = [];
        $global = $this->option('global');
        $local = $this->option('local');

        if ($global && !$this->stuble->hasPath(Stuble::KEY_ENV_PATH)) {
            throw new \Exception("Cannot get files from global path. You must set STUBLE_HOME in your environment variable.");
        }

        if ($global) {
            $files = $this->stuble->getStubsFilesFromPath(Stuble::KEY_ENV_PATH);
        } elseif ($local) {
            $files = $this->stuble->getStubsFilesFromPath(Stuble::KEY_WORKING_PATH);
        } else {
            $files = $this->stuble->getMergedStubsFiles();
        }

        return array_values($files);
    }

    protected function filterFiles(array $files, string $keyword)
    {
        return array_filter($files, function ($file) use ($keyword) {
            return is_numeric(strpos(strtolower($file['source_path']), strtolower($keyword)));
        });
    }
}