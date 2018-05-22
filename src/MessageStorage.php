<?php


namespace Printful\GettextCms;


use Gettext\Translation;
use Gettext\Translations;
use Printful\GettextCms\Interfaces\MessageRepositoryInterface;
use Printful\GettextCms\Structures\MessageItem;

class MessageStorage
{
    /** @var MessageRepositoryInterface */
    private $repository;

    public function __construct(MessageRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function saveTranslations(Translations $translations)
    {
        $locale = $translations->getLanguage();
        $domain = (string)$translations->getDomain();

        foreach ($translations as $v) {
            $this->saveSingleTranslation($locale, $domain, $v);
        }
    }

    private function saveSingleTranslation(string $locale, string $domain, Translation $translation)
    {
        $key = $this->getKey($locale, $domain, $translation);

        $existingItem = $this->repository->getSingle($key);

        if ($existingItem->exists()) {
            $existingTranslation = $this->itemToTranslation($existingItem);
            $existingTranslation->mergeWith($translation);
            $item = $this->translationToItem($locale, $domain, $existingTranslation);

            // Override the disabled state of the existing translation
            $item->isDisabled = $translation->isDisabled();
        } else {
            $item = $this->translationToItem($locale, $domain, $translation);
        }

        $this->repository->save($item);
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

        return $item;
    }

    public function getTranslations(string $locale, $domain): Translations
    {
        $domain = (string)$domain;

        $translations = new Translations;
        $translations->setDomain($domain);
        $translations->setLanguage($locale);

        $items = $this->repository->getAll($locale, $domain);

        foreach ($items as $v) {
            $translation = $this->itemToTranslation($v);
            $translations[] = $translation;
        }

        return $translations;
    }
}