<?php
/** @noinspection SpellCheckingInspection */


namespace Printful\GettextCms\Tests\Storage;


use Gettext\Translations;
use Printful\GettextCms\Interfaces\MessageRepositoryInterface;
use Printful\GettextCms\MessageStorage;
use Printful\GettextCms\Tests\Stubs\MessageRepositoryStub;
use Printful\GettextCms\Tests\TestCase;

class StorageTest extends TestCase
{
    /** @var MessageRepositoryInterface */
    private $repository;

    /** @var MessageStorage */
    private $storage;

    protected function setUp()
    {
        parent::setUp();

        $this->repository = new MessageRepositoryStub;

        $this->storage = new MessageStorage($this->repository);
    }

    public function testSaveAndGetItems()
    {
        $translations = new Translations;
        $translations->setLanguage('lv_LV');
        $translations->setDomain('my-domain');

        $translations
            ->insert('ctx 1', 'original 1', 'plural')
            ->addReference('file.php', 123)
            ->addExtractedComment('Extracted comment')
            ->addComment('Comment');

        $translations
            ->insert('ctx 2', 'original 2')
            ->setTranslation('Orģināls Divi')
            ->setDisabled(true);

        $this->storage->saveTranslations($translations);

        $translationsFound = $this->storage->getAllTranslations($translations->getLanguage(), $translations->getDomain());

        self::assertEquals($translations, $translationsFound, 'Original and returned translations are equal');
    }

    public function testMergeTranslationWithExistingDisabledOne()
    {
        $translations = new Translations;
        $translations->setLanguage('lv_LV');

        $t1 = $translations->insert('', 'Text')
            ->setDisabled(true)
            ->setTranslation('Teksts');

        // Initial save with disabled text
        $this->storage->saveTranslations($translations);

        // Create a new translations, but this time it's eanbled and untranslated
        $t2 = $t1->getClone()
            ->setDisabled(false)// Found again, but now enabled
            ->setTranslation(''); // Remove translation

        $newTranslations = new Translations;
        $newTranslations->setLanguage('lv_LV');
        $newTranslations[] = $t2;

        $this->storage->saveTranslations($newTranslations);

        $returned = $this->storage->getAllTranslations($translations->getLanguage(), $translations->getDomain());

        $found = $returned->find('', 'Text');

        self::assertEquals('Text', $found->getOriginal(), 'Original matches');
        self::assertEquals('Teksts', $found->getTranslation(), 'Translation matches');
        self::assertFalse($found->isDisabled(), 'Translation is no longer disabled');
    }
}