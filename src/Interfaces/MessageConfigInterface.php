<?php

namespace Printful\GettextCms\Interfaces;

interface MessageConfigInterface
{
    /**
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
}