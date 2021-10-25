<?php

namespace Emsifa\Stuble\Commands;

use Emsifa\Stuble\Filters;
use Emsifa\Stuble\Gitignore;
use Emsifa\Stuble\Parser;

class Stublify extends StubleCommand
{
    protected string $name = 'stublify';
    protected string $description = 'Transform file(s) into stub(s).';
    protected string $help = '';
    protected string $tempDir;
    protected bool $completed;

    protected array $args = [
        'file' => [
            'description' => 'File or directory to transform.',
        ],
        'dest' => [
            'description' => 'Output filename or directory name.',
        ],
    ];

    protected array $options = [
        'global' => [
            'alias' => 'G',
            'description' => 'Save stubs to global path ($STUBLE_HOME/stubs).',
        ],
        'force' => [
            'alias' => 'f',
            'description' => 'Force rewrite existing stubs.',
        ],
    ];

    /**
     * @inheritdoc
     * @psalm-suppress UndefinedConstant
     */
    protected function handle()
    {
        $file = $this->argument('file');
        $dest = $this->argument('dest');
        $global = $this->option('global');
        $force = $this->option('force');

        $filepath = realpath($file);
        $workingPath = $this->getWorkingPath();
        $stubsPath = $global ? $this->stuble->getPath('STUBLE_HOME').'/stubs' : $this->stuble->getPath('.').'/stubs';
        $destPath = $stubsPath.'/'.$dest;

        // DO SOME CHECKS AND CONFIRMATION
        // -------------------------------------------------------------------------
        if (! $filepath) {
            return $this->error("File or directory '{$file}' is not exists.");
        }

        if (file_exists($destPath) && ! $force) {
            return $this->error("Whoops! Seems like you have '{$file}' exists in your stubs directory. Use '-f' to force rewrite.");
        }

        if (file_exists($destPath) && $force) {
            $this->warning("\n[WARNING]\nYour current '{$file}' stub(s) will be deleted and replaced with new one.");
            if (! $this->confirm("Do you want to continue [y/N]?", false)) {
                $this->info("Canceled!");
                exit;
            }
        }

        // COLLECTING FILES
        // -------------------------------------------------------------------------
        $gitignore = $this->makeGitignore($filepath, $workingPath);
        $files = $this->getFiles($filepath, $gitignore);

        // CREATE TEMP FILES
        // -------------------------------------------------------------------------
        $this->tempDir = $workingPath.'/tmp-stublify-'.uniqid().'';

        // Clean temporary files if user interrupts (Ctrl+C)
        pcntl_signal(SIGTERM, [&$this, 'cleanup']);
        pcntl_signal(SIGINT, [&$this, 'cleanup']);

        $tempFiles = $this->createTempFiles($files, $workingPath);

        // COLLECTING TEXTS TO REPLACES
        // -------------------------------------------------------------------------
        $replaces = [];
        while (true) {
            $this->nl();
            $text = $this->askTextToReplace();
            if (! $text) {
                break;
            }

            $files = $this->findFilesContains($text, $tempFiles);
            if (count($files)) {
                $num = number_format(count($files), 0);
                $this->info(str_repeat("-", 60));
                $this->writeln("Found '{$text}' in {$num} files.", 'success', ['bold']);
                $this->info(str_repeat("-", 60));
            } else {
                $this->warning(str_repeat("-", 60));
                $this->writeln("Whoops. No file contain '{$text}'.", "danger", ['bold']);
                $this->warning(str_repeat("-", 60));

                continue;
            }

            $parameter = $this->askParameter($text);
            $replaces[$text] = [
                'files' => $files,
                'parameter' => $parameter,
            ];

            if ($this->confirm("Do you want to replace other formats of '{$text}' [Y/n]?", true)) {
                $otherFormats = $this->findOtherFormats($tempFiles, $text, $parameter, $replaces);
                if (count($otherFormats)) {
                    foreach ($otherFormats as $text => $data) {
                        $num = number_format(count($data['files']), 0);
                        $param = $data['parameter']['key'].'.'.implode('.', $data['parameter']['filters']);
                        $this->info("- {$text} = {$param} ({$num} files)");
                    }
                    $replaces = array_merge($replaces, $otherFormats);
                }
            }
        }

        // COOK IT!
        // -------------------------------------------------------------------------

        // Sort $replaces keys and reverse it so longest text will be replaced first
        ksort($replaces, SORT_NATURAL);
        $replaces = array_reverse($replaces);

        $this->nl();

        // > Replace Text With Parameter
        $this->process("Replacing text with parameter", function () use ($replaces) {
            foreach ($replaces as $text => $data) {
                foreach ($data['files'] as $file) {
                    $this->replaceTextInFile($text, $data['parameter']['code'], $file);
                }
            }
        });

        // > Put output path configuration to each stubs files
        $this->process("Putting output path configuration", function () use ($tempFiles, $replaces) {
            foreach ($tempFiles as $file) {
                $relativePath = str_replace($this->tempDir.'/', "", $file);
                $relativePath = preg_replace("/\.stub$/", "", $relativePath);
                foreach ($replaces as $text => $data) {
                    $relativePath = str_replace($text, $data['parameter']['code'], $relativePath);
                }
                $content = file_get_contents($file);
                $content = $this->putConfigs($content, [
                    'path' => $relativePath,
                ]);
                file_put_contents($file, $content);
            }
        });

        // > Removing current stubs file
        if (file_exists($destPath)) {
            $this->process("Removing current stubs file", function () use ($destPath) {
                $this->removeFileOrDirectory($destPath);
            });
        }

        // > Generating stubs file (moving temporary files to destination)
        $this->process("Generating stubs file", function () use ($destPath) {
            rename($this->tempDir, $destPath);
        });

        // DONE
        // -------------------------------------------------------------------------
        if (is_dir($destPath)) {
            $this->nl();
            $this->info("Type <fg=green;options=bold>'stuble ls {$dest}'</> to see your generated stubs file.");
        }
    }

