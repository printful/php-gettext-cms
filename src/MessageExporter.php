<?php

namespace Printful\GettextCms;

use Gettext\Generators\Mo;
use Printful\GettextCms\Exceptions\InvalidPathException;
use Printful\GettextCms\Interfaces\MessageConfigInterface;

/**
 * Class allows to export translations from repository to a generated .mo file which is used with gettext
 */
class MessageExporter
{
    /** @var MessageConfigInterface */
    private $config;

    /** @var MessageStorage */
    private $storage;

    public function __construct(
        MessageConfigInterface $config,
        MessageStorage $storage
    ) {
        $this->config = $config;
        $this->storage = $storage;
    }

    /**
     * @param string $locale
     * @param string $domain
     * @return bool
     * @throws InvalidPathException
     */
    public function export(string $locale, string $domain)
    {
        $translations = $this->storage->getAllTranslations($locale, $domain);

        $pathname = $this->getMoPathname($locale, $domain);

        return Mo::toFile($translations, $pathname);
    }

    /**
     * @param string $locale
     * @param string $domain
     * @return string
     * @throws InvalidPathException
     */
    private function getMoPathname(string $locale, string $domain)
    {
        return $this->ensureDirectory($locale) . '/' . $domain . '.mo';
    }

    /**
     * @param string $locale
     * @return string
     * @throws InvalidPathException
     */
    private function ensureDirectory(string $locale)
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