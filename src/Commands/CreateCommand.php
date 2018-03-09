<?php

namespace Emsifa\Stuble\Commands;

use Emsifa\Stuble\Result;
use Emsifa\Stuble\Stuble;
use Rakit\Console\Command;

class CreateCommand extends StubleCommand
{

    protected $signature = "create {stub} {--d|dump::Dump render results.}";

    protected $description = "Create file from given stub.";

    public function handle($stub)
    {
        $stubsFiles = $this->findStubsFiles($stub);

        if (empty($stubsFiles)) {
            $this->error("Stub file '{$stub}.stub' not found.");
            exit;
        }

        $stubles = array_map(function ($file) {
            return $this->makeStuble($file);
        }, $stubsFiles);

        $this->writeln("# FILL PARAMETERS", "cyan");
        $paramsValues = $this->askParams($stubles);

        $dump = $this->option('dump');

        if (!$dump) {
            $this->writeln("# SAVING FILES", "cyan");
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
        $content = (string) $result;
        $savePath = $result->getSavePath();
        if (!$savePath) {
            $savePath = $this->ask("Set {$filename} save path:");
        }
        $dest = $this->getWorkingPath() . '/' . $savePath;

        if (is_file($dest) && !$this->confirm("File '{$savePath}' already exists. Do you want to overwrite it?")) {
            return;
        }

        $this->save($dest, $content);
        $this->writeln("+ File '{$savePath}' saved!", "green");
    }

    protected function dumpResult(Result $result, Stuble $stuble)
    {
        $params = $result->getParams();
        $stub = $stuble->filename;

        $len = max(array_map(function ($k) { return strlen($k); }, array_keys($params)));

        $this->writeln('');
        $this->writeln("[{$stub}]".PHP_EOL, "green");
        foreach ($params as $key => $value) {
            $this->writeln(str_pad($key, $len, " ", STR_PAD_RIGHT)." : {$value}", "blue");
        }
        $this->writeln($result);
    }

    protected function makeStuble(string $file)
    {
        $content = file_get_contents($file);
        $stuble = new Stuble($content);
        $stuble->filename = pathinfo($file, PATHINFO_FILENAME);
        $this->includesStubleInits($stuble, $file);

        return $stuble;
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
            $stubleParams = $stuble->getParamsValues();
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