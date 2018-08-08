<?php

namespace Printful\GettextCms\Interfaces;

use Printful\GettextCms\Structures\MessageItem;

interface MessageRepositoryInterface
{
    /**
     * Store a message in repository (update an existing message)
     *
     * @param MessageItem $item
     * @return bool
     */
    public function save(MessageItem $item): bool;

    /**
     * Find an existing translation item by given item key.
     * If message item is not found, return an empty item object
     *
     * @param string $key
     * @return MessageItem
     */
    public function getSingle($key): MessageItem;

    /**
     * Retrieve list of all messages (disabled and enabled, untranslated)
     * Includes untranslated messages
     *
     * @param string $locale
     * @param string $domain
     * @return MessageItem[]
     */
    public function getAll(string $locale, string $domain): array;

    /**
     * Retrieve list of enabled messages (including untranslated)
     *
     * @param string $locale
     * @param string $domain
     * @return MessageItem[]
     */
    public function getEnabled(string $locale, string $domain): array;

    /**
     * Retrieve list of enabled and translated messages
     *
     * @param string $locale
     * @param string $domain
     * @return MessageItem[]
     */
    public function getEnabledTranslated(string $locale, string $domain): array;

    /**
     * Retrieve list of enabled and translated messages that are in JS files
     *
     * @param string $locale
     * @param string $domain
     * @return MessageItem[]
     */
    public function getEnabledTranslatedJs(string $locale, string $domain): array;

    /**
     * List of messages that require to be translated (enabled and are marked as needs-checking)
     *
     * @param string $locale
     * @param string $domain
     * @return MessageItem[]
     */
    public function getRequiresTranslating(string $locale, string $domain): array;

    /**
     * Set all locale and domain messages as not present in files (isFile field should be set to false)
     *
     * @param string $locale
     * @param string $domain
     */
    public function setAllAsNotInFilesAndInJs(string $locale, string $domain);

    /**
     * Set all locale and domain messages as not dynamic (isDynamic field should be set to false)
     *
     * @param string $locale
     * @param string $domain
     */
    public function setAllAsNotDynamic(string $locale, string $domain);

    /**
     * Set all messages as disabled which are not used (messages which filed "isDynamic", "isInFile" and "isInJs" all are false)
     */
    public function disableUnused();
}