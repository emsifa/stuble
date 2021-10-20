<?php

namespace Emsifa\Stuble\Commands;

use Emsifa\Stuble\Helper;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;
use PharData;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;

class Get extends StubleCommand
{
    protected $name = 'get';
    protected $description = 'Download stub files from github';
    protected $help = '';

    protected $args = [
        'repository' => [
            'type' => InputArgument::REQUIRED,
            'description' => '"username/repository" github'
        ]
    ];

    protected $options = [];

    /**
     * @var Client
     */
    protected $client;

    protected function handle()
    {
        $repository = $this->argument("repository");

        $this->ensureRepositoryValid($repository);

        [$repository, $version] = $this->extractVersion($repository);

        // $this->info("> <fg=green;options=bold>Ensuring repository exists</>");
        $this->ensureRepositoryExists($repository);

        // $this->info("> <fg=green;options=bold>Ensuring version/release exists</>");
        $this->ensureVersionExists($repository, $version);

        $archiveUrl = $this->getArchiveUrl($repository, $version);
        $acrhiveFilename = $this->getArchiveFilename($repository, $version);
        $archivePath = $this->getWorkingPath()."/{$acrhiveFilename}";

        $this->info("Downloading {$repository}@{$version}");
        $this->downloadArchive($archiveUrl, $archivePath);

        // $this->info("> <fg=green;options=bold>Extracting archive</>");
        $path = $this->getDownloadPath($repository);
        $rootDir = $this->getArchiveRootDir($repository, $version);

        $this->removeDirIfExists($path);
        $this->extractArchive($archivePath, $path, $rootDir);

        // $this->info("> <fg=green;options=bold>Removing tmp file</>");
        unlink($archivePath);
    }

    protected function ensureRepositoryValid(string $repository)
    {
        if (!preg_match("/^([a-zA-z0-9\._-]+)\/([a-zA-z0-9\._-]+)(\@[a-zA-z0-9\._-]+)?$/", $repository)) {
            $this->error("Invalid repository name: $repository");
            exit;
        }
    }

    protected function ensureRepositoryExists(string $repository)
    {
        $url = $this->getRepositoryUrl($repository);
        if (!$this->isUrlExists($url)) {
            $this->error("Repository '{$repository}' doesn't exists.");
            exit;
        }
    }

    protected function ensureVersionExists(string $repository, string $version)
    {
        $url = $this->getVersionUrl($repository, $version);
        if (!$this->isUrlExists($url)) {
            $this->error("Repository '{$repository}' doesn't have release '{$version}'.");
            exit;
        }
    }

    protected function extractVersion(string $repository): array
    {
        $split = explode("@", $repository);
        return count($split) > 1 ? [$split[0], $split[1]] : [$repository, "master"];
    }

    protected function getArchiveFilename(string $repository, string $version): string
    {
        return ".tmp_".str_replace("/", "_", $repository)."@{$version}.acrhive";
    }

    protected function getDownloadPath(string $repository): string
    {
        return $this->getWorkingPath()."/stubs/{$repository}";
    }

    protected function getRepositoryUrl(string $repository)
    {
        return "https://github.com/{$repository}";
    }

    protected function getArchiveUrl(string $repository, $version): string
    {
        return "https://github.com/{$repository}/archive/{$version}.tar.gz";
    }

    protected function getVersionUrl(string $repository, $version): string
    {
        return "https://github.com/{$repository}/tree/{$version}";
    }

    protected function getRepoName(string $repository): string
    {
        return explode("/", $repository)[1];
    }

    protected function getArchiveRootDir(string $repository, string $version): string
    {
        $repoName = explode("/", $repository)[1];
        if (preg_match("/^v\d+(\.\d+(\.\d+)?)?$/", $version)) {
            $version = ltrim($version, "v");
        }
        return "{$repoName}-{$version}";
    }

    protected function downloadArchive(string $archiveUrl, string $archivePath)
    {
        $file = fopen($archivePath, "w");

        $this->getHttpClient()->get($archiveUrl, [
            "sink" => $file,
        ]);
    }

    protected function extractArchive(string $archivePath, string $dest, string $rootDir)
    {
        $repoName = basename($dest);
        $dest = dirname($dest);

        Helper::createDirectoryIfNotExists(dirname($dest));
        $phar = new PharData($archivePath);
        $phar->extractTo($dest);

        rename("{$dest}/{$rootDir}", "{$dest}/{$repoName}");
    }

    protected function isUrlExists(string $url)
    {
        try {
            $response = $this->getHttpClient()->head($url);
            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
            return false;
        }
    }

    protected function removeDirIfExists(string $path)
    {
        if (is_dir($path)) {
            Helper::removeDir($path);
        }
    }

    protected function getHttpClient()
    {
        if (!$this->client) {
            $this->client = new Client();
        }
        return $this->client;
    }
}
