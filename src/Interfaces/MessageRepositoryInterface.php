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
     * Retrieve list of messages from a specific locale and domain
     *
     * @param string $locale
     * @param string $domain
     * @return MessageItem[]
     */
    public function getAll(string $locale, string $domain): array;
}