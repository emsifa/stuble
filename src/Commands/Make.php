<?php

namespace Emsifa\Stuble\Commands;

use Emsifa\Stuble\Helper;
use Emsifa\Stuble\Result;
use Emsifa\Stuble\Stub;
use InvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class Make extends StubleCommand
{
    protected string $name = 'make';
    protected string $description = 'Generate file by given stub file/directory.';
    protected string $help = '';

    protected array $args = [
        'stub' => [
            'type' => InputArgument::REQUIRED,
            'description' => 'Stub file/directory.',
        ],
        'parameters' => [
            'type' => InputArgument::IS_ARRAY,
            'description' => 'Parameter values',
        ],
    ];

    protected array $options = [
        'dump' => [
            'alias' => 'd',
            'description' => 'Dump render results.',
        ],
        'info' => [
            'type' => InputOption::VALUE_NONE,
            'description' => 'Show stub files and parameters needed from given stub file/directory.',
        ],
        'no-subdir' => [
            'description' => 'Without subdirectories.',
        ],
        'excludes' => [
            'alias' => 'e',
            'type' => InputOption::VALUE_OPTIONAL,
            'description' => 'Exclude some files.',
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
            'description' => 'Show list of stub files being generated.',
        ],
    ];

    /**
     * @inheritdoc
     */
    protected function handle()
    {
        $query = $this->argument('stub');
        $parameters = $this->argument('parameters');
        $subdir = ! $this->option('no-subdir');
        $excludes = $this->option('excludes');
        $skipExists = $this->option('skip-exists');
        $overwrite = $this->option('overwrite');
        $showStubs = $this->option('show-stubs');
        $info = $this->option('info');

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

        if ($info) {
            $this->displayStubsFiles($stubsFiles);
            $this->displayParameters($stubsParameters);

            return;
        }

        $paramsValues = $parameters;
        if (count($parameters) > 0) {
            $this->validateParameters($parameters, $stubsParameters);
        } else {
            $paramsValues = $this->askParams($stubs);
        }

        $dump = $this->option('dump');

        if (! $dump) {
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

    /**
     * Print stub files to OutputInterface
     *
     * @param  array $stubsFiles
     * @return void
     */
    protected function displayStubsFiles(array $stubsFiles): void
    {
        $digitsCount = strlen((string) count($stubsFiles));

        $this->writeln(str_repeat(" ", $digitsCount + 2)." <fg=blue;options=bold>STUBS</> ");
        foreach ($stubsFiles as $i => $file) {
            $sourcePath = ltrim($file['source_path'], '/');
            $sourceName = $file['source'];
            $no = intval($i) + 1;
            $this->text("<fg=blue;options=bold> " . str_pad((string) $no, $digitsCount, " ", STR_PAD_LEFT) . " </> <fg=green;options=bold>{$sourceName}</>/{$sourcePath}");
        }
        $this->nl();
    }

    /**
     * Print parameters to OutputInterface
     *
     * @param  array $parameters
     * @return void
     */
    protected function displayParameters(array $parameters): void
    {
        $digitsCount = strlen((string) count($parameters));

        $this->writeln(str_repeat(" ", $digitsCount + 2)." <fg=blue;options=bold>PARAMETERS</> ");
        $i = 0;
        foreach ($parameters as $key => $value) {
            $this->text("<fg=blue;options=bold> " . str_pad((string) ++$i, $digitsCount, " ", STR_PAD_LEFT) . " </> <fg=green;options=bold>{$key}</>" . ($value ? ": {$value}" : ""));
        }
    }

    /**
     * Remove excluded files from stub files
     *
     * @param  array $stubsFiles
     * @param  string $excludes
     * @param  string $basePath
     * @return array
     */
    protected function excludeFiles(array $stubsFiles, string $excludes, string $basepath): array
    {
        $excludes = array_map(function ($pattern) {
            $pattern = trim($pattern);
            $hasAsterisk = strpos($pattern, '*');
            if (! $hasAsterisk) {
                return [
                    'type' => 'exact',
                    'pattern' => preg_replace("/\.stub$/", "", $pattern).".stub",
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
                    'pattern' => "/".$pattern."/",
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

    /**
     * Processing generated result
     * either create, overwrite, skip, or append
     *
     * @param  Result $result
     * @param  Stub $stuble
     * @param  bool|null $skipExists
     * @param  bool|null $overwrite
     * @return void
     */
    protected function processFile(Result $result, Stub $stuble, ?bool $skipExists, ?bool $overwrite): void
    {
        $filename = $stuble->filename;
        $content = (string) $result;
        $savePath = $result->getSavePath();
        $append = $result->getAppendOption();

        if ($append) {
            $this->append($content, $append);
            return;
        }

        if (! $savePath) {
            $savePath = $this->ask("Set {$filename} save path:");
        }

        $dest = $this->getWorkingPath() . '/' . $savePath;
        $fileExists = is_file($dest);

        if ($fileExists && $skipExists) {
            $this->skip($savePath);
        } elseif ($fileExists && ! $overwrite && ! $this->confirmOverwrite($savePath)) {
            $this->skip($savePath);
        } elseif ($fileExists && $overwrite) {
            $this->overwrite($dest, $content);
        } else {
            $this->create($dest, $content);
        }
    }

    /**
     * Confirm file overwriting
     *
     * @param  string $savePath
     * @return void
     */
    protected function confirmOverwrite(string $savePath): void
    {
        $this->confirm("<bg=yellow;fg=black> ? </> File '{$savePath}' already exists. Do you want to overwrite it? <fg=magenta>[y/N]</>");
    }

    /**
     * Print skip message
     *
     * @param  string $file
     * @return void
     */
    protected function skip(string $file): void
    {
        $this->writeln("<bg=gray;fg=black> skip </> {$file}");
    }

    /**
     * Create new file and print message
     *
     * @param  string $file
     * @param  string $content
     * @return void
     */
    protected function create(string $file, string $content): void
    {
        $relativePath = $this->toRelativePath($file);
        $this->save($file, $content);
        $this->writeln("<bg=green;fg=black;> create </> {$relativePath}");
    }

    /**
     * Overwrite file and print message
     *
     * @param  string $file
     * @param  string $content
     * @return void
     */
    protected function overwrite(string $file, string $content)
    {
        $relativePath = $this->toRelativePath($file);
        $this->save($file, $content);
        $this->writeln("<bg=magenta;fg=black;> overwrite </> {$relativePath}");
    }

    /**
     * Convert absolute path to relative path
     *
     * @param  string $dest
     * @return string
     */
    protected function toRelativePath(string $dest): string
    {
        return ltrim(str_replace($this->getWorkingPath(), "", $dest), "/");
    }

    /**
     * Dump/print result content
     *
     * @param  Result $result
     * @param  Stub $stuble
     * @return void
     */
    protected function dumpResult(Result $result, Stub $stuble): void
    {
        $stub = $stuble->filename;
        $this->nl();
        $this->info("[{$stub}]" . PHP_EOL);
        $this->text($result->getRawContent());
    }

    /**
     * Ask parameters' value
     *
     * @param  array $stubles
     */
    protected function askParams(array $stubles)
    {
        $params = $this->collectParams($stubles);

        foreach ($params as $key => $value) {
            $params[$key] = $this->ask("<bg=yellow;fg=black> ? </> {$key}:", $value);
        }

        return $params;
    }

    /**
     * Collect params from stuble files
     *
     * @param  array $stubles
     * @return array
     */
    protected function collectParams(array $stubles): array
    {
        $params = [];
        foreach ($stubles as $stuble) {
            $stubleParams = $stuble->getParamsValues(false);
            foreach ($stubleParams as $key => $value) {
                if (! isset($params[$key])) {
                    $params[$key] = $value;
                }

                if (! $params[$key] && $value) {
                    $params[$key] = $value;
                }
            }
        }

        return $params;
    }

    /**
     * Save file to disk
     *
     * @param  string $dest
     * @param  string $content
     * @return void
     */
    protected function save(string $dest, string $content): void
    {
        Helper::createDirectoryIfNotExists(dirname($dest));
        file_put_contents($dest, $content);
    }

    /**
     * Append given text to $appendOption['file']
     *
     * @param  string $text
     * @param  array $appendOption
     * @return void
     */
    protected function append(string $text, array $appendOption): void
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

    /**
     * Append $text before given $keyword in $dest file
     *
     * @param  string $dest
     * @param  string $text
     * @param  string $keyword
     * @return void
     */
    protected function appendBefore(string $dest, string $text, string $keyword): void
    {
        $line = $this->findLineNumber($dest, $keyword);

        if (! file_exists($dest)) {
            $text = $text."\n".$keyword."\n";
        }

        $this->appendToLine($dest, $text, $line);
    }

    /**
     * Append $text after $keyword in $dest file
     *
     * @param  string $dest
     * @param  string $text
     * @param  string $keyword
     * @return void
     */
    protected function appendAfter(string $dest, string $text, string $keyword): void
    {
        $line = $this->findLineNumber($dest, $keyword);

        if (! file_exists($dest)) {
            $text = $keyword."\n".$text;
        }

        $this->appendToLine($dest, $text, $line + 1);
    }

    /**
     * Append $text in line $line inside $dest file
     *
     * @param  string $dest
     * @param  string $text
     * @param  int $line
     * @return void
     */
    protected function appendToLine(string $dest, string $text, int $line): void
    {
        if ($line < 1) {
            file_put_contents($dest, $text);
            return;
        }

        if (! file_exists($dest)) {
            file_put_contents($dest, $text);
            return;
        }

        $lines = explode("\n", file_get_contents($dest));
        array_splice($lines, $line - 1, 0, $text);

        $content = implode("\n", $lines);

        file_put_contents($dest, $content);
    }

    /**
     * Get line number containing $keyword in $dest file
     *
     * @param  string $dest
     * @param  string $keyword
     * @return int
     */
    protected function findLineNumber(string $dest, string $keyword): int
    {
        if (! file_exists($dest)) {
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
     * Remove duplicate source_path from stubs files
     *
     * @param  array $stubsFiles
     * @return array
     */
    public function filterDuplicates(array $stubsFiles)
    {
        return array_values(array_reduce($stubsFiles, function ($result, $stubFile) {
            if (! isset($result[$stubFile['source_path']])) {
                $result[$stubFile['source_path']] = $stubFile;
            }

            return $result;
        }, []));
    }

    /**
     * Resolve parameters' value
     *
     * @param  array $parameters
     * @return array
     */
    protected function resolveParameters(array $parameters): array
    {
        $values = [];
        foreach ($parameters as $param) {
            [$key, $value] = $this->extractParameter($param);
            $values[$key] = $value;
        }

        return $values;
    }

    /**
     * Extract parameter from given string
     *
     * @param  string $param
     * @return [string, string]
     * @throws InvalidArgumentException
     */
    protected function extractParameter(string $param): array
    {
        $split = explode(":", $param, 2);

        if (count($split) == 1) {
            throw new InvalidArgumentException("Invalid parameter format. Parameter should be written like \"key:value\"");
        }

        return $split;
    }

    /**
     * Validate parameters to ensure:
     * 1. No unused parameter
     * 2. No missing parameter
     *
     * @param  array $inputParameters
     * @param  array $stubsParameters
     * @return void
     * @throws InvalidArgumentException
     */
    protected function validateParameters(array $inputParameters, array $stubsParameters): void
    {
        $inputKeys = array_keys($inputParameters);
        $stubsKeys = array_keys($stubsParameters);

        $unusedParameters = array_diff($inputKeys, $stubsKeys);
        if ($unusedParameters) {
            throw new InvalidArgumentException("Unused parameter: " . implode(", ", $unusedParameters));
        }


        $requiredKeys = [];
        foreach ($stubsParameters as $k => $v) {
            if (! $v) {
                $requiredKeys[] = $k;
            }
        }

        $missingParameters = array_diff($requiredKeys, $inputKeys);
        if ($missingParameters) {
            throw new InvalidArgumentException("Missing parameter: ". implode(", ", $missingParameters));
        }
    }
}
