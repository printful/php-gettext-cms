<?php

namespace Printful\GettextCms;

use Printful\GettextCms\Interfaces\MessageConfigInterface;

class MessageRevisions
{
    /** @var MessageConfigInterface */
    private $config;

    public function __construct(MessageConfigInterface $config)
    {
        $this->config = $config;
    }

    public function getRevisionedDomain(string $locale, string $domain): string
    {
        // TODO implement
        return $domain;
    }
}