<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Printful\GettextCms\Tests\TestCases;

use Gettext\Extractors\Mo;
use Gettext\Translation;
use Gettext\Translations;
use Mockery;
use Mockery\Mock;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Printful\GettextCms\Exceptions\InvalidPathException;
use Printful\GettextCms\Interfaces\MessageConfigInterface;
use Printful\GettextCms\Interfaces\MessageRepositoryInterface;
use Printful\GettextCms\MessageBuilder;
use Printful\GettextCms\MessageRevisions;
use Printful\GettextCms\MessageStorage;
use Printful\GettextCms\Tests\Stubs\MessageRepositoryStub;
use Printful\GettextCms\Tests\TestCase;

class BuilderTest extends TestCase
{
    /** @var Mock|MessageConfigInterface */
    private $config;

    /** @var MessageRepositoryInterface */
    private $repository;

    /** @var MessageBuilder */
    private $exporter;

    /** @var vfsStreamDirectory */
    private $root;

    /** @var MessageRevisions */
    private $revisions;

    /** @var MessageStorage */
    private $storage;

    /** @var string */
    private $locale;

    /** @var string */
    private $domain;

    protected function setUp()
    {
        parent::setUp();

        $this->locale = 'en_US';
        $this->domain = 'domain';

        $this->root = vfsStream::setup('virtualMoDirectory/');

        $this->config = Mockery::mock(MessageConfigInterface::class);

        // Set a fake default locale so all tests will work as if their locale is not the default
        $this->config->shouldReceive('getDefaultLocale')->andReturn('xx_XX')->byDefault();

        $this->config->shouldReceive('useRevisions')->andReturn(false)->byDefault();

        $this->repository = new MessageRepositoryStub;
        $this->revisions = new MessageRevisions($this->config);
        $this->storage = new MessageStorage($this->repository);

        $this->exporter = new MessageBuilder(
            $this->config,
            $this->storage,
            $this->revisions
        );
    }

    public function testExportToMoFile()
    {
        $locale = $this->locale;
        $domain = 'custom-domain';

        $this->add('O1', 'T1');
        $this->add('O2', 'T2');
        $this->add('O3', 'T3', 'CTX3');

        $dir = $this->root->url();

        $expectedPath = $this->getMoPathname($domain);

        self::assertFalse($this->root->hasChild($expectedPath), 'Mo files does not exist');

        $this->config->shouldReceive('getMoDirectory')->andReturn($dir)->once();

        $this->exporter->export($locale, $domain);

        self::assertTrue($this->root->hasChild($expectedPath), 'Mo file was created');

        $messages = $this->repository->getEnabledTranslated($locale, $domain);
        $this->verifyTranslations($messages, $locale, $domain, $this->root->url() . '/' . $expectedPath);
    }

    public function testExceptionOnMissingDir()
    {
        $this->add('Original', 'Translation');

        $this->config->shouldReceive('getMoDirectory')->andReturn('missing-directory')->once();

        self::expectException(InvalidPathException::class);

        $this->exporter->export($this->locale, $this->domain);
    }

    public function testRepeatedExportOfSameTranslationResultsInSameRevision()
    {
        $dir = $this->root->url();

        $this->config->shouldReceive('getMoDirectory')->andReturn($dir)->once();
        $this->config->shouldReceive('useRevisions')->andReturn(true)->byDefault();

        $this->add('Original', 'Translation');

        $this->exporter->export($this->locale, $this->domain);

        $expectedPath = $this->getMoPathname($this->revisions->getRevisionedDomain($this->locale, $this->domain));

        self::assertTrue($this->root->hasChild($expectedPath), 'Mo file exists');

        // Revision should not change
        $this->exporter->export($this->locale, $this->domain);
        self::assertTrue($this->root->hasChild($expectedPath), 'Same mo file exists');
    }

    public function testPreviousDomainIsRemovedAfterNewRevisionIsCreated()
    {
        $dir = $this->root->url();

        $this->config->shouldReceive('getMoDirectory')->andReturn($dir)->once();
        $this->config->shouldReceive('useRevisions')->andReturn(true)->byDefault();

        $this->add('Original', 'Translation');

        $this->exporter->export($this->locale, $this->domain);

        $pathFirstRevision = $this->getMoPathname($this->revisions->getRevisionedDomain($this->locale, $this->domain));

        self::assertTrue($this->root->hasChild($pathFirstRevision), 'Mo file exists');

        $this->add('Original', 'Different Translation');

        $this->exporter->export($this->locale, $this->domain);
        $pathSecondRevision = $this->getMoPathname($this->revisions->getRevisionedDomain($this->locale, $this->domain));

        self::assertFalse($this->root->hasChild($pathFirstRevision), 'Previous file is removed');
        self::assertTrue($this->root->hasChild($pathSecondRevision), 'New revision file was created');
    }

    public function testDefaultLocaleIsNotExported()
    {
        // Set a fake default locale so all tests will work as if their locale is not the default
        $this->config->shouldReceive('getDefaultLocale')->andReturn('en_EN')->byDefault();
        $this->config->shouldReceive('getMoDirectory')->andReturn('whatever')->never();

        // If there were an attempt to export, error would happend because mo directory does not exist
        self::assertTrue($this->exporter->export('en_EN', 'domain'), 'Default domain is not exported');
    }

    private function verifyTranslations(array $messages, string $locale, string $domain, string $moPathname)
    {
        $translations = new Translations;
        Mo::fromFile($moPathname, $translations);

        self::assertEquals($locale, $translations->getLanguage(), 'Locale matches');
        self::assertEquals($domain, $translations->getDomain(), 'Domain matches');

        self::assertCount(count($messages), $translations);

        foreach ($messages as $v) {
            $translation = $translations->find($v->context, $v->original);
            self::assertNotFalse($translation);
            self::assertEquals($v->translation, $translation->getTranslation(), 'Translation matches');
            self::assertEquals($v->context, $translation->getContext(), 'Context matches');
        }
    }

    private function getMoPathname($domain)
    {
        return $this->locale . '/LC_MESSAGES/' . $domain . '.mo';
    }

    private function add(string $original, string $translation, string $context = ''): self
    {
        $this->storage->createOrUpdateSingle(
            $this->locale,
            $this->domain,
            (new Translation($context, $original))->setTranslation($translation)
        );

        return $this;
    }
}