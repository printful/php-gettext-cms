<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Printful\GettextCms\Tests\TestCases;

use Mockery;
use Mockery\Mock;
use Printful\GettextCms\DynamicMessageImporter;
use Printful\GettextCms\Exceptions\MissingMessagesException;
use Printful\GettextCms\Interfaces\MessageConfigInterface;
use Printful\GettextCms\MessageArrayRepository;
use Printful\GettextCms\MessageStorage;
use Printful\GettextCms\Tests\TestCase;

class DynamicImporterTest extends TestCase
{
    /** @var MessageConfigInterface|Mock */
    private $mockConfig;

    /** @var MessageStorage */
    private $storage;

    /** @var DynamicMessageImporter */
    private $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockConfig = Mockery::mock(MessageConfigInterface::class);
        $this->storage = new MessageStorage(new MessageArrayRepository());
        $this->importer = new DynamicMessageImporter($this->mockConfig, $this->storage);
    }

    public function testAddNewTranslations()
    {
        $locale = 'lv_LV';
        $domain = 'custom';

        $this->mockConfig->shouldReceive('getLocales')->once()->andReturn([$locale]);
        $this->mockConfig->shouldReceive('getDefaultLocale')->once()->andReturn('en_US');

        $this->importer
            ->add('T1')
            ->add('T1', 'ctx1')
            ->add('T2', 'ctx2')
            ->saveAndDisabledPrevious($domain);

        $translations = $this->storage->getAll($locale, $domain);

        self::assertCount(3, $translations, 'Two translations exist');
        self::assertNotFalse($translations->find('', 'T1'), 'T1 is found');
        self::assertNotFalse($translations->find('ctx1', 'T1'), 'T1 with context is found');
        self::assertNotFalse($translations->find('ctx2', 'T2'), 'T2 with context is found');
    }

    public function testDisablePreviousTranslations()
    {
        $locale = 'lv_LV';
        $domain = 'custom';

        $this->mockConfig->shouldReceive('getLocales')->twice()->andReturn([$locale]);
        $this->mockConfig->shouldReceive('getDefaultLocale')->atLeast()->once()->andReturn('en_US');

        $this->importer
            ->add('T1')
            ->add('T2')
            ->saveAndDisabledPrevious($domain);

        $this->importer
            ->add('T2')
            ->add('T3')
            ->saveAndDisabledPrevious($domain);

        $translations = $this->storage->getAllEnabled($locale, $domain);

        self::assertFalse($translations->find('', 'T1'), 'T1 was disabled');
        self::assertNotFalse($translations->find('', 'T2'), 'T2 is present');
        self::assertNotFalse($translations->find('', 'T3'), 'T3 is present');
    }

    public function testPreventSavingWithoutMessages()
    {
        self::expectException(MissingMessagesException::class);
        $this->importer->saveAndDisabledPrevious('domain');
    }
}