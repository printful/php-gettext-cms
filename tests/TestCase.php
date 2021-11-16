<?php

namespace Printful\GettextCms\Tests;

use FilesystemIterator;
use Mockery;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * Delete this directory with all files in it
     *
     * @param $path
     */
    protected function deleteDirectory($path)
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($path);
    }

    protected function getDummyFile(string $filename = 'dummy-file.php', string $directory = 'dummy-directory'): string
    {
        return __DIR__ . '/assets/' . $directory . '/' . $filename;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}