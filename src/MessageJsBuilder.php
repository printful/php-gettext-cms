<?php

namespace Printful\GettextCms;

use Gettext\Generators\Jed;

/**
 * Class exports JS translations to JSONP callback files in JED translation format
 */
class MessageJsBuilder
{
    /** @var MessageStorage */
    private $storage;

    public function __construct(MessageStorage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Export one or multiple domains in a single JS file with JSONP callback that will load JED format translations
     *
     * @param string $locale
     * @param string[] $domains
     * @param string $jsonpCallback Function name, that will be called with domain translations
     * @return string
     */
    public function exportJsonp(string $locale, array $domains, string $jsonpCallback)
    {
        $js = '';

        foreach ($domains as $domain) {
            $translations = $this->storage->getEnabledTranslatedJs($locale, $domain);

            if (!count($translations)) {
                continue;
            }

            $jed = Jed::toString($translations);

            $js .= $jsonpCallback . '(' . $jed . ");\n";
        }

        return $js;
    }
}