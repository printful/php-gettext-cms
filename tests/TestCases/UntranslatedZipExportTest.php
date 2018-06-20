<?php

namespace Printful\GettextCms\Tests\TestCases;

use Gettext\Translation;
use Gettext\Translations;
use InvalidArgumentException;
use Mockery;
use Mockery\Mock;
use Printful\GettextCms\Interfaces\MessageConfigInterface;
use Printful\GettextCms\Interfaces\MessageRepositoryInterface;
use Printful\GettextCms\MessageStorage;
use Printful\GettextCms\Tests\Stubs\MessageRepositoryStub;
use Printful\GettextCms\Tests\TestCase;
use Printful\GettextCms\UntranslatedMessageExporter;
use Printful\GettextCms\UntranslatedMessageZipExporter;
use ZipArchive;

class UntranslatedZipExportTest extends TestCase
{
    /** @var MessageRepositoryInterface */
    private $repository;

    /** @var MessageStorage */
    private $storage;

    /** @var string */
    private $domain;

    /** @var string */
    private $domainOther;

    /** @var string */
    private $locale;

    /** @var MessageConfigInterface|Mock */
    private $config;

    private $zipPathname;

    public function setUp()
    {
        parent::setUp();

        $this->domain = 'domain';
        $this->domainOther = 'domain2';
        $this->locale = 'en_US';

        $this->repository = new MessageRepositoryStub;
        $this->storage = new MessageStorage($this->repository);
        $this->config = Mockery::mock(MessageConfigInterface::class);

        $this->config->shouldReceive('getOtherDomains')->andReturn([$this->domainOther])->atLeast()->once();
        $this->config->shouldReceive('getDefaultDomain')->andReturn($this->domain)->atLeast()->once();

        $this->zipPathname = __DIR__ . '/../assets/temp/export.zip';
        $pathname = $this->zipPathname;
        @unlink($pathname);
    }

    protected function tearDown()
    {
        @unlink($this->zipPathname);
        parent::tearDown();
    }

    public function testSimpleUntranslated()
    {
        $this->storage->createOrUpdateSingle($this->locale, $this->domain, new Translation('ctx', 'O1'));
        $this->storage->createOrUpdateSingle($this->locale, $this->domain, new Translation('', 'O2'));
        $this->storage->createOrUpdateSingle($this->locale, $this->domainOther, new Translation('', 'O3'));

        $zipExporter = new UntranslatedMessageZipExporter(
            $this->config,
            new UntranslatedMessageExporter($this->storage)
        );

        self::assertFileNotExists($this->zipPathname, 'Zip file does not exist');

        $zipExporter->export($this->zipPathname, $this->locale);

        self::assertFileExists($this->zipPathname, 'Zip file exists');

        $zip = new ZipArchive;
        $zip->open($this->zipPathname);

        self::assertEquals(2, $zip->numFiles, 'Two domain files in zip');

        $ts1 = Translations::fromPoString($zip->getFromIndex(0));
        $ts2 = Translations::fromPoString($zip->getFromIndex(1));

        if ($ts1->getDomain() == $this->domain) {
            $tsDomain = $ts1;
            $tsDomainOther = $ts2;
        } else {
            $tsDomain = $ts2;
            $tsDomainOther = $ts1;
        }

        self::assertNotFalse($tsDomain->find('ctx', 'O1'), 'O1 was found');
        self::assertNotFalse($tsDomain->find('', 'O2'), 'O2 was found');
        self::assertNotFalse($tsDomainOther->find('', 'O3'), 'O3 was found');
    }

    public function testFailOnMissingDirectory()
    {
        $zipExporter = new UntranslatedMessageZipExporter(
            $this->config,
            new UntranslatedMessageExporter($this->storage)
        );

        self::expectException(InvalidArgumentException::class);
        $zipExporter->export('/missing-path/file.zip', $this->locale);
    }
}