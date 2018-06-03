<?php

namespace Printful\GettextCms;

use Gettext\Translation;
use Gettext\Translations;
use Printful\GettextCms\Exceptions\InvalidPathException;
use Printful\GettextCms\Interfaces\MessageConfigInterface;
use Printful\GettextCms\Structures\RevisionItem;

class MessageRevisions
{
    /** @var MessageConfigInterface */
    private $config;

    /**
     * Cached instance so we don't read the file each time
     * @var RevisionItem
     */
    private $revisionCache = null;

    public function __construct(MessageConfigInterface $config)
    {
        $this->config = $config;
    }

    /**
     * Get revisioned domain or the original domain name if revision does not exist for this domain
     *
     * @param string $locale
     * @param string $domain
     * @return string
     */
    public function getRevisionedDomain(string $locale, string $domain): string
    {
        if (!$this->revisionCache) {
            $this->revisionCache = $this->readFromFile();
        }

        $revisionedDomain = $this->revisionCache->getRevisionedDomain($locale, $domain);

        return $revisionedDomain ?: $domain;
    }

    /**
     * From given translations, create a versioned domain name "domain_XXXXXX"
     *
     * @param string $domain
     * @param Translations $translations
     * @return string
     */
    public function generateRevisionedDomain(string $domain, Translations $translations): string
    {
        return $domain . '-' . $this->generateHash($translations);
    }

    private function generateHash(Translations $translations): string
    {
        $array = array_map(function (Translation $t) {
            return [
                $t->getOriginal(),
                $t->getPlural(),
                $t->getTranslation(),
                $t->getPluralTranslations(),
                $t->getContext(),
            ];
        }, (array)$translations);

        return substr(md5(serialize($array)), 0, 6); // Take only 6, and pray for no collisions
    }

    /**
     * Store in revision file that a new revision was created
     *
     * @param string $locale
     * @param string $originalDomain
     * @param string $revisionedDomain
     * @return bool
     * @throws InvalidPathException
     */
    public function saveRevision(string $locale, string $originalDomain, string $revisionedDomain): bool
    {
        $revisions = $this->readFromFile();

        $previousRevision = $revisions->getRevisionedDomain($locale, $originalDomain);

        if ($previousRevision === $revisionedDomain) {
            // No need to save, because it's the same revision
            return true;
        }

        $revisions->setRevisionedDomain($locale, $originalDomain, $revisionedDomain);

        return $this->writeToFile($revisions);
    }

    /**
     * Read revision data from file
     *
     * @return RevisionItem
     */
    private function readFromFile(): RevisionItem
    {
        $pathname = $this->getPathname();

        if (is_file($pathname)) {
            return RevisionItem::fromArray(json_decode(file_get_contents($pathname), true));
        }

        return new RevisionItem;
    }

    /**
     * Write revision data to json file
     *
     * @param Structures\RevisionItem $revisions
     * @return bool
     * @throws InvalidPathException
     */
    private function writeToFile(RevisionItem $revisions): bool
    {
        // Update the cached version
        $this->revisionCache = $revisions;

        $pathname = $this->getPathname();

        if (!is_dir(dirname($pathname))) {
            throw new InvalidPathException('Directory does not exist (' . $this->config->getMoDirectory() . ')');
        }

        return file_put_contents($pathname, json_encode($revisions->toArray(), JSON_PRETTY_PRINT));
    }

    /**
     * Full path to revision file
     *
     * @return string
     */
    private function getPathname(): string
    {
        return rtrim($this->config->getMoDirectory(), '/') . '/' . 'revisions.json';
    }
}