<?php

namespace Emsifa\Stuble\Commands;

use Emsifa\Stuble\Helper;
use Emsifa\Stuble\Stub;
use Emsifa\Stuble\Result;
use InvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class Make extends StubleCommand
{
    protected $name = 'make';
    protected $description = 'Generate file by given stub file/directory.';
    protected $help = '';

    protected $args = [
        'stub' => [
            'type' => InputArgument::REQUIRED,
            'description' => 'Stub file/directory.'
        ],
        'parameters' => [
            'type' => InputArgument::IS_ARRAY,
            'description' => 'Parameter values',
        ],
    ];

    protected $options = [
        'dump' => [
            'alias' => 'd',
            'description' => 'Dump render results.'
        ],
        'no-subdir' => [
            'description' => 'Without subdirectories.'
        ],
        'excludes' => [
            'alias' => 'e',
            'type' => InputOption::VALUE_OPTIONAL,
            'description' => 'Exclude some files.'
        ],
        'skip-exists' => [
            'alias' => 'S',
            'type' => InputOption::VALUE_NONE,
            'description' => 'Skip (not overwrite) existing files without asking.',
        ],
        'overwrite' => [
            'alias' => 'F',
            'type' => InputOption::VALUE_NONE,
            'description' => 'Overwrite existing files without asking.',
        ],
    ];

    protected function handle()
    {
        $query = $this->argument('stub');
        $parameters = $this->argument('parameters');
        $subdir = !$this->option('no-subdir');
        $excludes = $this->option('excludes');
        $skipExists = $this->option('skip-exists');
        $overwrite = $this->option('overwrite');

        if ($skipExists && $overwrite) {
            throw new InvalidOptionException("Cannot enable skip-exists and overwrite options at the same time.");
        }

        $parameters = $this->resolveParameters($parameters);

        $stubsFiles = $this->stuble->findStubsFiles($query, $subdir);

        if (empty($stubsFiles)) {
            $this->error("Stub file '{$query}.stub' not found.");
        }

        $stubsFiles = $this->filterDuplicates($stubsFiles);

        if ($excludes) {
            $stubsFiles = $this->excludeFiles($stubsFiles, $excludes, "/stubs/{$query}");
        }

        if (count($stubsFiles) > 1) {
            $this->info("# STUBS FILES");
            foreach ($stubsFiles as $i => $file) {
                $sourcePath = ltrim($file['source_path'], '/');
                $sourceName = $file['source'];
                $this->text("<fg=magenta>".($i + 1) . ")</> <fg=green>{$sourceName}</>/{$sourcePath}");
            }
            $this->nl();
        } else {
            $file = $stubsFiles[0];
            $this->info("# STUB FILE");
            $sourcePath = ltrim($file['source_path'], '/');
            $sourceName = $file['source'];
            $this->writeln("<fg=green>{$sourceName}</>:{$sourcePath}");
            $this->nl();
        }

        $stubs = array_map(function ($file) {
            $sourcePath = ltrim($file['source_path'], '/');
            $sourceName = $file['source'];
            return $this->stuble->makeStub($sourceName.':'.$sourcePath);
        }, $stubsFiles);

        $this->info("# FILL PARAMETERS");
        $stubsParameters = $this->collectParams($stubs);

        $paramsValues = $parameters;
        if (count($parameters) > 0) {
            $this->validateParameters($parameters, $stubsParameters);
        } else {
            $paramsValues = $this->askParams($stubs);
        }

        $dump = $this->option('dump');

        if (!$dump) {
            $this->nl();
            $this->info("# SAVING FILES");
            foreach ($stubs as $stub) {
                $result = $stub->render($paramsValues);
                $this->askToSave($result, $stub, $skipExists, $overwrite);
            }
        } else {
            foreach ($stubs as $stub) {
                $result = $stub->render($paramsValues);
                $this->dumpResult($result, $stub);
            }
        }
    }

    protected function excludeFiles(array $stubsFiles, string $excludes, string $basepath)
    {
        $excludes = array_map(function ($pattern) {
            $pattern = trim($pattern);
            $hasAsterisk = strpos($pattern, '*');
            if (!$hasAsterisk) {
                return [
                    'type' => 'exact',
                    'pattern' => preg_replace("/\.stub$/", "", $pattern).".stub"
                ];
            } else {
                $replacers = [
                    "\*\*" => "[^\\n]+",
                    "\*" => "[^\/]+(\.\w+)?$",
                ];

                $pattern = preg_quote($pattern, "/");
                foreach ($replacers as $search => $regex) {
                    $pattern = str_replace($search, $regex, $pattern);
                }

                return [
                    'type' => 'regex',
                    'pattern' => "/".$pattern."/"
                ];
            }
        }, explode(",", $excludes));

        return array_filter($stubsFiles, function ($file) use ($excludes, $basepath) {
            $filepath = preg_replace("/^".preg_quote($basepath, "/")."\//", "", $file['source_path']);
            foreach ($excludes as $pattern) {
                if ($pattern['type'] == 'exact' && $filepath == $pattern['pattern']) {
                    return false;
                } elseif ($pattern['type'] == 'regex' && preg_match($pattern['pattern'], $filepath)) {
                    return false;
                }
            }
            return true;
        });
    }

    protected function askToSave(Result $result, Stub $stuble, ?bool $skipExists, ?bool $overwrite)
    {
        $filename = $stuble->filename;
        $content = (string)$result;
        $savePath = $result->getSavePath();
        $append = $result->getAppendOption();

        if ($append) {
            $dest = $this->getWorkingPath().'/'.$append['file'];
            $this->append($content, $append);
            $this->text("+ File '{$append['file']}' appended!");
        } else {
            if (!$savePath) {
                $savePath = $this->ask("Set {$filename} save path:");
            }

            $dest = $this->getWorkingPath() . '/' . $savePath;
            $fileExists = is_file($dest);

            if ($fileExists && $skipExists) {
                $this->info("[skipped] {$savePath}");
                return;
            }

            if ($fileExists && !$overwrite && !$this->confirm("File '{$savePath}' already exists. Do you want to overwrite it?")) {
                $this->info("[skipped] {$savePath}");
                return;
            }

            $action = $fileExists ? "overwritten" : "created";

            $this->save($dest, $content);
            $this->info("[{$action}] {$savePath}");
        }
    }

    protected function dumpResult(Result $result, Stub $stuble)
    {
        $stub = $stuble->filename;
        $this->nl();
        $this->info("[{$stub}]" . PHP_EOL);
        $this->text($result->getRawContent());
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
        Helper::createDirectoryIfNotExists(dirname($dest));
        file_put_contents($dest, $content);
    }

    protected function append(string $text, $appendOption)
    {
        $dest = $this->getWorkingPath().'/'.$appendOption['file'];
        $after = $appendOption['after'];
        $before = $appendOption['before'];
        $line = $appendOption['line'];

        Helper::createDirectoryIfNotExists(dirname($dest));

        if ($before) {
            return $this->appendBefore($dest, $text, $before);
        } elseif ($after) {
            return $this->appendAfter($dest, $text, $after);
        } elseif ($line) {
            return $this->appendToLine($dest, $text, $line);
        } else {
            return file_put_contents($dest, "\n".$text, FILE_APPEND);
        }
    }

    protected function appendBefore($dest, $text, $keyword)
    {
        $line = $this->findLineNumber($dest, $keyword);

        if (!file_exists($dest)) {
            $text = $text."\n".$keyword."\n";
        }

        $this->appendToLine($dest, $text, $line);
    }

    protected function appendAfter($dest, $text, $keyword)
    {
        $line = $this->findLineNumber($dest, $keyword);

        if (!file_exists($dest)) {
            $text = $keyword."\n".$text;
        }

        $this->appendToLine($dest, $text, $line + 1);
    }

    protected function appendToLine($dest, $text, $line)
    {
        if ($line < 1) {
            return file_put_contents($dest, $text);
        }

        if (!file_exists($dest)) {
            return file_put_contents($dest, $text);
        }

        $lines = explode("\n", file_get_contents($dest));
        array_splice($lines, $line - 1, 0, $text);

        $content = implode("\n", $lines);
        return file_put_contents($dest, $content);
    }

    protected function findLineNumber($dest, $keyword)
    {
        if (!file_exists($dest)) {
            return 0;
        }

        $lines = explode("\n", file_get_contents($dest));
        $regex = "/".preg_quote($keyword, "/")."/";
        foreach ($lines as $i => $line) {
            if (preg_match($regex, $line)) {
                return $i + 1;
            }
        }

        return 0;
    }

    /**
     * Remove same source_path from stubs files
     */
    public function filterDuplicates(array $stubsFiles)
    {
        return array_values(array_reduce($stubsFiles, function ($result, $stubFile) {
            if (!isset($result[$stubFile['source_path']])) {
                $result[$stubFile['source_path']] = $stubFile;
            }
            return $result;
        }, []));
    }

    protected function resolveParameters(array $parameters)
    {
        $values = [];
        foreach ($parameters as $param) {
            [$key, $value] = $this->extractParameter($param);
            $values[$key] = $value;
        }
        return $values;
    }

    protected function extractParameter(string $param)
    {
        $split = explode(":", $param, 2);

        if (count($split) == 1) {
            throw new InvalidArgumentException("Invalid parameter format. Parameter should be written like \"key:value\"");
        }

        return $split;
    }

    protected function validateParameters(array $inputParameters, array $stubsParameters)
    {
        $inputKeys = array_keys($inputParameters);
        $stubsKeys = array_keys($stubsParameters);

        $unusedParameters = array_diff($inputKeys, $stubsKeys);
        if ($unusedParameters) {
            throw new InvalidArgumentException("Unused parameter: " . implode(", ", $unusedParameters));
        }


        $requiredKeys = [];
        foreach ($stubsParameters as $k => $v) {
            if (!$v) {
                $requiredKeys[] = $k;
            }
        }

        $missingParameters = array_diff($requiredKeys, $inputKeys);
        if ($missingParameters) {
            throw new InvalidArgumentException("Missing parameter: ". implode(", ", $missingParameters));
        }
    }
}
