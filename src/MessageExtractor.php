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
use Printful\GettextCms\Interfaces\MessageConfigInterface;
use Printful\GettextCms\Structures\ScanItem;

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
     * @return Translations[] List of translation files extracted (for each domain)
     * @throws GettextCmsException
     */
    public function extract(array $items)
    {
        $defaultDomain = $this->config->getDefaultDomain();
        $domains = $this->config->getOtherDomains();
        $domains[] = $defaultDomain;

        $allTranslations = [];

        foreach ($domains as $domain) {
            $translations = new Translations;

            // When we scan for default domain, we have to specify an empty value
            // otherwise we would search for domain function calls with this domain
            // For example, empty domain will find string like __("message")
            // But if a domain is specified, it will look for dgettext("custom-domain", "message")
            if ($domain !== $defaultDomain) {
                $translations->setDomain($domain);
            }

            foreach ($items as $item) {
                // Scan for this item, translations will be merged with all domain translations
                $this->extractForDomain($item, $translations);
            }

            // Always set the domain even if it is the default one
            $translations->setDomain($domain);

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