    /**
     * Find given text other formats
     *
     * @param  array $tempFiles
     * @param  string $text
     * @param  array $parameter
     * @param  array $excepts
     * @return array
     */
    protected function findOtherFormats(array $tempFiles, string $text, array $parameter, array $excepts = [])
    {
        $filters = new Filters();
        $usedFilters = $parameter['filters'] ?: [];
        $availableFilters = [
            'singular',
            'plural',
            'kebab',
            'snake',
            'camel',
            'pascal',
            'studly',
            'title',
            'words',
        ];

        $otherFormats = [];
        foreach ($availableFilters as $filter) {
            if (in_array($filter, $usedFilters)) {
                continue;
            }

            $filteredText = $filters->{$filter}($text);
            if (! isset($excepts[$filteredText])) {
                $files = $this->findFilesContains($filteredText, $tempFiles);
                if (count($files)) {
                    $otherFormats[$filteredText] = [
                        'files' => $files,
                        'parameter' => array_merge($parameter, [
                            'filters' => array_merge($usedFilters, [$filter]),
                            'code' => '{? '.$parameter['key'].'.'.implode('.', array_merge($usedFilters, [$filter])).' ?}',
                        ]),
                    ];
                }
            }
        }

        foreach ($otherFormats as $text => $data) {
            $otherFormats = array_merge(
                $otherFormats,
                $this->findOtherFormats(
                    $data['files'],
                    $text,
                    $data['parameter'],
                    array_merge($excepts, $otherFormats)
                )
            );
        }

        return $otherFormats;
    }

    /**
     * Process callable $process with given info
     *
     * @param  string $info
     * @param  callable $process
     * @return void
     */
    protected function process(string $info, callable $process): void
    {
        $this->write($info." ... ", "info");
        call_user_func($process);
        $this->success("DONE");
    }

    /**
     * Ask parameter value
     *
     * @param  string $text
     * @return mixed
     */
    protected function askParameter(string $text): mixed
    {
        $param = $this->ask("Replace '{$text}' with parameter:");
        $parameter = $this->parseParameter($param);
        if (! $parameter) {
            $this->warning("Invalid parameter syntax '{$param}'.");

            return $this->askParameter($text);
        }

        return $parameter;
    }

