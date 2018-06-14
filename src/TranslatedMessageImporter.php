<?php

namespace Printful\GettextCms;

use Gettext\Translations;
use Printful\GettextCms\Exceptions\InvalidTranslationException;
use Printful\GettextCms\Exceptions\UnsupportedDomainException;
use Printful\GettextCms\Exceptions\UnsupportedLocaleException;
use Printful\GettextCms\Interfaces\MessageConfigInterface;

/**
 * Class used for importing translated messages
 */
class TranslatedMessageImporter
{
    /** @var MessageStorage */
    private $storage;

    /** @var MessageConfigInterface */
    private $config;

    public function __construct(MessageConfigInterface $config, MessageStorage $storage)
    {
        $this->storage = $storage;
        $this->config = $config;
    }

    /**
     * Import translations from translation object
     *
     * @param Translations $translations
     * @throws UnsupportedLocaleException
     * @throws UnsupportedDomainException
     * @throws InvalidTranslationException
     */
    public function importFromTranslations(Translations $translations)
    {
        $locale = $translations->getLanguage();
        $domain = $translations->getDomain();

        if (!in_array($locale, $this->config->getLocales())) {
            throw new UnsupportedLocaleException('Locale ' . $locale . ' was not found in config');
        }

        if (!in_array($domain, $this->config->getOtherDomains()) && $domain !== $this->config->getDefaultDomain()) {
            throw new UnsupportedDomainException('Domain ' . $domain . ' was not found in config');
        }

        $this->storage->saveTranslated($translations);
    }

    /**
     * Import Translations from PO string
     *
     * @param string $po
     * @throws InvalidTranslationException
     * @throws UnsupportedDomainException
     * @throws UnsupportedLocaleException
     */
    public function importFromPo(string $po)
    {
        $this->importFromTranslations(Translations::fromPoString($po));
    }
}