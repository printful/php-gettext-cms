<?php


namespace Printful\GettextCms;


use Gettext\Extractors\Extractor;
use Gettext\Extractors\JsCode;
use Gettext\Extractors\PhpCode;
use Gettext\Extractors\VueJs;
use Gettext\Translations;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use League\Flysystem\Plugin\ListFiles;
use Printful\GettextCms\Exceptions\GettextCmsException;
use Printful\GettextCms\Exceptions\InvalidPathException;
use Printful\GettextCms\Exceptions\UnknownExtractorException;
use Printful\GettextCms\Structures\ScanItem;

class MessageExtractor
{
    const EXTRACTORS = [
        'js' => JsCode::class,
        'vue' => VueJs::class,
        'php' => PhpCode::class,
    ];

    /**
     * @param ScanItem[] $items
     * @param bool $defaultDomain Should we scan for translations in default domain (no domain messages)
     * @param array $otherDomains List of domains we should search (excluding the default domain
     * @return Translations[] List of translation files for each domain we wanted to extract
     * @throws GettextCmsException
     */
    public function extract(array $items, bool $defaultDomain = true, array $otherDomains = [])
    {
        $pathnames = $this->resolvePathnames($items);

        if ($defaultDomain) {
            // If we search for default domain, add an empty domain string
            $otherDomains[] = '';
        }

        $translations = [];

        foreach ($otherDomains as $domain) {
            $translations[] = $this->extractForDomain($domain, $pathnames);
        }

        return $translations;
    }

    /**
     * @param string $domain
     * @param array $pathnames
     * @return Translations
     * @throws UnknownExtractorException
     */
    private function extractForDomain($domain, array $pathnames)
    {
        $translations = new Translations;
        $translations->setDomain($domain);

        foreach ($pathnames as $pathname) {
            $this->extractFileMessages($pathname, $translations);
        }

        return $translations;
    }

    /**
     * @param $pathname
     * @param Translations $translations
     * @throws UnknownExtractorException
     */
    private function extractFileMessages($pathname, Translations $translations)
    {
        $extractor = $this->getExtractor($pathname);

        $extractor::fromFile($pathname, $translations, [
            'domainOnly' => $translations->hasDomain(), // We scan for messages that match our needed domain only
        ]);
    }

    /**
     * @param ScanItem[] $items
     * @return array Pathnames to matching files
     */
    public function resolvePathnames(array $items): array
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

    /**
     * @param ScanItem $item
     * @return string[] List of pathnames to files
     */
    private function resolveDirectoryFiles(ScanItem $item)
    {
        $dir = realpath($item->path);

        $adapter = new Local($dir);
        $filesystem = new Filesystem($adapter);
        $filesystem->addPlugin(new ListFiles);

        $files = $filesystem->listFiles('', $item->recursive);

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

    /**
     * @param string $pathname Full path to file
     * @return Extractor
     * @throws UnknownExtractorException
     */
    private function getExtractor($pathname)
    {
        $extension = pathinfo($pathname, PATHINFO_EXTENSION);

        if (isset(self::EXTRACTORS[$extension])) {
            return self::EXTRACTORS[$extension];
        }

        throw new UnknownExtractorException('Extractor is not know for file extension "' . $extension . '"');
    }
}