    /**
     * Find file contains $text from given $tempFiles
     *
     * @param  string $text
     * @param  array $tempFiles
     * @return array
     */
    protected function findFilesContains(string $text, array $tempFiles): array
    {
        $files = [];
        foreach ($tempFiles as $file) {
            $content = file_get_contents($file);
            if (is_int(strpos($content, $text))) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * Ask user to type find text to replace
     *
     * @return mixed
     */
    protected function askTextToReplace(): mixed
    {
        return trim($this->ask("Type text to be replaced:"));
    }

    /**
     * Create temporary files
     *
     * @param  array $files
     * @param  string $sourceDir
     * @return array
     */
    protected function createTempFiles(array $files, $sourceDir)
    {
        $tempDir = $this->tempDir;
        $tempFiles = [];
        $this->makeDirectory($tempDir);

        foreach ($files as $file) {
            $filepath = str_replace($sourceDir."/", "", $file);
            $dest = $tempDir.'/'.$filepath.'.stub';
            $destdir = dirname($dest);

            if (! is_dir($destdir)) {
                $this->makeDirectory($destdir);
            }

            copy($file, $dest);
            $tempFiles[] = $dest;

            $this->dispatchSignal();
        }

        return $tempFiles;
    }

    /**
     * Replace $text to $replacement in given $file
     *
     * @param  string $file
     * @param  string $replacement
     * @param  string $file
     * @return string
     */
    protected function replaceTextInFile(string $text, string $replacement, string $file): string
    {
        $content = file_get_contents($file);
        $content = str_replace($text, $replacement, $content);

        return file_put_contents($file, $content);
    }

    /**
     * Put configuration to given $content
     *
     * @param  string $content
     * @param  array $configs
     * @return string
     */
    protected function putConfigs(string $content, array $configs): string
    {
        $lines[] = "===";
        foreach ($configs as $key => $value) {
            $lines[] = "{$key}: {$value}";
        }
        $lines[] = "===";
        $lines[] = $content;

        return implode("\n", $lines);
    }

    /**
     * Make directory recursived
     *
     * @param  string $directory
     * @return void
     */
    protected function makeDirectory(string $directory): void
    {
        $paths = explode("/", $directory);
        $dir = "";
        foreach ($paths as $path) {
            $dir .= "/{$path}";
            if (! is_dir($dir)) {
                mkdir($dir);
            }
        }
    }

    /**
     * Get files in given $filepath
     *
     * @param  string $filepath
     * @param  Gitignore|null $gitignore
     * @return array
     */
    protected function getFiles(string $filepath, ?Gitignore $gitignore = null): array
    {
        if (is_file($filepath)) {
            return [$filepath];
        }

        $dir = $filepath;
        $files = array_diff(scandir($filepath), ['.', '..']);

        $results = [];
        foreach ($files as $file) {
            $filepath = $dir.'/'.$file;
            if ($gitignore && $gitignore->isIgnoring($filepath)) {
                continue;
            }

            // ignore .git directory
            if ($file == '.git' && is_dir($filepath)) {
                continue;
            }

            if (is_dir($filepath)) {
                $results = array_merge($results, $this->getFiles($filepath, $gitignore));
            } else {
                $results[] = $filepath;
            }
        }

        return $results;
    }

    /**
     * Parse parameter declaration
     *
     * @param  string $parameterDeclaration
     * @return string|null
     */
    protected function parseParameter(string $parameterDeclaration): ?string
    {
        $params = Parser::parse("{? $parameterDeclaration ?}");
        if (! count($params)) {
            return null;
        } else {
            return $params[0];
        }
    }

    /**
     * Make Gitignore instance
     *
     * @param  string $filepath
     * @param  string $workingPath
     * @return Gitignore|null
     */
    protected function makeGitignore($filepath, $workingPath): ?Gitignore
    {
        $gitignore = null;
        if (is_dir($filepath)) {
            $gitignores = [
                $filepath.'/.gitignore',
                $workingPath.'/.gitignore',
            ];

            foreach ($gitignores as $gitignoreFile) {
                if (is_file($gitignoreFile)) {
                    $gitignore = new Gitignore($gitignoreFile);

                    break;
                }
            }
        }

        return $gitignore;
    }

    /**
     * Remove file or directory from given $fileOrDir
     *
     * @param  string $fileOrDir
     * @return void
     */
    protected function removeFileOrDirectory(string $fileOrDir): void
    {
        if (is_file($fileOrDir)) {
            unlink($fileOrDir);
        } elseif (is_dir($fileOrDir)) {
            $files = array_diff(scandir($fileOrDir), ['.', '..']);
            foreach ($files as $file) {
                $this->removeFileOrDirectory($fileOrDir.'/'.$file);
            }

            rmdir($fileOrDir);
        }
    }

    /**
     * Call pcntl_signal_dispatch
     *
     * @return void
     */
    protected function dispatchSignal(): void
    {
        pcntl_signal_dispatch();
    }

    /**
     * Clean up created file and directory during processing
     */
    public function cleanup()
    {
        $this->removeFileOrDirectory($this->tempDir);
        exit;
    }
}
