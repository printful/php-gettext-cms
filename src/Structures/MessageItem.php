<?php

namespace Printful\GettextCms\Structures;

/**
 * Class represents a single translation message for saving to or receiving from storage
 */
class MessageItem
{
    /**
     * Unique 32 char identifier for this message, should be used as the unique key
     * @var string
     */
    public $key = '';

    /**
     * @var string
     */
    public $locale = '';

    /**
     * @var string
     */
    public $domain = '';

    /**
     * @var string
     */
    public $context = '';

    /**
     * @var string
     */
    public $original = '';

    /**
     * @var string
     */
    public $translation = '';

    /**
     * Is translation gone from source
     * @var bool
     */
    public $isDisabled = false;

    /**
     * @var string
     */
    public $originalPlural = '';

    /**
     * List of plural translations
     * @var string[]
     */
    public $pluralTranslations = [];

    /**
     * List of pathnames where this translation exists (with line numbers)
     * @var array[] [[file, line], ..]
     */
    public $references = [];

    /**
     * Comments from the translator
     * @var string[]
     */
    public $comments = [];

    /**
     * Comments from programmer (extracted from source code)
     * @var string[]
     */
    public $extractedComments = [];

    /**
     * @return bool
     */
    public function exists(): bool
    {
        return !!$this->key;
    }
}