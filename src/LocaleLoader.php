<?php

namespace Printful\GettextCms;

use Printful\GettextCms\Exceptions\UnsupportedLocaleException;
use Printful\GettextCms\Interfaces\MessageConfigInterface;

/**
 * Class sets locales and binds domains for current request
 */
class LocaleLoader
{
    /**
     * Available categories are defined here
     * @url https://www.php.net/manual/en/function.setlocale.php
     */
    const LC_CATEGORIES_TO_OVERRIDE = [
        LC_MESSAGES,
        LC_TIME,
    ];

    /** @var MessageConfigInterface */
    private $config;

    /** @var MessageRevisions */
    private $revisions;

    public function __construct(MessageConfigInterface $config, MessageRevisions $revisions)
    {
        $this->config = $config;
        $this->revisions = $revisions;
    }

    /**
     * Globally set current locale and bind gettext domains
     *
     * @param string $locale
     * @return bool If false is returned, some domains may not have been bound and will fail
     * @throws UnsupportedLocaleException
     */
    public function load(string $locale): bool
    {
        putenv("LANG=" . $locale);

        // Some systems use LANGUAGE variant above LANG, so we just set both of them, just in case.
        putenv("LANGUAGE=" . $locale);

        if (!$this->setLocale($locale)) {
            throw new UnsupportedLocaleException('Locale is not supported by your system: ' . $locale);
        }

        return $this->bindDomains($locale);
    }

    private function setLocale($locale): bool
    {
        // Some locales contain utf postfix, so we should try them all
        $locales = [
            $locale,
            $locale . '.utf8',
            $locale . '.UTF-8',
        ];

        foreach ($locales as $v) {
            foreach (self::LC_CATEGORIES_TO_OVERRIDE as $lc) {
                if (!setlocale($lc, $v)) {
                    return false;
                }
                return true;
            }
        }

        return false;
    }

    /**
     * Bind all domains for locale
     *
     * @param string $locale
     * @return bool Returns false if binding fails, translations may not work correctly
     */
    private function bindDomains(string $locale): bool
    {
        $domainBound = true;

        $domainDir = rtrim($this->config->getMoDirectory(), '/');

        $defaultDomain = $this->config->getDefaultDomain();

        $allDomains = $this->config->getOtherDomains();
        $allDomains[] = $defaultDomain;

        foreach ($allDomains as $domain) {
            $actualDomain = $this->getActualDomain($locale, $domain);

            // File structure for a translation is: mo-directory/{locale}/LC_MESSAGES/{domain}.mo
            if (!bindtextdomain($actualDomain, $domainDir)) {
                // If text domain binding fails, something is wrong with the paths
                $domainBound = false;
            }

            bind_textdomain_codeset($actualDomain, 'utf8');
        }

        // Bind the default domain for _() calls (and other non-domain specific calls)
        textdomain($this->getActualDomain($locale, $defaultDomain));

        return $domainBound;
    }

    /**
     * Get actual domain name. If revisions are enabled, this will return something like "domain_XXXXXX"
     *
     * @param string $locale
     * @param string $domain
     * @return string
     */
    private function getActualDomain(string $locale, string $domain): string
    {
        if ($this->config->useRevisions()) {
            return $this->revisions->getRevisionedDomain($locale, $domain);
        }

        return $domain;
    }
}