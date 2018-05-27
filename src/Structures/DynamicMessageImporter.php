<?php

namespace Printful\GettextCms\Structures;

use Gettext\Translation;
use Printful\GettextCms\Exceptions\MissingMessagesException;
use Printful\GettextCms\Interfaces\MessageConfigInterface;
use Printful\GettextCms\MessageStorage;

/**
 * Class allows adding dynamically generated translations which are not present in files.
 * For example, add messages from database fields which you still want to use gettext with.
 */
class DynamicMessageImporter
{
    /** @var MessageConfigInterface */
    private $config;

    /** @var MessageStorage */
    private $storage;

    /** @var array [context => [message, ..], ..] */
    private $messages = [];

    public function __construct(MessageConfigInterface $config, MessageStorage $storage)
    {
        $this->config = $config;
        $this->storage = $storage;
    }

    /**
     * Add message to the message queue to be saved.
     * Save has to be called to save all the translations to the repository
     *
     * @see \Printful\GettextCms\Structures\DynamicMessageImporter::saveAndDisabledPrevious
     *
     * @param string $original
     * @param string $context
     * @return DynamicMessageImporter
     */
    public function add(string $original, string $context = ''): DynamicMessageImporter
    {
        $this->messages += [$context => []];
        $this->messages[$context][$original] = $original;

        return $this;
    }

    /**
     * Save given messages to domain
     * All old messages will be disabled
     *
     * @param string $domain
     * @throws MissingMessagesException
     */
    public function saveAndDisabledPrevious(string $domain)
    {
        if (!$this->messages) {
            throw new MissingMessagesException('Tried to save but no messages were given');
        }

        $locales = $this->config->getLocales();

        foreach ($locales as $locale) {
            $this->storage->disableAll($locale, $domain);

            foreach ($this->messages as $context => $messages) {
                foreach ($messages as $message) {
                    $this->storage->saveSingleTranslation(
                        $locale,
                        $domain,
                        new Translation($context, $message)
                    );
                }
            }
        }

        // Clear the queue of messages to be saved so on repeated
        $this->messages = [];
    }
}