<?php

namespace Printful\GettextCms;

use Printful\GettextCms\Exceptions\GettextCmsException;
use Printful\GettextCms\Interfaces\MessageConfigInterface;
use Printful\GettextCms\Structures\ScanItem;

class MessageManager
{
    /** @var LocaleLoader */
    private $loader;

    /** @var string Current locale set */
    private $locale;

    /** @var MessageRevisions */
    private $revisions;

    /** @var self */
    private static $instance;

    /** @var MessageConfigInterface */
    private $config;

    /** @var MessageStorage */
    private $storage;

    private function __construct(MessageConfigInterface $config)
    {
        $this->revisions = new MessageRevisions($config);
        $this->loader = new LocaleLoader($config, new MessageRevisions($config));
        $this->config = $config;
        $this->storage = new MessageStorage($this->config->getRepository());

        if ($config->useShortFunctions()) {
            $this->declareFunctions();
        }
    }

    /**
     * Initialize singleton instance
     * Singleton is necessary for short functions because they need to access the revision functionality globally.
     *
     * @param MessageConfigInterface $config
     * @return MessageManager
     */
    public static function init(MessageConfigInterface $config): self
    {
        self::$instance = new static($config);

        return self::$instance;
    }

    /**
     * @return MessageManager
     */
    public static function getInstance(): self
    {
        return self::$instance;
    }

    /**
     * @param string $locale
     * @return MessageManager
     * @throws GettextCmsException
     */
    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        $this->loader->load($locale);

        return $this;
    }

    /**
     * Declare helper functions for easier gettext usage
     *
     * @see _n()
     * @see _d()
     * @see _x()
     * @see _dn()
     * @see _dx()
     * @see _dxn()
     *
     * @return MessageManager
     */
    private function declareFunctions(): self
    {
        require __DIR__ . '/helpers/functions.php';

        return $this;
    }

    /**
     * Get revisioned domain for current locale or the original domain name if revision does not exist for this domain
     *
     * @param string $domain
     * @return string
     */
    public function getRevisionedDomain(string $domain): string
    {
        return $this->revisions->getRevisionedDomain($this->locale, $domain);
    }

    /**
     * Extract translations from files and save them to repository
     * Careful, previous translations will be disabled, so everything has to be scanned at once
     *
     * @param ScanItem[] $scanItems
     * @throws GettextCmsException
     */
    public function extractAndSaveFromFiles(array $scanItems)
    {
        if ($this->config->useShortFunctions()) {
            // If using short functions, we need to pre-fill them
            // so they are extracted too
            $functions = $this->getFunctions();

            foreach ($scanItems as $scanItem) {
                $scanItem->functions = $scanItem->functions ?: [];
                $scanItem->functions += $functions;
            }
        }

        $importer = new MessageImporter(
            $this->config,
            $this->storage,
            new MessageExtractor($this->config)
        );

        $importer->extractAndSave($scanItems);
    }

    /**
     * Export PO string of untranslated messages
     *
     * @param string $locale
     * @param string $domain
     * @return string
     */
    public function exportUntranslatedPo(string $locale, string $domain): string
    {
        $exporter = new UntranslatedMessageExporter($this->storage);

        return $exporter->exportPoString($locale, $domain);
    }

    /**
     * Create a zip archive with messages that require translations as PO files
     *
     * @param string $zipPathname Full pathname to the file where the ZIP archive should be written
     * @param string $locale
     * @param string[]|null $domains Domains to export. If not provided, export all domains defined in config
     * @return bool
     */
    public function exportUntranslatedPoZip(string $zipPathname, string $locale, array $domains = null): bool
    {
        $exporter = new UntranslatedMessageZipExporter(
            $this->config,
            new UntranslatedMessageExporter($this->storage)
        );

        return $exporter->export($zipPathname, $locale, $domains);
    }

    /**
     * Import translated messages from PO file
     *
     * @param string $poContent
     * @throws GettextCmsException
     */
    public function importTranslated($poContent)
    {
        $importer = new TranslatedMessageImporter($this->config, $this->storage);
        $importer->importFromPo($poContent);
    }

    /**
     * Build MO translation files for each locale and domain
     *
     * @throws GettextCmsException
     */
    public function buildTranslationFiles()
    {
        $builder = new MessageBuilder($this->config, $this->storage, $this->revisions);

        foreach ($this->config->getLocales() as $locale) {
            $domains = $this->config->getOtherDomains();
            $domains[] = $this->config->getDefaultDomain();

            foreach ($domains as $domain) {
                $builder->export($locale, $domain);
            }
        }
    }

    /**
     * Get the dynamic importer for adding dynamic messages
     *
     * @return DynamicMessageImporter
     */
    public function getDynamicMessageImporter(): DynamicMessageImporter
    {
        return new DynamicMessageImporter($this->config, $this->storage);
    }

    /**
     * Functions to scan for including (default functions + custom functions)
     *
     * @return array
     */
    private function getFunctions(): array
    {
        return [
            // Default functions
            '_' => 'gettext',
            'gettext' => 'gettext',
            'ngettext' => 'ngettext',
            'pgettext' => 'pgettext',
            'dgettext' => 'dgettext',
            'dngettext' => 'dngettext',
            'dpgettext' => 'dpgettext',
            'npgettext' => 'npgettext',
            'dnpgettext' => 'dnpgettext',

            // Our custom functions
            '_n' => 'ngettext',
            '_nc' => 'npgettext',
            '_c' => 'pgettext',
            '_dc' => 'dpgettext',
            '_d' => 'dgettext',
            '_dn' => 'dngettext',
            '_dnc' => 'dnpgettext',
        ];
    }
}