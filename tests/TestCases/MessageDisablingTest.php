<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Printful\GettextCms\Tests\TestCases;

use Gettext\Translation;
use Mockery;
use Mockery\Mock;
use Printful\GettextCms\DynamicMessageImporter;
use Printful\GettextCms\Interfaces\MessageConfigInterface;
use Printful\GettextCms\Interfaces\MessageRepositoryInterface;
use Printful\GettextCms\MessageExtractor;
use Printful\GettextCms\MessageImporter;
use Printful\GettextCms\MessageStorage;
use Printful\GettextCms\Structures\ScanItem;
use Printful\GettextCms\Tests\Stubs\MessageRepositoryStub;
use Printful\GettextCms\Tests\TestCase;

class MessageDisablingTest extends TestCase
{
    /** @var MessageConfigInterface|Mock */
    private $config;

    /** @var MessageStorage */
    private $storage;

    /** @var DynamicMessageImporter */
    private $dynamicImporter;

    /** @var MessageImporter */
    private $fileImporter;

    /** @var MessageRepositoryInterface */
    private $repository;

    protected function setUp()
    {
        parent::setUp();

        $this->config = Mockery::mock(MessageConfigInterface::class);
        $this->repository = new MessageRepositoryStub;
        $this->storage = new MessageStorage($this->repository);
        $this->dynamicImporter = new DynamicMessageImporter($this->config, $this->storage);
        $this->fileImporter = new MessageImporter($this->config, $this->storage, new MessageExtractor($this->config));
    }

    public function testStaticMessageIsNotDisabledAfterDynamicMessagesAreAdded()
    {
        $locale = 'lv_LV';
        $domain = 'custom';

        $this->config->shouldReceive('getLocales')->twice()->andReturn([$locale]);
        $this->config->shouldReceive('getDefaultLocale')->atLeast()->once()->andReturn('en_US');

        $this->dynamicImporter->add('Dynamic 1')->saveAndDisabledPrevious($domain);

        $this->storage->createOrUpdateSingle($locale, $domain, new Translation('', 'Static'));

        // This should not disable "Static" translation, but will disable "Dynamic 1"
        $this->dynamicImporter->add('Dynamic 2')->saveAndDisabledPrevious($domain);

        $translations = $this->storage->getAllEnabled($locale, $domain);

        self::assertNotFalse($translations->find('', 'Static'), 'Static was found');
        self::assertNotFalse($translations->find('', 'Dynamic 2'), 'Static was found');
        self::assertCount(2, $translations);
    }

    public function testDynamicMessageIsNotDisabledAfterStaticScan()
    {
        $locale = 'lv_LV';
        $domain = 'domain';

        $this->config->shouldReceive('getLocales')->atLeast()->once()->andReturn([$locale]);
        $this->config->shouldReceive('getDefaultLocale')->atLeast()->once()->andReturn('en_US');
        $this->config->shouldReceive('getDefaultDomain')->andReturn('whatever-domain');
        $this->config->shouldReceive('getOtherDomains')->andReturn([$domain]);

        // This will add one static message
        $this->fileImporter->extractAndSave([new ScanItem($this->getDummyFile('../importer/domain.php'))]);

        // Add one dynamic message in the same domain
        $this->dynamicImporter->add("Dynamic")->saveAndDisabledPrevious($domain);

        self::assertCount(
            2,
            $this->storage->getAllEnabled($locale, $domain),
            'Dynamic and static messages are found'
        );

        // Scan again, and this should not disable dynamic message
        $this->fileImporter->extractAndSave([new ScanItem($this->getDummyFile('../importer/domain.php'))]);

        self::assertCount(2, $this->storage->getAllEnabled($locale, $domain), 'Same messages are found');
    }


    public function testSameMessageIsNotDisabledIfStillPresentSomewhere()
    {
        $locale = 'lv_LV';
        $domain = 'domain';

        $this->config->shouldReceive('getLocales')->atLeast()->once()->andReturn([$locale]);
        $this->config->shouldReceive('getDefaultLocale')->atLeast()->once()->andReturn('en_US');
        $this->config->shouldReceive('getDefaultDomain')->andReturn('whatever-domain');
        $this->config->shouldReceive('getOtherDomains')->andReturn([$domain]);

        // Adds one translation "message"
        $this->fileImporter->extractAndSave([new ScanItem($this->getDummyFile('../importer/domain.php'))]);

        $item = $this->findOneByMessage($locale, $domain, 'message');

        self::assertTrue($item->isInFile, 'Is in file after file import');

        // Add same translation as dynamic
        $this->dynamicImporter->add('message')->saveAndDisabledPrevious($domain);

        $item = $this->findOneByMessage($locale, $domain, 'message');

        self::assertTrue($item->isInFile, 'Is in file');
        self::assertTrue($item->isDynamic, 'Is dynamic');

        // Remove dynamic message, add a different one
        $this->dynamicImporter->add('other')->saveAndDisabledPrevious($domain);

        $item = $this->findOneByMessage($locale, $domain, 'message');

        self::assertFalse($item->isDynamic, 'Is not dynamic, was disabled');
        self::assertTrue($item->isInFile, 'Still is in file');

    }

    private function findOneByMessage($locale, $domain, $original)
    {
        $all = $this->repository->getAll($locale, $domain);
        foreach ($all as $v) {
            if ($v->original === $original) {
                return $v;
            }
        }
        return null;
    }
}