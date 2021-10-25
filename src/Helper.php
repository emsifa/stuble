<?php

namespace Emsifa\Stuble;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Helper
{
    public static function createDirectoryIfNotExists($dir)
    {
        list($drive, $dir) = static::splitDriveWithPath($dir);
        $paths = explode("/", $dir);
        $path = "";
        while (count($paths)) {
            $path .= "/" . array_shift($paths);

            if (! is_dir($drive . $path)) {
                mkdir($drive . $path);
            }
        }
    }

    public static function removeDir(string $dir)
    {
        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
    }

    /**
     * Split drive and path from windows filesystem
     */
    private static function splitDriveWithPath($path)
    {
        $splitted = explode(":", $path, 2);

        return count($splitted) > 1
            ? [$splitted[0].":", str_replace("\\", "/", $splitted[1])]
            : [null, str_replace("\\", "/", $splitted[0])];
    }
}
