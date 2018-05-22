<?php


namespace Printful\GettextCms\Tests\Stubs;


use Printful\GettextCms\Interfaces\MessageRepositoryInterface;
use Printful\GettextCms\Structures\MessageItem;

class MessageRepositoryStub implements MessageRepositoryInterface
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
    public function getSingle($key): MessageItem
    {
        foreach ($this->store as $v) {
            if ($v->key === $key) {
                return $v;
            }
        }

        return new MessageItem;
    }
}