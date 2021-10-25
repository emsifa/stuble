<?php

namespace Emsifa\Stuble\Commands;

use Emsifa\Stuble\Helper;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use PharData;
use Symfony\Component\Console\Input\InputArgument;

class Get extends StubleCommand
{
    protected string $name = 'get';
    protected string $description = 'Download stub files from github';
    protected string $help = '';

    protected array $args = [
        'repository' => [
            'type' => InputArgument::REQUIRED,
            'description' => '"username/repository" github',
        ],
    ];

    protected array $options = [];

    /**
     * @var Client
     */
    protected Client|null $client = null;

    /**
     * @inheritdoc
     */
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

    /**
     * Validating repository to ensure repository is valid repository name
     */
    protected function ensureRepositoryValid(string $repository)
    {
        if (! preg_match("/^([a-zA-z0-9\._-]+)\/([a-zA-z0-9\._-]+)(\@[a-zA-z0-9\._-]+)?$/", $repository)) {
            $this->error("Invalid repository name: $repository");
            exit;
        }
    }

    /**
     * Validating repository to ensure repository is exists on github
     */
    protected function ensureRepositoryExists(string $repository)
    {
        $url = $this->getRepositoryUrl($repository);
        if (! $this->isUrlExists($url)) {
            $this->error("Repository '{$repository}' doesn't exists.");
            exit;
        }
    }

    /**
     * Validating repository version to ensure release name is exists
     */
    protected function ensureVersionExists(string $repository, string $version)
    {
        $url = $this->getVersionUrl($repository, $version);
        if (! $this->isUrlExists($url)) {
            $this->error("Repository '{$repository}' doesn't have release '{$version}'.");
            exit;
        }
    }

    /**
     * Extract version from repository name
     *
     * @return [string, string]
     */
    protected function extractVersion(string $repository): array
    {
        $split = explode("@", $repository);

        return count($split) > 1 ? [$split[0], $split[1]] : [$repository, "master"];
    }

    /**
     * Get temporary archive file name
     *
     * @param  string $repository
     * @param  string $version
     * @return string
     */
    protected function getArchiveFilename(string $repository, string $version): string
    {
        return ".tmp_".str_replace("/", "_", $repository)."@{$version}.acrhive";
    }

    /**
     * Get downloaded file destination
     *
     * @param  string $repository
     * @return string
     */
    protected function getDownloadPath(string $repository): string
    {
        return $this->getWorkingPath()."/stubs/{$repository}";
    }

    /**
     * Get GitHub repository URL
     *
     * @param  string $repository
     * @return string
     */
    protected function getRepositoryUrl(string $repository): string
    {
        return "https://github.com/{$repository}";
    }

    /**
     * Get GitHub archive (.tar.gz) URL
     *
     * @param  string $repository
     * @param  string $version
     * @return string
     */
    protected function getArchiveUrl(string $repository, string $version): string
    {
        return "https://github.com/{$repository}/archive/{$version}.tar.gz";
    }

    /**
     * Get GitHub tree version URL
     *
     * @param  string $repository
     * @param  string $version
     * @return string
     */
    protected function getVersionUrl(string $repository, string $version): string
    {
        return "https://github.com/{$repository}/tree/{$version}";
    }

    /**
     * Extract "repository-name" from "username/repository-name"
     *
     * @param  string $repository
     * @return string
     */
    protected function getRepoName(string $repository): string
    {
        return explode("/", $repository)[1];
    }

    /**
     * Get downloaded archive root directory name
     *
     * @param  string $repository
     * @param  string $version
     * @return string
     */
    protected function getArchiveRootDir(string $repository, string $version): string
    {
        $repoName = $this->getRepoName($repository);
        if (preg_match("/^v\d+(\.\d+(\.\d+)?)?$/", $version)) {
            $version = ltrim($version, "v");
        }

        return "{$repoName}-{$version}";
    }

    /**
     * Download archive file from GitHub
     *
     * @param  string $archiveUrl
     * @param  string $archivePath
     * @return void
     */
    protected function downloadArchive(string $archiveUrl, string $archivePath): void
    {
        $file = fopen($archivePath, "w");

        $this->getHttpClient()->get($archiveUrl, [
            "sink" => $file,
        ]);
    }

    /**
     * Extract archive (.tar.gz) file
     *
     * @param  string $archivePath
     * @param  string $dest
     * @param  string $rootDir
     * @return void
     */
    protected function extractArchive(string $archivePath, string $dest, string $rootDir): void
    {
        $repoName = basename($dest);
        $dest = dirname($dest);

        Helper::createDirectoryIfNotExists(dirname($dest));
        $phar = new PharData($archivePath);
        $phar->extractTo($dest);

        rename("{$dest}/{$rootDir}", "{$dest}/{$repoName}");
    }

    /**
     * Call HTTP request to make sure given URL is exists
     *
     * @param  string $url
     * @return bool
     */
    protected function isUrlExists(string $url): bool
    {
        try {
            $response = $this->getHttpClient()->head($url);

            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
            return false;
        }
    }

    /**
     * Remove whole directory if exists
     *
     * @param  string $path
     * @return void
     */
    protected function removeDirIfExists(string $path): void
    {
        if (is_dir($path)) {
            Helper::removeDir($path);
        }
    }

    /**
     * Get or make Guzzle Client instance
     *
     * @return Client
     */
    protected function getHttpClient(): Client
    {
        if (! $this->client) {
            $this->client = new Client();
        }

        return $this->client;
    }
}
