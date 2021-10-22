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
        'show-stubs' => [
            'alias' => 'L',
            'type' => InputOption::VALUE_NONE,
            'description' => 'Show list of stub files being generated.'
        ]
    ];

    protected function handle()
    {
        $query = $this->argument('stub');
        $parameters = $this->argument('parameters');
        $subdir = !$this->option('no-subdir');
        $excludes = $this->option('excludes');
        $skipExists = $this->option('skip-exists');
        $overwrite = $this->option('overwrite');
        $showStubs = $this->option('show-stubs');

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

        if ($showStubs) {
            $this->displayStubsFiles($stubsFiles);
        }

        $stubs = array_map(function ($file) {
            $sourcePath = ltrim($file['source_path'], '/');
            $sourceName = $file['source'];
            return $this->stuble->makeStub($sourceName.':'.$sourcePath);
        }, $stubsFiles);

        $stubsParameters = $this->collectParams($stubs);

        $paramsValues = $parameters;
        if (count($parameters) > 0) {
            $this->validateParameters($parameters, $stubsParameters);
        } else {
            $paramsValues = $this->askParams($stubs);
        }

        $dump = $this->option('dump');

        if (!$dump) {
            foreach ($stubs as $stub) {
                $result = $stub->render($paramsValues);
                $this->processFile($result, $stub, $skipExists, $overwrite);
            }
        } else {
            foreach ($stubs as $stub) {
                $result = $stub->render($paramsValues);
                $this->dumpResult($result, $stub);
            }
        }
    }

    protected function displayStubsFiles(array $stubsFiles)
    {
        $digitsCount = strlen((string) count($stubsFiles));

        $this->writeln(str_repeat(" ", $digitsCount + 2)." <fg=blue;options=bold>STUBS</> ");
        foreach ($stubsFiles as $i => $file) {
            $sourcePath = ltrim($file['source_path'], '/');
            $sourceName = $file['source'];
            $this->text("<fg=blue;options=bold> " . str_pad($i + 1, $digitsCount, " ", STR_PAD_LEFT) . " </> <fg=green;options=bold>{$sourceName}</>/{$sourcePath}");
        }
        $this->nl();
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

    protected function processFile(Result $result, Stub $stuble, ?bool $skipExists, ?bool $overwrite)
    {
        $filename = $stuble->filename;
        $content = (string) $result;
        $savePath = $result->getSavePath();
        $append = $result->getAppendOption();

        if ($append) {
            $dest = $this->getWorkingPath().'/'.$append['file'];
            return $this->append($content, $append);
        }

        if (!$savePath) {
            $savePath = $this->ask("Set {$filename} save path:");
        }

        $dest = $this->getWorkingPath() . '/' . $savePath;
        $fileExists = is_file($dest);

        if ($fileExists && $skipExists) {
            return $this->skip($savePath);
        }

        if ($fileExists && !$overwrite && !$this->confirmOverwrite($savePath)) {
            return $this->skip($savePath);
        }

        return $fileExists
            ? $this->overwrite($dest, $content)
            : $this->create($dest, $content);
    }

    protected function confirmOverwrite(string $savePath)
    {
        return $this->confirm("<bg=yellow;fg=black> ? </> File '{$savePath}' already exists. Do you want to overwrite it? <fg=magenta>[y/N]</>");
    }

    protected function skip(string $file)
    {
        $this->writeln("<bg=gray;fg=black> skip </> {$file}");
    }

    protected function create(string $file, string $content)
    {
        $relativePath = str_replace($this->getWorkingPath(), "", $file);
        $this->save($file, $content);
        $this->writeln("<bg=green;fg=black;> create </> {$relativePath}");
    }

    protected function overwrite(string $file, string $content)
    {
        $relativePath = str_replace($this->getWorkingPath(), "", $file);
        $this->save($file, $content);
        $this->writeln("<bg=magenta;fg=black;> create </> {$relativePath}");
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
            $params[$key] = $this->ask("<bg=yellow;fg=black> ? </> {$key}:", $value);
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
            $this->appendBefore($dest, $text, $before);
        } elseif ($after) {
            $this->appendAfter($dest, $text, $after);
        } elseif ($line) {
            $this->appendToLine($dest, $text, $line);
        } else {
            file_put_contents($dest, "\n".$text, FILE_APPEND);
        }

        $this->text("<bg=cyan;fg=black> append </> {$appendOption['file']}");
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
