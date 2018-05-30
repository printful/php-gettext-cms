<?php

namespace Printful\GettextCms\Interfaces;

interface MessageConfigInterface
{
    /**
     * List of locales you are supporting.
     * Format is en_US, lv_LV, ru_RU, etc.
     * 
     * @see https://www.gnu.org/software/gettext/manual/html_node/Locale-Names.html
     *
     * @return string[]
     */
    public function getLocales(): array;

    /**
     * Repository that will be used to store translations
     *
     * @return MessageRepositoryInterface
     */
    public function getRepository(): MessageRepositoryInterface;

    /**
     * Domain name that will be bound as the default domain for gettext function usage (without specifying a domain)
     *
     * @return string
     */
    public function getDefaultDomain(): string;

    /**
     * List of other domain names that will be used and which messages to scan for
     *
     * @return string[]
     */
    public function getOtherDomains(): array;

    /**
     * Absolute directory path where .mo translation files will be exported
     *
     * @return string
     */
    public function getMoDirectory(): string;

    /**
     * Indicates if we should add version to domain files when generating them.
     * This is one of the ways how to solve gettext caching - we include a revision in domain
     * file name, so gettext assumes it is a different domain and loads it in the memory.
     * The previously loaded domain will still remain in memory and will eventually drop out of it.
     *
     * Be careful not to fill your servers memory!
     * The safest way is to reload server and avoid renaming the the domain files.
     *
     * @return bool
     */
    public function useRevisions(): bool;
}