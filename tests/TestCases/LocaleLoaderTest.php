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

/**
 * Warning, each test has to use a different text domain, because all tests run in the separate process
 * and gettext may not re-cache the translations.
 */
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
        $domain = 'default';
        $locale = 'en_US';

        $this->setConfing($domain, [], false);

        $this->storage->saveSingleTranslation($locale, $domain, (new Translation('', 'O1'))->setTranslation('T1'));
        $this->builder->export($locale, $domain);

        self::assertEquals('O1', _('O1'), 'Translation does not exist');

        // Switch to a translated locale
        $isLocaleSet = $this->loader->load($locale);
        self::assertTrue($isLocaleSet, 'Text domain and locale was bound');
        self::assertEquals('T1', _('O1'), 'Translation is returned');

        // Switch to a random locale that does not have a translation
        $isLocaleSet = $this->loader->load('de_DE');
        self::assertTrue($isLocaleSet, 'Text domain and locale was bound');
        self::assertEquals('O1', _('O1'), 'Translation does not exist for other locale');

        // Switch back to an existing locale and test again
        $this->loader->load($locale);
        self::assertEquals('T1', _('O1'), 'Translation is returned');
    }

    public function testSwitchBetweenTwoLocales()
    {
        $domain = 'domain2';
        $locale1 = 'en_US';
        $locale2 = 'de_DE';

        $this->setConfing($domain, [], false);

        $t1 = (new Translation('', 'Original'))->setTranslation('Eng');
        $this->storage->saveSingleTranslation($locale1, $domain, $t1);
        $this->builder->export($locale1, $domain);

        $t2 = (new Translation('', 'Original'))->setTranslation('Ger');
        $this->storage->saveSingleTranslation($locale2, $domain, $t2);
        $this->builder->export($locale2, $domain);

        self::assertTrue($this->loader->load($locale1));
        self::assertEquals($t1->getTranslation(), _($t1->getOriginal()), 'Translation is returned');

        self::assertTrue($this->loader->load($locale2));
        self::assertEquals($t2->getTranslation(), _($t2->getOriginal()), 'Translation is returned');
    }

    public function testGettextCachesOldTranslationWithoutRevisions()
    {
        $domain = 'domain3';
        $locale = 'en_US';

        $this->setConfing($domain);

        $t = (new Translation('', 'Original'))->setTranslation('Translated');
        $this->storage->saveSingleTranslation($locale, $domain, $t);
        $this->builder->export($locale, $domain);

        $this->loader->load($locale);

        self::assertEquals($t->getTranslation(), _($t->getOriginal()), 'Translation is returned');

        $tUpdated = (new Translation('', 'Original'))->setTranslation('Updated translation');
        $this->storage->saveSingleTranslation($locale, $domain, $tUpdated);
        $this->builder->export($locale, $domain);

        $this->loader->load($locale);

        // Proves that without revisions, gettext caches the first translated string, ignores updated
        self::assertEquals(
            $t->getTranslation(),
            _($tUpdated->getOriginal()),
            'Old translation is returned, not the updated one');
    }

    private function setConfing($domain, array $otherDomains = [], bool $useRevisions = false): void
    {
        $this->config->shouldReceive('getDefaultDomain')->andReturn($domain)->atLeast()->once();
        $this->config->shouldReceive('getOtherDomains')->andReturn($otherDomains)->atLeast()->once();
        $this->config->shouldReceive('useRevisions')->andReturn($useRevisions)->atLeast()->once();
    }

}