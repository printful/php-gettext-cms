<?php


namespace Printful\GettextCms\Tests\TestCases;


use Gettext\Translation;
use Gettext\Translations;
use Printful\GettextCms\Interfaces\MessageRepositoryInterface;
use Printful\GettextCms\MessageStorage;
use Printful\GettextCms\Tests\Stubs\MessageRepositoryStub;
use Printful\GettextCms\Tests\TestCase;
use Printful\GettextCms\UntranslatedMessageExporter;

class UntranslatedExportTest extends TestCase
{
    /** @var MessageRepositoryInterface */
    private $repository;

    /** @var UntranslatedMessageExporter */
    private $exporter;

    /** @var MessageStorage */
    private $storage;

    /** @var string */
    private $domain;

    /** @var string */
    private $locale;

    public function setUp()
    {
        parent::setUp();

        $this->domain = 'test';
        $this->locale = 'lv_LV';

        $this->repository = new MessageRepositoryStub;
        $this->storage = new MessageStorage($this->repository);
        $this->exporter = new UntranslatedMessageExporter($this->storage);
    }

    public function testSimpleUntranslated()
    {
        // Save two untranslated items
        $this->storage->createOrUpdateSingle($this->locale, $this->domain, new Translation('ctx', 'O1'));
        $this->storage->createOrUpdateSingle($this->locale, $this->domain, new Translation('', 'O2'));

        // Same domain, but string is translated
        $this->storage->createOrUpdateSingle($this->locale, $this->domain,
            (new Translation('', 'O3'))->setTranslation('T3'));

        // Save random translation in a different domain/locale
        $this->storage->createOrUpdateSingle('en_US', 'random', new Translation('', 'random'));

        $ts = Translations::fromPoString($this->exporter->exportPoString($this->locale, $this->domain));

        self::assertCount(2, $ts, 'One untranslated item');
        self::assertNotFalse($ts->find('ctx', 'O1'), 'Untranslated item is within list');
        self::assertNotFalse($ts->find('', 'O2'), 'Untranslated item is within list');
    }

    public function testDisabledTranslationNotExported()
    {
        $t = (new Translation('', 'O1'))->setDisabled(true);
        $this->storage->createOrUpdateSingle($this->locale, $this->domain, $t);

        $po = $this->exporter->exportPoString($this->locale, $this->domain);

        self::assertEmpty($po, 'No translations exist');
    }

    public function testTranslationMissingPluralTranslationIsExported()
    {
        $t = (new Translation('', 'O1', 'P1'))->setTranslation('T1');

        $this->storage->createOrUpdateSingle($this->locale, $this->domain, $t);

        $ts = Translations::fromPoString($this->exporter->exportPoString($this->locale, $this->domain));

        self::assertCount(1, $ts, 'One translation exists');
    }

    public function testMissingOnePluralFormIsExported()
    {
        // lv_LV locale requires 3 plural forms, so this translation counts as untranslated
        $t = (new Translation('', 'O1', 'P1'))->setTranslation('T1')->setPluralTranslations(['PF1', 'PF2', '']);

        $this->storage->createOrUpdateSingle($this->locale, $this->domain, $t);

        $ts = Translations::fromPoString($this->exporter->exportPoString($this->locale, $this->domain));

        self::assertCount(1, $ts, 'Plural translations missing, is exported');
    }

    public function testAllPluralFormsExistsAndNotExported()
    {
        // lv_LV locale requires 3 plural forms, this translation is complete
        $t = (new Translation('', 'O1', 'P1'))
            ->setTranslation('T1')
            ->setPluralTranslations(['PF1', 'PF2', 'PF3']);

        $this->storage->createOrUpdateSingle($this->locale, $this->domain, $t);

        $ts = Translations::fromPoString($this->exporter->exportPoString($this->locale, $this->domain));

        self::assertEmpty($ts, 'All plural forms are present, nothing is exported');
    }

    public function testAllPluralFormsExistsAndNotExportedForEnUs()
    {
        // lv_LV locale requires 3 plural forms, this translation is complete
        $t = (new Translation('', 'O1', 'P1'))
            ->setTranslation('T1')
            ->setPluralTranslations(['PF1', 'PF2']);

        $locale = 'en_US';
        $this->storage->createOrUpdateSingle($locale, $this->domain, $t);

        $ts = Translations::fromPoString($this->exporter->exportPoString($locale, $this->domain));

        self::assertEmpty($ts, 'All plural forms are present, nothing is exported');
    }
}