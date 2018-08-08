<?php

namespace Printful\GettextCms;

use Gettext\Languages\Language;
use Gettext\Translation;
use Gettext\Translations;
use Printful\GettextCms\Exceptions\InvalidTranslationException;
use Printful\GettextCms\Interfaces\MessageRepositoryInterface;
use Printful\GettextCms\Structures\MessageItem;

/**
 * Class handles all saving and retrieving logic of translations using the given repository
 */
class MessageStorage
{
    private const TYPE_FILE = 'file';
    private const TYPE_DYNAMIC = 'dynamic';

    /** @var MessageRepositoryInterface */
    private $repository;

    /**
     * Cache for plural form counts
     * @var array [locale => plural count, ..]
     */
    private $pluralFormCache = [];

    public function __construct(MessageRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Save translation object for a single domain and locale
     *
     * @param Translations $translations
     * @throws InvalidTranslationException
     */
    public function createOrUpdate(Translations $translations)
    {
        $this->validateHeaders($translations);

        $locale = $translations->getLanguage();
        $domain = $translations->getDomain();

        foreach ($translations as $translation) {
            $this->createOrUpdateSingle($locale, $domain, $translation);
        }
    }

    /**
     * @param Translations $translations
     * @throws InvalidTranslationException
     */
    private function validateHeaders(Translations $translations)
    {
        if (!$translations->getLanguage()) {
            throw new InvalidTranslationException('Locale is missing');
        }

        if (!$translations->getDomain()) {
            throw new InvalidTranslationException('Domain is missing');
        }
    }

    /**
     * Create or update a translation that is found in a file
     *
     * @param string $locale
     * @param string $domain
     * @param Translation $translation
     * @return bool
     */
    public function createOrUpdateSingle(string $locale, string $domain, Translation $translation): bool
    {
        return $this->createOrUpdateSingleWithType($locale, $domain, $translation, self::TYPE_FILE);
    }

    /**
     * Create or update a translation that is dynamically generated
     *
     * @param string $locale
     * @param string $domain
     * @param Translation $translation
     * @return bool
     */
    public function createOrUpdateSingleDynamic(string $locale, string $domain, Translation $translation): bool
    {
        return $this->createOrUpdateSingleWithType($locale, $domain, $translation, self::TYPE_DYNAMIC);
    }

    /**
     * Save a translation to repository by merging it to a previously saved version.
     *
     * @param string $locale
     * @param string $domain
     * @param Translation $translation
     * @param string $type
     * @return bool
     */
    private function createOrUpdateSingleWithType(
        string $locale,
        string $domain,
        Translation $translation,
        string $type
    ): bool {
        // Make a clone so we don't modify the passed instance
        $translation = $translation->getClone();

        $key = $this->getKey($locale, $domain, $translation);

        $existingItem = $this->repository->getSingle($key);

        if ($existingItem->exists()) {
            $existingTranslation = $this->itemToTranslation($existingItem);
            $translation->mergeWith($existingTranslation);
            $item = $this->translationToItem($locale, $domain, $translation);
        } else {
            $item = $this->translationToItem($locale, $domain, $translation);
        }

        $item->isInJs = $item->isInJs ?: $existingItem->isInJs;

        if ($type == self::TYPE_DYNAMIC || $existingItem->isDynamic) {
            $item->isDynamic = true;
        }

        if ($type == self::TYPE_FILE || $item->isInJs || $existingItem->isInFile) {
            $item->isInFile = true;
        }

        $item->hasOriginalTranslation = $translation->hasTranslation();
        $item->requiresTranslating = $this->requiresTranslating($locale, $translation);

        $item->isDisabled = $item->isDisabled ?: (!$item->isInJs && !$item->isInFile && !$item->isDynamic);

        if (!$this->hasChanged($existingItem, $item)) {
            return true;
        }

        return $this->repository->save($item);
    }

    /**
     * Save translation object for a single domain and locale
     *
     * @param Translations $translations
     * @throws InvalidTranslationException
     */
    public function saveTranslated(Translations $translations)
    {
        $this->validateHeaders($translations);

        $locale = $translations->getLanguage();
        $domain = $translations->getDomain();

        foreach ($translations as $translation) {
            $this->saveTranslatedSingle($locale, $domain, $translation);
        }
    }

    /**
     * Function for saving only translated values.
     * This will not modify disabled state and will not create new entries in repository, only modifies existing
     *
     * @param string $locale
     * @param string $domain
     * @param Translation $translation
     * @return bool
     */
    public function saveTranslatedSingle(string $locale, string $domain, Translation $translation): bool
    {
        if (!$translation->hasTranslation()) {
            return false;
        }

        $existingItem = $this->repository->getSingle($this->getKey($locale, $domain, $translation));

        if (!$existingItem->exists()) {
            return false;
        }

        $existingTranslation = $this->itemToTranslation($existingItem);

        $existingTranslation->setTranslation($translation->getTranslation());

        // Make sure we do not drop previous plural translations if current one does not contain one
        $pluralTranslations = $translation->getPluralTranslations();

        if (!empty($pluralTranslations)) {
            $existingTranslation->setPluralTranslations($pluralTranslations);
        }

        $item = $this->translationToItem($locale, $domain, $existingTranslation);

        $item->hasOriginalTranslation = true;
        // It's still possible that plurals are missing and this translation still needs work
        $item->requiresTranslating = $this->requiresTranslating($locale, $translation);

        if (!$this->hasChanged($existingItem, $item)) {
            return true;
        }

        return $this->repository->save($item);
    }

    /**
     * Check if some translations are missing (original or missing plural forms)
     *
     * @param string $locale
     * @param Translation $translation
     * @return bool
     */
    private function requiresTranslating(string $locale, Translation $translation): bool
    {
        if (!$translation->hasTranslation()) {
            return true;
        }

        if ($translation->hasPlural()) {
            $translatedPluralCount = count(array_filter($translation->getPluralTranslations()));
            // If there are less plural translations than language requires, this needs translating
            return $this->getPluralCount($locale) !== $translatedPluralCount;
        }

        return false;
    }

    /**
     * Get number of plural forms for this locale
     *
     * @param string $locale
     * @return int
     * @throws \InvalidArgumentException Thrown in the locale is not correct
     */
    private function getPluralCount(string $locale): int
    {
        if (!array_key_exists($locale, $this->pluralFormCache)) {
            $info = Language::getById($locale);

            // Minus one, because we do not count the original string as a plural
            $this->pluralFormCache[$locale] = count($info->categories) - 1;
        }

        return $this->pluralFormCache[$locale];
    }

    /**
     * Generate a unique key for storage (basically a primary key)
     *
     * @param string $locale
     * @param string $domain
     * @param Translation $translation
     * @return string
     */
    private function getKey(string $locale, string $domain, Translation $translation): string
    {
        return md5($locale . '|' . $domain . '|' . $translation->getContext() . '|' . $translation->getOriginal());
    }

    /**
     * Convert a message item to a translation item
     *
     * @param MessageItem $item
     * @return Translation
     */
    private function itemToTranslation(MessageItem $item): Translation
    {
        $translation = new Translation($item->context, $item->original, $item->originalPlural);

        $translation->setTranslation($item->translation);
        $translation->setPluralTranslations($item->pluralTranslations);
        $translation->setDisabled($item->isDisabled);

        foreach ($item->references as $v) {
            $translation->addReference($v[0], $v[1]);
        }

        foreach ($item->comments as $v) {
            $translation->addComment($v);
        }

        foreach ($item->extractedComments as $v) {
            $translation->addExtractedComment($v);
        }

        return $translation;
    }

    /**
     * Convert a translation item to a message item
     *
     * @param string $locale
     * @param string $domain
     * @param Translation $translation
     * @return MessageItem
     */
    private function translationToItem(string $locale, string $domain, Translation $translation): MessageItem
    {
        $item = new MessageItem;

        $item->key = $this->getKey($locale, $domain, $translation);
        $item->domain = $domain;
        $item->locale = $locale;
        $item->context = (string)$translation->getContext();
        $item->original = $translation->getOriginal();
        $item->translation = $translation->getTranslation();
        $item->originalPlural = $translation->getPlural();
        $item->pluralTranslations = $translation->getPluralTranslations();
        $item->references = $translation->getReferences();
        $item->comments = $translation->getComments();
        $item->extractedComments = $translation->getExtractedComments();
        $item->isDisabled = $translation->isDisabled();

        foreach ($item->references as $reference) {
            if ($this->isJsFile($reference[0] ?? '')) {
                $item->isInJs = true;
                break;
            }
        }

        return $item;
    }

    /**
     * Check if this is considered a JS file.
     *
     * @param string $filename
     * @return bool
     */
    private function isJsFile(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($extension, ['vue', 'js'], true);
    }

    /**
     * All translations, including disabled, enabled and untranslated
     *
     * @param string $locale
     * @param $domain
     * @return Translations
     */
    public function getAll(string $locale, $domain): Translations
    {
        return $this->convertItems(
            $locale,
            (string)$domain,
            $this->repository->getAll($locale, (string)$domain)
        );
    }

    /**
     * All enabled translations including untranslated
     *
     * @param string $locale
     * @param $domain
     * @return Translations
     */
    public function getAllEnabled(string $locale, $domain): Translations
    {
        return $this->convertItems(
            $locale,
            (string)$domain,
            $this->repository->getEnabled($locale, (string)$domain)
        );
    }

    /**
     * Enabled and translated translations only
     *
     * @param string $locale
     * @param $domain
     * @return Translations
     */
    public function getEnabledTranslated(string $locale, $domain): Translations
    {
        $items = $this->repository->getEnabledTranslated($locale, (string)$domain);

        return $this->convertItems($locale, (string)$domain, $items);
    }

    /**
     * Enabled and translated JS translations
     *
     * @param string $locale
     * @param $domain
     * @return Translations
     */
    public function getEnabledTranslatedJs(string $locale, $domain): Translations
    {
        $items = $this->repository->getEnabledTranslatedJs($locale, (string)$domain);

        return $this->convertItems($locale, (string)$domain, $items);
    }

    /**
     * Enabled and translated translations only
     *
     * @param string $locale
     * @param $domain
     * @return Translations
     */
    public function getRequiresTranslating(string $locale, $domain): Translations
    {
        return $this->convertItems(
            $locale,
            (string)$domain,
            $this->repository->getRequiresTranslating($locale, (string)$domain)
        );
    }

    /**
     * Converts message items to a translation object
     *
     * @param string $locale
     * @param string|null $domain
     * @param MessageItem[] $items
     * @return Translations
     */
    private function convertItems(string $locale, $domain, array $items): Translations
    {
        $domain = (string)$domain;

        $translations = new Translations;
        $translations->setDomain($domain);
        $translations->setLanguage($locale);

        foreach ($items as $v) {
            $translation = $this->itemToTranslation($v);
            $translations[] = $translation;
        }

        return $translations;
    }

    /**
     * Check if new item is different than the old one
     *
     * @param MessageItem $old
     * @param MessageItem $new
     * @return bool
     */
    private function hasChanged(MessageItem $old, MessageItem $new): bool
    {
        // TODO implement
        return true;
    }

    /**
     * Set all locale and domain messages as not present in files ("isFile" and "isInJs" fields should be set to false)
     *
     * @param string $locale
     * @param string $domain
     */
    public function setAllAsNotInFilesAndInJs(string $locale, string $domain)
    {
        $this->repository->setAllAsNotInFilesAndInJs($locale, $domain);
    }

    /**
     * Set all locale and domain messages as not dynamic ("isDynamic" field should be set to false)
     *
     * @param string $locale
     * @param string $domain
     */
    public function setAllAsNotDynamic(string $locale, string $domain)
    {
        $this->repository->setAllAsNotDynamic($locale, $domain);
    }

    /**
     * Set all messages as disabled which are not used (messages which filed "isDynamic", "isInFile" and "isInJs" all are false)
     */
    public function disableUnused()
    {
        $this->repository->disableUnused();
    }
}