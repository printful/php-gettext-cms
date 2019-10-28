<?php


namespace Printful\GettextCms\Structures;


/**
 * Structure holds revision data for domain for easier array manipulations
 */
class RevisionItem
{
    /** @var array [locale => [domain => revisioned domain], ..] */
    private $revisions = [];

    /**
     * Get a revisioned domain name
     *
     * @param string $locale
     * @param string $originalDomain
     * @return string|null
     */
    public function getRevisionedDomain(string $locale, string $originalDomain)
    {
        return $this->revisions[$locale][$originalDomain] ?? null;
    }

    /**
     * Set revisioned domain
     *
     * @param string $locale
     * @param string $originalDomain
     * @param string $revisionedDomain
     */
    public function setRevisionedDomain(string $locale, string $originalDomain, string $revisionedDomain)
    {
        $this->revisions += [$locale => []];
        $this->revisions[$locale][$originalDomain] = $revisionedDomain;
    }

    /**
     * Get revision array
     *
     * @return array
     */
    public function toArray()
    {
        return $this->revisions;
    }

    /**
     * Load revisions from an array
     *
     * @param array $revisions
     * @return RevisionItem
     */
    public static function fromArray($revisions): RevisionItem
    {
        $instance = new self();
        $instance->revisions = $revisions;

        return $instance;
    }
}