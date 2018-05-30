<?php

namespace Printful\GettextCms;

use Printful\GettextCms\Interfaces\MessageConfigInterface;

/**
 * Class sets locales and binds domains for current request
 */
class LocaleLoader
{
    /** @var MessageConfigInterface */
    private $config;

    /** @var MessageRevisions */
    private $revisions;

    public function __construct(MessageConfigInterface $config)
    {
        $this->revisions = new MessageRevisions($config);
        $this->config = $config;
    }

    /**
     * Globally set current locale and bind gettext domains
     *
     * @param string $locale
     * @return bool If false is returned, some domains may not have been bound and will fail
     */
    public function load(string $locale): bool
    {
        putenv("LANG=" . $locale);
        setlocale(LC_ALL, $locale);

        return $this->bindDomains($locale);
    }

    /**
     * Bind all domains for locale
     *
     * @param string $locale
     * @return bool Returns false if binding fails, translations may not work correctly
     */
    private function bindDomains(string $locale): bool
    {
        $domainBindingFailed = false;

        $domainDir = rtrim($this->config->getMoDirectory(), '/');

        $defaultDomain = $this->config->getDefaultDomain();

        $allDomains = $this->config->getOtherDomains();
        $allDomains[] = $defaultDomain;

        foreach ($allDomains as $domain) {
            $actualDomain = $this->getActualDomain($locale, $domain);

            // File structure for a translation is: mo-directory/{locale}/LC_MESSAGES/{domain}.mo
            if (!bindtextdomain($actualDomain, $domainDir)) {
                // If text domain binding fails, something is wrong with the paths
                $domainBindingFailed = true;
            }

            bind_textdomain_codeset($actualDomain, 'utf8');
        }

        // Bind the default domain for _() calls (and other non-domain specific calls)
        textdomain($this->getActualDomain($locale, $defaultDomain));

        return $domainBindingFailed;
    }

    /**
     * Get actual domain name. If revisions are enabled, this will return something like "domain_xxxxxx"
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