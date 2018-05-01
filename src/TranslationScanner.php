<?php


namespace Printful\GettextCms;


use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use League\Flysystem\Plugin\ListFiles;
use Printful\GettextCms\Exceptions\InvalidPathException;
use Printful\GettextCms\Structures\ScanItem;

class TranslationScanner
{
    /**
     * @param ScanItem[] $items
     */
    public function scan(array $items)
    {
        $pathnames = $this->resolveFiles($items);

        // TODO scan files
    }

    /**
     * @param ScanItem[] $items
     * @return array Pathnames to matching files
     */
    public function resolveFiles(array $items): array
    {
        return array_reduce($items, function (&$carry, ScanItem $item) {
            $carry = array_merge($carry, $this->resolveSingleItemFiles($item));
            return $carry;
        }, []);
    }

    /**
     * Returns a list of files that match this item
     *
     * @param ScanItem $item
     * @return array List of matching pathnames
     * @throws InvalidPathException
     */
    private function resolveSingleItemFiles(ScanItem $item): array
    {
        if (is_file($item->path)) {
            return [$item->path];
        }

        if (!is_dir($item->path)) {
            throw new InvalidPathException('Path "' . $item->path . '" does not exist');
        }

        return $this->resolveDirectoryFiles($item);
    }

    private function resolveDirectoryFiles(ScanItem $item)
    {
        $dir = realpath($item->path);

        $adapter = new Local($dir);
        $filesystem = new Filesystem($adapter);
        $filesystem->addPlugin(new ListFiles);

        $files = $filesystem->listFiles('', true);

        // If extensions are set, filter other files out
        if ($item->extensions) {
            $files = array_filter($files, function ($file) use ($item) {
                return in_array($file['extension'], $item->extensions);
            });
        }

        return array_map(function ($file) use ($dir) {
            return $dir . DIRECTORY_SEPARATOR . $file['path'];
        }, $files);
    }
}