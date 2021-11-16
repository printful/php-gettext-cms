<?php

namespace Printful\GettextCms;

use FilesystemIterator;
use Gettext\Extractors\ExtractorInterface;
use Gettext\Extractors\ExtractorMultiInterface;
use Gettext\Extractors\JsCode;
use Gettext\Extractors\PhpCode;
use Gettext\Extractors\VueJs;
use Gettext\Translations;
use Printful\GettextCms\Exceptions\GettextCmsException;
use Printful\GettextCms\Exceptions\InvalidPathException;
use Printful\GettextCms\Exceptions\UnknownExtractorException;
use Printful\GettextCms\Interfaces\MessageConfigInterface;
use Printful\GettextCms\Structures\ScanItem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Class extracts gettext function calls from source files and converts them to Translation objects
 */
class MessageExtractor
{
    const EXTRACTORS = [
        'js' => JsCode::class,
        'vue' => VueJs::class,
        'php' => PhpCode::class,
    ];

    /** @var MessageConfigInterface */
    private $config;

    /**
     * @param MessageConfigInterface $config
     */
    public function __construct(MessageConfigInterface $config)
    {
        $this->config = $config;
    }

    /**
     * @param ScanItem[] $items
     * @param array|null $domains Force domains to scan. If null, will scan default domains.
     * @return Translations[] List of translations for each domain
     * @throws GettextCmsException
     */
    public function extract(array $items, array $domains = null): array
    {
        $defaultDomain = $this->config->getDefaultDomain();

        if (!$domains) {
            $domains = $this->config->getOtherDomains();
            $domains[] = $defaultDomain;
        }

        /** @var Translations[] $allTranslations [domain => translations, ...] */
        $allTranslations = array_reduce($domains, function ($carry, string $domain) use ($defaultDomain) {
            $translations = new Translations();

            // When we scan for default domain, we have to specify an empty value
            // otherwise we would search for domain function calls with this domain
            // For example, empty domain will find string like __("message")
            // But if a domain is specified, it will look for dgettext("custom-domain", "message")
            if ($domain !== $defaultDomain) {
                $translations->setDomain($domain);
            }

            $carry[$domain] = $translations;

            return $carry;
        }, []);

        foreach ($items as $item) {
            // Scan for this item, translations will be merged with all domain translations
            $this->extractForDomains($item, array_values($allTranslations));
        }

        // Always set the domain even if it is the default one
        // This is needed because default domain won't be set for the translations instance
        foreach ($allTranslations as $domain => $translations) {
            $translations->setDomain($domain);
        }

        return array_values($allTranslations);
    }

    /**
     * @param ScanItem $scanItem
     * @param Translations[] $translations
     * @return Translations[]
     * @throws InvalidPathException
     * @throws UnknownExtractorException
     */
    private function extractForDomains(ScanItem $scanItem, array $translations): array
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
     * @param Translations[] $translations
     * @param array|null $functions Optional functions to scan for
     * @throws UnknownExtractorException
     */
    private function extractFileMessages($pathname, array $translations, array $functions = null)
    {
        $extractor = $this->getExtractor($pathname);

        $options = [
            'extractComments' => '', // This extracts comments above function call
            // HTML attribute prefixes we parse as JS which could contain translations (are JS expressions)
            'attributePrefixes' => [
                ':',
                'v-',
            ],
        ];

        if ($functions) {
            $options['functions'] = $functions;
        }

        $extractor::fromFileMultiple($pathname, $translations, $options);
    }

    /**
     * Returns a list of files that match this item (if single file, then an array with a single pathname)
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
     * If scan item is for a directory, this will create a list of matching files
     *
     * @param ScanItem $item
     * @return string[] List of pathnames to files
     */
    private function resolveDirectoryFiles(ScanItem $item): array
    {
        $dir = realpath($item->path);

        if ($item->recursive) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
            );
        } else {
            $iterator = new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS);
        }

        // If no extensions are given, fallback to known extensions
        $extensions = $item->extensions ?: array_keys(self::EXTRACTORS);

        $matchingPathnames = [];

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            $extension = strtolower($file->getExtension());

            if ($extensions && in_array($extension, $extensions)) {
                $matchingPathnames[] = $file->getRealPath();
            }
        }

        return $matchingPathnames;
    }

    /**
     * @param string $pathname Full path to file
     * @return ExtractorInterface|ExtractorMultiInterface|string Name of the extraction class for the given file
     * @throws UnknownExtractorException
     */
    private function getExtractor($pathname): string
    {
        $extension = pathinfo($pathname, PATHINFO_EXTENSION);

        if (isset(self::EXTRACTORS[$extension])) {
            return self::EXTRACTORS[$extension];
        }

        throw new UnknownExtractorException('Extractor is not know for file extension "' . $extension . '"');
    }
}