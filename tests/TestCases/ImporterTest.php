<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Printful\GettextCms\Tests\TestCases;

use Mockery;
use Mockery\Mock;
use Printful\GettextCms\Interfaces\MessageConfigInterface;
use Printful\GettextCms\MessageArrayRepository;
use Printful\GettextCms\MessageExtractor;
use Printful\GettextCms\MessageImporter;
use Printful\GettextCms\MessageStorage;
use Printful\GettextCms\Structures\ScanItem;
use Printful\GettextCms\Tests\TestCase;

class ImporterTest extends TestCase
{
    /** @var MessageConfigInterface|Mock */
    private $config;

    /** @var MessageStorage */
    private $storage;

    /** @var MessageImporter */
    private $importer;

    /** @var MessageExtractor */
    private $extractor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = Mockery::mock(MessageConfigInterface::class);
        $this->storage = new MessageStorage(new MessageArrayRepository());
        $this->extractor = new MessageExtractor($this->config);
        $this->importer = new MessageImporter($this->config, $this->storage, $this->extractor);
    }

    public function testSaveAndDisableOnRepeatedSave()
    {
        $domain = 'default';
        $localeDe = 'de_DE';
        $localeEn = 'en_US';

        $this->configure($localeEn, [$localeEn, $localeDe], $domain, []);

        // Scan for two translations
        $this->importer->extractAndSave([new ScanItem($this->getDummyFile('../importer/file1.php'))]);

        self::assertEmpty($this->storage->getAll($localeEn, $domain), 'Default locale is not saved');
        $allEnabled = $this->storage->getAllEnabled($localeDe, $domain);

        self::assertCount(2, $allEnabled, 'German locale is saved');
        self::assertNotFalse($allEnabled->find('', 'first'));
        self::assertNotFalse($allEnabled->find('', 'second'));

        // Scan for two translations where one is new and another one is missing now
        $this->importer->extractAndSave([new ScanItem($this->getDummyFile('../importer/file2.php'))]);

        $allEnabledAfter = $this->storage->getAllEnabled($localeDe, $domain);
        self::assertCount(2, $allEnabledAfter, 'Two enabled translations');
        self::assertCount(3, $this->storage->getAll($localeDe, $domain), 'Total three translations total');

        // One old translation is disabled, new translation was added
        self::assertNotFalse($allEnabledAfter->find('', 'second'));
        self::assertNotFalse($allEnabledAfter->find('', 'third'));

    }

    private function configure(string $defaultLocale, array $locales, string $defaultDomain, array $otherDomains)
    {
        $this->config->shouldReceive('getDefaultLocale')->andReturn($defaultLocale)->atLeast()->once();
        $this->config->shouldReceive('getLocales')->andReturn($locales)->atLeast()->once();
        $this->config->shouldReceive('getOtherDomains')->andReturn($otherDomains)->atLeast()->once();
        $this->config->shouldReceive('getDefaultDomain')->andReturn($defaultDomain)->atLeast()->once();
    }
}