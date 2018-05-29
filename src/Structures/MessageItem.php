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
     * This indicates if original is translated, but plural translations can be missing
     * @var bool
     */
    public $hasOriginalTranslation = false;

    /**
     * This indicates if string has to be translated/checked, can be missing plural translation
     * @var bool
     */
    public $needsChecking = false;

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