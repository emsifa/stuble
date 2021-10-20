<?php

namespace Emsifa\Stuble\Commands;

use Emsifa\Stuble\Gitignore;
use Emsifa\Stuble\Stuble;
use Emsifa\Stuble\Parser;
use Emsifa\Stuble\Filters;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Stublify extends StubleCommand
{
    protected $name = 'stublify';
    protected $description = 'Transform file(s) into stub(s).';
    protected $help = '';
    protected $tempDir;
    protected $completed;

    protected $args = [
        'file' => [
            'description' => 'File or directory to transform.'
        ],
        'dest' => [
            'description' => 'Output filename or directory name.'
        ]
    ];

    protected $options = [
        'global' => [
            'alias' => 'G',
            'description' => 'Save stubs to global path ($STUBLE_HOME/stubs).'
        ],
        'force' => [
            'alias' => 'f',
            'description' => 'Force rewrite existing stubs.'
        ],
    ];

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
        if (!$filepath) {
            return $this->error("File or directory '{$file}' is not exists.");
        }

        if (file_exists($destPath) && !$force) {
            return $this->error("Whoops! Seems like you have '{$file}' exists in your stubs directory. Use '-f' to force rewrite.");
        }

        if (file_exists($destPath) && $force) {
            $this->warning("\n[WARNING]\nYour current '{$file}' stub(s) will be deleted and replaced with new one.");
            if (!$this->confirm("Do you want to continue [y/N]?", false)) {
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
            if (!$text) {
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
                'parameter' => $parameter
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
                    'path' => $relativePath
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

    protected function findOtherFormats(array $tempFiles, $text, $parameter, $excepts = [])
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
            if (!isset($excepts[$filteredText])) {
                $files = $this->findFilesContains($filteredText, $tempFiles);
                if (count($files)) {
                    $otherFormats[$filteredText] = [
                        'files' => $files,
                        'parameter' => array_merge($parameter, [
                            'filters' => array_merge($usedFilters, [$filter]),
                            'code' => '{? '.$parameter['key'].'.'.implode('.', array_merge($usedFilters, [$filter])).' ?}'
                        ])
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

    protected function process($info, callable $process)
    {
        $this->write($info." ... ", "info");
        call_user_func($process);
        $this->success("DONE");
    }

    protected function askParameter($text)
    {
        $param = $this->ask("Replace '{$text}' with parameter:");
        $parameter = $this->parseParameter($param);
        if (!$parameter) {
            $this->warning("Invalid parameter syntax '{$param}'.");
            return $this->askParameter($text);
        }

        return $parameter;
    }

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

    protected function askTextToReplace()
    {
        return trim($this->ask("Type text to be replaced:"));
    }

    protected function createTempFiles(array $files, $sourceDir)
    {
        $tempDir = $this->tempDir;
        $tempFiles = [];
        $this->makeDirectory($tempDir);

        foreach ($files as $file) {
            $filepath = str_replace($sourceDir."/", "", $file);
            $dest = $tempDir.'/'.$filepath.'.stub';
            $destdir = dirname($dest);

            if (!is_dir($destdir)) {
                $this->makeDirectory($destdir);
            }

            copy($file, $dest);
            $tempFiles[] = $dest;

            $this->dispatchSignal();
        }

        return $tempFiles;
    }

    protected function replaceTextInFile($text, $replaced, $file)
    {
        $content = file_get_contents($file);
        $content = str_replace($text, $replaced, $content);
        return file_put_contents($file, $content);
    }

    protected function putConfigs($content, array $configs)
    {
        $lines[] = "===";
        foreach ($configs as $key => $value) {
            $lines[] = "{$key}: {$value}";
        }
        $lines[] = "===";
        $lines[] = $content;
        return implode("\n", $lines);
    }

    protected function makeDirectory($directory)
    {
        $paths = explode("/", $directory);
        $dir = "";
        foreach ($paths as $path) {
            $dir .= "/{$path}";
            if (!is_dir($dir)) {
                mkdir($dir);
            }
        }
    }

    protected function getFiles($filepath, $gitignore = null)
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

    protected function parseParameter($parameterDeclaration)
    {
        $params = Parser::parse("{? $parameterDeclaration ?}");
        if (!count($params)) {
            return null;
        } else {
            return $params[0];
        }
    }

    protected function makeGitignore($filepath, $workingPath)
    {
        $gitignore = null;
        if (is_dir($filepath)) {
            $gitignores = [
                $filepath.'/.gitignore',
                $workingPath.'/.gitignore'
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

    protected function removeFileOrDirectory($fileOrDir)
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

    protected function dispatchSignal()
    {
        pcntl_signal_dispatch();
    }

    public function cleanup()
    {
        $this->removeFileOrDirectory($this->tempDir);
        exit;
    }

    public function __destruct()
    {
        // $this->cleanup();
    }
}
