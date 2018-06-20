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
     * Export untranslated messages to a PO file string.
     * If no messages exist, empty string is returned.
     *
     * @param string $locale
     * @param string $domain
     * @return string PO file contents or empty string if nothing to export
     */
    public function exportPoString(string $locale, string $domain): string
    {
        $translations = $this->storage->getRequiresTranslating($locale, $domain);

        if (!$translations->count()) {
            return '';
        }

        return Po::toString($translations);
    }
}