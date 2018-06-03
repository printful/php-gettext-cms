<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Printful\GettextCms\Tests\TestCases;

use Gettext\Translation;
use Mockery;
use Mockery\Mock;
use Printful\GettextCms\Interfaces\MessageConfigInterface;
use Printful\GettextCms\LocaleLoader;
use Printful\GettextCms\MessageBuilder;
use Printful\GettextCms\MessageRevisions;
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

    /** @var MessageRevisions */
    private $revisions;

    public function setUp()
    {
        parent::setUp();

        $this->dir = realpath(__DIR__ . '/../assets/temp') . '/generated-translations';

        $this->config = Mockery::mock(MessageConfigInterface::class);
        $this->config->shouldReceive('getMoDirectory')->andReturn($this->dir)->atLeast()->once();

        $this->storage = new MessageStorage(new MessageRepositoryStub);
        $this->revisions = new MessageRevisions($this->config);
        $this->builder = new MessageBuilder($this->config, $this->storage, $this->revisions);
        $this->loader = new LocaleLoader($this->config, $this->revisions);

        $this->deleteDirectory($this->dir);

        mkdir($this->dir);
    }

    protected function tearDown()
    {
        $this->deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function testLoadLocaleAndGetTranslationWithGettext()
    {
        $domain = 'default';
        $locale = 'en_US';

        $this->setConfig($domain, [], false);

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

        $this->setConfig($domain, [], false);

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
        $domainOther = 'domain-other';
        $locale = 'en_US';

        $this->setConfig('default-domain', [$domainOther], false);

        $this->addAndExport($locale, $domainOther, 'Orig', 'Translated');

        $this->loader->load($locale);

        self::assertEquals(
            'Translated',
            dgettext($domainOther, 'Orig'),
            'First translation is returned correctly without revisions'
        );

        $this->addAndExport($locale, $domainOther, 'Orig', 'Updated translation');

        $this->loader->load($locale);

        // Proves that without revisions, gettext caches the first translated string, ignores updated
        self::assertEquals(
            'Translated', // First translation, not the updated one
            dgettext($domainOther, 'Orig'),
            'Old translation is returned, not the updated one'
        );
    }

    public function testGettextCacheBustingWithDomainRevisions()
    {
        $locale = 'en_US';
        $domainMain = 'domain-main';
        $domainOther = 'domain-other';

        $this->setConfig($domainMain, [$domainOther], true);

        $this->addAndExport($locale, $domainMain, 'Original', 'Translated');
        $this->addAndExport($locale, $domainOther, 'Other Original', 'Other Translated');

        $this->loader->load($locale);

        self::assertEquals('Translated', gettext('Original'), 'Main translated');

        $otherRevisionedDomain = $this->revisions->getRevisionedDomain($locale, $domainOther);

        self::assertEquals(
            'Other Translated',
            dgettext($otherRevisionedDomain, 'Other Original'),
            'Other domain was translated'
        );


        $this->addAndExport($locale, $domainMain, 'Original', 'Translated v2');
        $this->addAndExport($locale, $domainOther, 'Other Original', 'Other Translated v2');

        // Re-load so changes take effect
        $this->loader->load($locale);

        $otherRevisionedDomainModified = $this->revisions->getRevisionedDomain($locale, $domainOther);

        self::assertNotEquals($otherRevisionedDomain, $otherRevisionedDomainModified, 'Revision changed');

        self::assertEquals('Translated v2', gettext('Original'), 'Main translated after revision');

        self::assertEquals(
            'Other Translated v2',
            dgettext($otherRevisionedDomainModified, 'Other Original'),
            'Other translated after revision'
        );
    }

    private function addAndExport($locale, $domain, $original, $translation)
    {
        $t = (new Translation('', $original))->setTranslation($translation);
        $this->storage->saveSingleTranslation($locale, $domain, $t);
        $this->builder->export($locale, $domain);
    }

    private function setConfig($domain, array $otherDomains = [], bool $useRevisions = false)
    {
        $this->config->shouldReceive('getDefaultDomain')->andReturn($domain)->atLeast()->once();
        $this->config->shouldReceive('getOtherDomains')->andReturn($otherDomains)->atLeast()->once();
        $this->config->shouldReceive('useRevisions')->andReturn($useRevisions)->atLeast()->once();
    }
}