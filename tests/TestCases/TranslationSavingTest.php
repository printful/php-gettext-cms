<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Printful\GettextCms\Tests\TestCases;

use Gettext\Translations;
use Mockery;
use Mockery\Mock;
use Printful\GettextCms\Exceptions\UnsupportedDomainException;
use Printful\GettextCms\Exceptions\UnsupportedLocaleException;
use Printful\GettextCms\Interfaces\MessageConfigInterface;
use Printful\GettextCms\Interfaces\MessageRepositoryInterface;
use Printful\GettextCms\MessageStorage;
use Printful\GettextCms\Tests\Stubs\MessageRepositoryStub;
use Printful\GettextCms\Tests\TestCase;
use Printful\GettextCms\TranslatedMessageImporter;

class TranslationSavingTest extends TestCase
{
    /** @var MessageRepositoryInterface */
    private $repository;

    /** @var MessageStorage */
    private $storage;

    /** @var MessageConfigInterface|Mock */
    private $config;

    /** @var TranslatedMessageImporter */
    private $importer;

    protected function setUp()
    {
        parent::setUp();

        $this->repository = new MessageRepositoryStub;
        $this->storage = new MessageStorage($this->repository);
        $this->config = Mockery::mock(MessageConfigInterface::class);
        $this->importer = new TranslatedMessageImporter($this->config, $this->storage);
    }

    public function testFailWithUnknownDomain()
    {
        $ts = (new Translations)->setDomain('unknown-domain')->setLanguage('en_US');
        $this->configure('en_US', 'default', []);

        self::expectException(UnsupportedDomainException::class);
        $this->importer->importFromTranslations($ts);
    }

    public function testFailWithUnknownLocale()
    {
        $ts = (new Translations)->setDomain('default')->setLanguage('en_US');
        $this->configure('de_DE', 'default', []);

        self::expectException(UnsupportedLocaleException::class);
        $this->importer->importFromTranslations($ts);
    }

    public function testSaveTranslations()
    {
        $locale = 'en_US';
        $domain = 'default';

        $this->configure($locale, $domain, []);

        $ts = (new Translations)->setDomain($domain)->setLanguage($locale);
        $t = $ts->insert('', 'O1');

        $this->storage->createOrUpdate($ts);

        $tTranslated = $t->getClone()->setTranslation('T1');
        $ts[] = $tTranslated;

        self::assertEmpty($this->storage->getEnabledTranslated($locale, $domain), 'Nothing is translated');

        $this->importer->importFromTranslations($ts);

        self::assertCount(1, $this->storage->getEnabledTranslated($locale, $domain), 'One translation exists');
    }

    public function testDoesNotRequireTranslationAfterPluralTranslated()
    {
        $locale = 'en_US';
        $domain = 'default';

        $this->configure($locale, $domain, []);

        $ts = (new Translations)->setDomain($domain)->setLanguage($locale);
        $t = $ts->insert('', 'O1', 'P1')->setTranslation('P1');

        $this->storage->createOrUpdate($ts);

        self::assertCount(1,
            $this->storage->getRequiresTranslating($locale, $domain),
            'One string has to be translated'
        );

        $tTranslated = $t->getClone()->setTranslation('T1')->setPluralTranslations(['PT2']);

        $this->storage->createOrUpdateSingle($locale, $domain, $tTranslated);

        self::assertEmpty(
            $this->storage->getRequiresTranslating($locale, $domain),
            'Nothing has to be translated'
        );
    }

    private function configure(string $locale, string $defaultDomain, array $otherDomains)
    {
        $this->config->shouldReceive('getLocales')->andReturn([$locale]);
        $this->config->shouldReceive('getOtherDomains')->andReturn($otherDomains);
        $this->config->shouldReceive('getDefaultDomain')->andReturn($defaultDomain);
    }
}