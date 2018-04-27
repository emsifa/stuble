<?php

namespace Emsifa\Stuble\Commands;

use Emsifa\Stuble\Stuble;
use Emsifa\Stuble\Result;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class GenerateStubCommand extends StubleCommand
{

    protected $name = 'gen:stub';
    protected $description = 'Generate file by given stub file/directory.';
    protected $help = '';

    protected $args = [
        'stub' => [
            'type' => InputArgument::REQUIRED,
            'description' => 'Stub file/directory.'
        ]
    ];

    protected $options = [
        'dump' => [
            'alias' => 'd',
            'description' => 'Dump render results.'
        ]
    ];

    protected function handle()
    {
        $query = $this->argument('stub');

        $stubsFiles = $this->factory->findStubsFiles($query);

        if (empty($stubsFiles)) {
            $this->error("Stub file '{$query}.stub' not found.");
        }

        if (count($stubsFiles) > 1) {
            $this->info("# FOUND STUBS FILES");
            foreach ($stubsFiles as $i => $file) {
                $sourcePath = ltrim($file['source_path'], '/');
                $sourceName = $file['source'];
                $this->text("<fg=magenta>".($i + 1) . ")</> <fg=green>{$sourceName}</>/{$sourcePath}");
            }
            $this->nl();
        } else {
            $this->info("# FOUND STUB FILE");
            $sourcePath = ltrim($file['source_path'], '/');
            $sourceName = $file['source'];
            $this->writeln("<fg=green>{$sourceName}</>:{$sourcePath}");
            $this->nl();
        }

        $stubles = array_map(function ($file) {
            $sourcePath = ltrim($file['source_path'], '/');
            $sourceName = $file['source'];
            return $this->factory->makeStub($sourceName.':'.$sourcePath);
        }, $stubsFiles);

        $this->info("# FILL PARAMETERS");
        $paramsValues = $this->askParams($stubles);

        $dump = $this->option('dump');

        if (!$dump) {
            $this->nl();
            $this->info("# SAVING FILES");
            foreach ($stubles as $stuble) {
                $result = $stuble->render($paramsValues);
                $this->askToSave($result, $stuble);
            }
        } else {
            foreach ($stubles as $stuble) {
                $result = $stuble->render($paramsValues);
                $this->dumpResult($result, $stuble);
            }
        }
    }

    protected function askToSave(Result $result, Stuble $stuble)
    {
        $filename = $stuble->filename;
        $content = (string)$result;
        $savePath = $result->getSavePath();
        if (!$savePath) {
            $savePath = $this->ask("Set {$filename} save path:");
        }
        $dest = $this->getWorkingPath() . '/' . $savePath;

        if (is_file($dest) && !$this->confirm("File '{$savePath}' already exists. Do you want to overwrite it?")) {
            return;
        }

        $this->save($dest, $content);
        $this->text("+ File '{$savePath}' saved!");
    }

    protected function dumpResult(Result $result, Stuble $stuble)
    {
        $params = $result->getParams();
        $stub = $stuble->filename;

        $len = max(array_map(function ($k) {
            return strlen($k);
        }, array_keys($params)));

        $this->nl();
        $this->info("[{$stub}]" . PHP_EOL);
        foreach ($params as $key => $value) {
            $this->text(str_pad($key, $len, " ", STR_PAD_RIGHT) . " : {$value}");
        }
        $this->text($result);
    }

    protected function askParams(array $stubles)
    {
        $params = $this->collectParams($stubles);

        foreach ($params as $key => $value) {
            $params[$key] = $this->ask("{$key}?", $value);
        }

        return $params;
    }

    protected function collectParams(array $stubles)
    {
        $params = [];
        foreach ($stubles as $stuble) {
            $stubleParams = $stuble->getParamsValues(false);
            foreach ($stubleParams as $key => $value) {
                if (!isset($params[$key])) {
                    $params[$key] = $value;
                }

                if (!$params[$key] && $value) {
                    $params[$key] = $value;
                }
            }
        }

        return $params;
    }

    protected function save(string $dest, string $content)
    {
        $this->createDirectoryIfNotExists(dirname($dest));
        file_put_contents($dest, $content);
    }

    protected function createDirectoryIfNotExists($dir)
    {
        $paths = explode("/", $dir);
        $path = "";
        while (count($paths)) {
            $path .= "/" . array_shift($paths);
            if (!is_dir($path)) {
                mkdir($path);
            }
        }
    }
}