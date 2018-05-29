<?php

namespace Printful\GettextCms;

use Gettext\Generators\Po;

/**
 * Export untranslated messages to a .po file contents that can be saved for translating
 */
class UntranslatedMessageExporter
{
    /** @var MessageStorage */
    private $storage;

    public function __construct(MessageStorage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * @param string $locale
     * @param string $domain
     * @return string
     */
    public function exportToString(string $locale, string $domain): string
    {
        $translations = $this->storage->getRequiresTranslating($locale, $domain);

        return Po::toString($translations);
    }
}