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

        $pathname = $this->getMoPathname($locale, $revisionedDomain ?: $domain);

        $wasSaved = Mo::toFile($translations, $pathname);

        if($wasSaved && $revisionedDomain){
            $this->revisions->saveRevision($locale, $domain, $revisionedDomain);
        }

        return $wasSaved;
    }

    /**
     * @param string $locale
     * @param string $domain
     * @return string
     * @throws InvalidPathException
     */
    private function getMoPathname(string $locale, string $domain): string
    {
        return $this->ensureDirectory($locale) . '/' . $domain . '.mo';
    }

    /**
     * @param string $locale
     * @return string
     * @throws InvalidPathException
     */
    private function ensureDirectory(string $locale): string
    {
        $dirPath = rtrim($this->config->getMoDirectory(), '/');

        if (!is_dir($dirPath)) {
            throw new InvalidPathException("Directory '$dirPath' does not exist");
        }

        $path = $dirPath . '/' . $locale . '/LC_MESSAGES';

        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        return $path;
    }
}