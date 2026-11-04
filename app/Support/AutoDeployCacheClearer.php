<?php

namespace App\Support;

final class AutoDeployCacheClearer
{
    public static function clearWhenGitHeadChanges(string $basePath): void
    {
        $head = self::currentGitHead($basePath);

        if ($head === null) {
            return;
        }

        $marker = $basePath.'/bootstrap/cache/.last_deploy_head';
        $previousHead = is_file($marker) ? trim((string) @file_get_contents($marker)) : null;

        if ($previousHead === $head) {
            return;
        }

        self::clearBootstrapCache($basePath);
        self::clearFileCache($basePath);

        @file_put_contents($marker, $head);
    }

    private static function currentGitHead(string $basePath): ?string
    {
        $headFile = $basePath.'/.git/HEAD';

        if (! is_file($headFile)) {
            return null;
        }

        $head = trim((string) @file_get_contents($headFile));

        if ($head === '') {
            return null;
        }

        if (! str_starts_with($head, 'ref: ')) {
            return $head;
        }

        $refFile = $basePath.'/.git/'.trim(substr($head, 5));

        if (! is_file($refFile)) {
            return null;
        }

        $ref = trim((string) @file_get_contents($refFile));

        return $ref === '' ? null : $ref;
    }

    private static function clearBootstrapCache(string $basePath): void
    {
        $cachePath = $basePath.'/bootstrap/cache';
        $files = [
            $cachePath.'/config.php',
            $cachePath.'/events.php',
            $cachePath.'/routes.php',
            $cachePath.'/views.php',
        ];

        foreach (glob($cachePath.'/routes-*.php') ?: [] as $routeCacheFile) {
            $files[] = $routeCacheFile;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private static function clearFileCache(string $basePath): void
    {
        self::deleteDirectoryContents($basePath.'/storage/framework/cache/data');

        foreach (glob($basePath.'/storage/framework/views/*.php') ?: [] as $viewFile) {
            @unlink($viewFile);
        }
    }

    private static function deleteDirectoryContents(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..' || $item === '.gitignore') {
                continue;
            }

            $fullPath = $path.'/'.$item;

            if (is_dir($fullPath)) {
                self::deleteDirectoryContents($fullPath);
                @rmdir($fullPath);

                continue;
            }

            @unlink($fullPath);
        }
    }
}
