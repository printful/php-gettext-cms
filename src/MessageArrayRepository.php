<?php

namespace Printful\GettextCms;

use Printful\GettextCms\Interfaces\MessageRepositoryInterface;
use Printful\GettextCms\Structures\MessageItem;

class MessageArrayRepository implements MessageRepositoryInterface
{
    /** @var MessageItem[] */
    private $store = [];

    /**
     * @inheritdoc
     */
    public function save(MessageItem $item): bool
    {
        $this->store[$item->key] = $item;
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getAll(string $locale, string $domain): array
    {
        return array_filter($this->store, function (MessageItem $item) use ($locale, $domain) {
            return $item->locale === $locale && $item->domain === $domain;
        });
    }

    /**
     * @inheritdoc
     */
    public function getEnabled(string $locale, string $domain): array
    {
        $allItems = $this->getAll($locale, $domain);

        return array_filter($allItems, function (MessageItem $item) {
            return !$item->isDisabled;
        });

    }

    /**
     * @inheritdoc
     */
    public function getEnabledTranslated(string $locale, string $domain): array
    {
        $enabledItems = $this->getEnabled($locale, $domain);

        return array_filter($enabledItems, function (MessageItem $item) {
            return $item->hasOriginalTranslation;
        });
    }

    /**
     * @inheritdoc
     */
    public function getEnabledTranslatedJs(string $locale, string $domain): array
    {
        $enabledTranslated = $this->getEnabledTranslated($locale, $domain);

        return array_filter($enabledTranslated, function (MessageItem $item) {
            return $item->isInJs;
        });
    }

    /**
     * @inheritdoc
     */
    public function getSingle($key): MessageItem
    {
        foreach ($this->store as $v) {
            if ($v->key === $key) {
                return $v;
            }
        }

        return new MessageItem();
    }

    /**
     * @inheritdoc
     */
    public function setAllAsNotInFilesAndInJs(string $locale, string $domain)
    {
        $this->store = array_map(function (MessageItem $item) use ($locale, $domain) {
            if ($item->domain === $domain && $item->locale === $locale) {
                $item->isInFile = false;
                $item->isInJs = false;
            }
            return $item;
        }, $this->store);
    }

    /**
     * @inheritdoc
     */
    public function setAllAsNotDynamic(string $locale, string $domain)
    {
        $this->store = array_map(function (MessageItem $item) use ($locale, $domain) {
            if ($item->domain === $domain && $item->locale === $locale) {
                $item->isDynamic = false;
            }
            return $item;
        }, $this->store);
    }

    /**
     * @inheritdoc
     */
    public function getRequiresTranslating(string $locale, string $domain): array
    {
        return array_filter($this->getEnabled($locale, $domain), function (MessageItem $item) {
            return $item->requiresTranslating;
        });
    }

    /**
     * @inheritdoc
     */
    public function disableUnused()
    {
        $this->store = array_map(function (MessageItem $item) {
            $item->isDisabled = !$item->isDynamic && !$item->isInJs && !$item->isInFile;
            return $item;
        }, $this->store);
    }
}