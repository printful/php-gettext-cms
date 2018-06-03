<?php

namespace Printful\GettextCms;

use Gettext\Generators\Mo;
use Printful\GettextCms\Exceptions\InvalidPathException;
use Printful\GettextCms\Interfaces\MessageConfigInterface;

/**
 * Class allows to export translations from repository to a generated .mo file which is used with gettext
 */
class MessageBuilder
{
    /** @var MessageConfigInterface */
    private $config;

    /** @var MessageStorage */
    private $storage;

    /** @var MessageRevisions */
    private $revisions;

    public function __construct(
        MessageConfigInterface $config,
        MessageStorage $storage,
        MessageRevisions $revisions
    ) {
        $this->config = $config;
        $this->storage = $storage;
        $this->revisions = $revisions;
    }

    /**
     * @param string $locale
     * @param string $domain
     * @return bool
     * @throws InvalidPathException
     */
    public function export(string $locale, string $domain): bool
    {
        $translations = $this->storage->getEnabledTranslated($locale, $domain);

        $revisionedDomain = null;
        if ($this->config->useRevisions()) {
            $revisionedDomain = $this->revisions->generateRevisionedDomain($domain, $translations);
        }

        $moPathname = $this->ensureDirectoryAndGetMoPathname($locale, $revisionedDomain ?: $domain);

        $wasSaved = Mo::toFile($translations, $moPathname);

        if ($wasSaved && $revisionedDomain) {
            $previousRevisionedDomain = $this->revisions->getRevisionedDomain($locale, $domain);

            $wasRevisionSaved = $this->revisions->saveRevision($locale, $domain, $revisionedDomain);

            if ($wasRevisionSaved) {
                $this->removePreviousRevisionedDomain($locale, $revisionedDomain, $previousRevisionedDomain);
            }
        }

        return $wasSaved;
    }

    /**
     * Remove previous domain file
     *
     * @param string $locale
     * @param string $currentDomain
     * @param string $previousDomain
     * @return bool True if cleanup was successful
     */
    private function removePreviousRevisionedDomain(
        string $locale,
        string $currentDomain,
        string $previousDomain
    ): bool {
        if ($currentDomain === $previousDomain) {
            // Same revisioned domain, no need to remove
            return true;
        }

        $previousPathname = $this->getMoPathname($locale, $previousDomain);

        if (is_file($previousPathname)) {
            return @unlink($previousPathname);
        }

        return false;
    }

    /**
     * @param string $locale
     * @param string $domain
     * @return string
     * @throws InvalidPathException
     */
    private function ensureDirectoryAndGetMoPathname(string $locale, string $domain): string
    {
        $baseDir = rtrim($this->config->getMoDirectory(), '/');
        $pathname = $this->getMoPathname($locale, $domain);

        if (!is_dir($baseDir)) {
            throw new InvalidPathException("Directory '$baseDir' does not exist");
        }

        $localeDir = dirname($pathname);

        if (!is_dir($localeDir)) {
            // Create the {baseDir}/{locale}/LC_MESSAGES directory
            mkdir($localeDir, 0777, true);
        }

        return $pathname;
    }

    /**
     * Get full pathname to mo file
     *
     * @param string $locale
     * @param string $domain
     * @return string Full pathname to mo file
     */
    private function getMoPathname($locale, $domain): string
    {
        return rtrim($this->config->getMoDirectory(), '/') . '/' . $locale . '/LC_MESSAGES/' . $domain . '.mo';
    }
}