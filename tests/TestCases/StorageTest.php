<?php /** @noinspection PhpUnhandledExceptionInspection */

/** @noinspection SpellCheckingInspection */


namespace Printful\GettextCms\Tests\TestCases;


use Gettext\Translations;
use Printful\GettextCms\Exceptions\InvalidTranslationException;
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

        $translationsFound = $this->storage->getAll($translations->getLanguage(), $translations->getDomain());

        self::assertEquals($translations, $translationsFound, 'Original and returned translations are equal');
    }

    public function testMergeTranslationWithExistingDisabledOne()
    {
        $translations = (new Translations)
            ->setLanguage('lv_LV')
            ->setDomain('domain');

        $t1 = $translations->insert('', 'Text')
            ->setDisabled(true)
            ->setTranslation('Teksts');

        // Initial save with disabled text
        $this->storage->saveTranslations($translations);

        // Create a new translations, but this time it's eanbled and untranslated
        $t2 = $t1->getClone()
            ->setDisabled(false)// Found again, but now enabled
            ->setTranslation(''); // Remove translation

        $newTranslations = (new Translations)
            ->setLanguage('lv_LV')
            ->setDomain('domain');
        $newTranslations[] = $t2;

        $this->storage->saveTranslations($newTranslations);

        $returned = $this->storage->getAll($translations->getLanguage(), $translations->getDomain());

        $found = $returned->find('', 'Text');

        self::assertEquals('Text', $found->getOriginal(), 'Original matches');
        self::assertEquals('Teksts', $found->getTranslation(), 'Translation matches');
        self::assertFalse($found->isDisabled(), 'Translation is no longer disabled');
    }

    public function testTranslatedAndEnabledMessageRetrieval()
    {
        $locale = 'lv_LV';
        $domain = 'domain';

        $translations = (new Translations)->setLanguage($locale)->setDomain($domain);

        $translations->insert('', 'O1')->setTranslation('T1');
        $translations->insert('', 'O2'); // Untranslated
        $translations->insert('', 'O3')->setTranslation('T3')->setDisabled(true); // Disabled

        $this->storage->saveTranslations($translations);

        $enabledTranslated = $this->storage->getEnabledTranslated($locale, $domain);
        $foundTranslation = $enabledTranslated->find('', 'O1');

        self::assertCount(2, $this->storage->getAllEnabled($locale, $domain), 'Two enabled exist');
        self::assertCount(3, $this->storage->getAll($locale, $domain), 'All three messages exist');
        self::assertCount(1, $enabledTranslated, 'One translated message exists');
        self::assertNotFalse($foundTranslation, 'Translated translation was found');
    }

    public function testMissingPluralTranslation()
    {
        $t = (new Translations)->setLanguage('lv_LV')->setDomain('domain');
        $t->insert('', 'O1', 'P1')->setTranslation('T1'); // Plural is not translated

        $this->storage->saveTranslations($t);

        self::assertEquals(
            $t,
            $this->storage->getEnabledTranslated($t->getLanguage(), $t->getDomain()),
            'Plural was not translated, but translations counts as translated, just needs checking'
        );
    }

    public function testPreventSavingWithoutLocale()
    {
        $translations = (new Translations)->setDomain('domain');
        self::expectException(InvalidTranslationException::class);
        $this->storage->saveTranslations($translations);
    }

    public function testPreventSavingWithoutDomain()
    {
        $translations = (new Translations)->setLanguage('en_US');
        self::expectException(InvalidTranslationException::class);
        $this->storage->saveTranslations($translations);
    }
}