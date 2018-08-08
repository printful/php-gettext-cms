<?php


namespace Printful\GettextCms;


use Printful\GettextCms\Exceptions\GettextCmsException;
use Printful\GettextCms\Interfaces\MessageConfigInterface;
use Printful\GettextCms\Structures\ScanItem;

class MessageImporter
{
    /** @var MessageExtractor */
    private $extractor;

    /** @var MessageStorage */
    private $storage;

    /** @var MessageConfigInterface */
    private $config;

    public function __construct(MessageConfigInterface $config, MessageStorage $storage, MessageExtractor $extractor)
    {
        $this->extractor = $extractor;
        $this->storage = $storage;
        $this->config = $config;
    }

    /**
     * Extract messages and save them all to repository
     * Careful! Messages for each domain that were not found in this scan will be disabled.
     *
     * @param ScanItem[] $scanItems
     * @throws GettextCmsException
     */
    public function extractAndSave(array $scanItems)
    {
        $defaultLocale = $this->config->getDefaultLocale();

        $allDomainTranslations = $this->extractor->extract($scanItems);

        foreach ($this->config->getLocales() as $locale) {
            if ($locale === $defaultLocale) {
                // We do not save the default locale, because default locale is the gettext fallback
                // if no other locale is set
                continue;
            }

            foreach ($allDomainTranslations as $translations) {
                $translations->setLanguage($locale);

                // Set all previous translations as not in files
                $this->storage->setAllAsNotInFilesAndInJs($locale, $translations->getDomain());

                $this->storage->createOrUpdate($translations);
            }
        }

        $this->storage->disableUnused();
    }
}