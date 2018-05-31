<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Printful\GettextCms\Tests\TestCases;

use Gettext\Translation;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Mockery;
use Mockery\Mock;
use Printful\GettextCms\Interfaces\MessageConfigInterface;
use Printful\GettextCms\LocaleLoader;
use Printful\GettextCms\MessageBuilder;
use Printful\GettextCms\MessageStorage;
use Printful\GettextCms\Tests\Stubs\MessageRepositoryStub;
use Printful\GettextCms\Tests\TestCase;

class LocaleLoaderTest extends TestCase
{
    /** @var string */
    private $dir;

    /** @var Mock|MessageConfigInterface */
    private $config;

    /** @var MessageStorage */
    private $storage;

    /** @var MessageBuilder */
    private $builder;

    /** @var LocaleLoader */
    private $loader;

    /** @var string */
    private $baseDir;

    /** @var string */
    private $moDir;

    public function setUp()
    {
        parent::setUp();

        $this->baseDir = realpath(__DIR__ . '/../assets/temp');
        $this->moDir = 'generated-translations';
        $this->dir = $this->baseDir . '/' . $this->moDir;

        $this->config = Mockery::mock(MessageConfigInterface::class);
        $this->config->shouldReceive('getMoDirectory')->andReturn($this->dir)->atLeast()->once();

        $this->storage = new MessageStorage(new MessageRepositoryStub);
        $this->builder = new MessageBuilder($this->config, $this->storage);
        $this->loader = new LocaleLoader($this->config);

        $this->cleanDirectory();

        @mkdir($this->dir);
    }

    private function cleanDirectory()
    {
        $adapter = new Local($this->baseDir);
        $filesystem = new Filesystem($adapter);
        $filesystem->deleteDir($this->moDir);
    }

    protected function tearDown()
    {
        $this->cleanDirectory();
        parent::tearDown();
    }

    public function testLoadLocaleAndGetTranslationWithGettext()
    {
        $config = $this->config;

        $domain = 'default';
        $locale = 'en_US';

        $config->shouldReceive('getDefaultDomain')->andReturn($domain)->atLeast()->once();
        $config->shouldReceive('getOtherDomains')->andReturn([])->atLeast()->once();
        $config->shouldReceive('useRevisions')->andReturn(false)->atLeast()->once();

        $this->storage->saveSingleTranslation($locale, $domain, (new Translation('', 'O1'))->setTranslation('T1'));

        $this->builder->export($locale, $domain);

        self::assertEquals('O1', _('O1'), 'Translation does not exist');

        $this->loader->load($locale);

        self::assertEquals('T1', _('O1'), 'Translation is returned');

        $this->loader->load('lv_LV');

        self::assertEquals('O1', _('O1'), 'Translation does not exist for other locale');
    }
}