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
        if ($defaultDomain) {
            // If we search for default domain, add an empty domain string
            $otherDomains[] = '';
        }

        $allTranslations = [];

        foreach ($otherDomains as $domain) {
            $translations = new Translations;
            $translations->setDomain($domain);

            foreach ($items as $item) {
                // Scan for this item, translations will be merged with all domain translations
                $this->extractForDomain($item, $translations);
            }
            $allTranslations[] = $translations;
        }

        return $allTranslations;
    }

    /**
     * @param ScanItem $scanItem
     * @param Translations $translations
     * @return Translations
     * @throws InvalidPathException
     * @throws UnknownExtractorException
     */
    private function extractForDomain(ScanItem $scanItem, Translations $translations)
    {
        $pathnames = $this->resolvePathnames($scanItem);

        foreach ($pathnames as $pathname) {
            // Translations will be merged with given object
            $this->extractFileMessages($pathname, $translations, $scanItem->functions);
        }

        return $translations;
    }

    /**
     * @param $pathname
     * @param Translations $translations
     * @param array|null $functions Optional functions to scan for
     * @throws UnknownExtractorException
     */
    private function extractFileMessages($pathname, Translations $translations, array $functions = null)
    {
        $extractor = $this->getExtractor($pathname);

        $options = [
            'extractComments' => '', // This extracts comments above function call
            'domainOnly' => $translations->hasDomain(), // We scan for messages that match our needed domain only
        ];

        if ($functions) {
            $options['functions'] = $functions;
        }

        $extractor::fromFile($pathname, $translations, $options);
    }

    /**
     * Returns a list of files that match this item
     *
     * @param ScanItem $item
     * @return array List of matching pathnames
     * @throws InvalidPathException
     */
    public function resolvePathnames(ScanItem $item): array
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