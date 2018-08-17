<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Printful\GettextCms\Tests\TestCases;

use Gettext\Translation;
use Gettext\Translations;
use Mockery;
use Mockery\Mock;
use Printful\GettextCms\Exceptions\InvalidPathException;
use Printful\GettextCms\Interfaces\MessageConfigInterface;
use Printful\GettextCms\MessageRevisions;
use Printful\GettextCms\Tests\TestCase;

class RevisionTest extends TestCase
{
    /** @var Mock|MessageConfigInterface */
    private $config;

    /** @var string */
    private $tempDir;

    public function setUp()
    {
        parent::setUp();

        $this->config = Mockery::mock(MessageConfigInterface::class);

        // Set a fake default locale so all tests will work as if their locale is not the default
        $this->config->shouldReceive('getDefaultLocale')->andReturn('xx_XX')->byDefault();

        $this->tempDir = realpath(__DIR__ . '/../assets/temp') . '/revisions';

        $this->deleteDirectory($this->tempDir);
        mkdir($this->tempDir);
    }

    protected function tearDown()
    {
        $this->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    public function testHashGeneration()
    {
        $revisions = new MessageRevisions($this->config);

        $ts = new Translations;
        $t = (new Translation('ctx', 'O'))->setTranslation('T');
        $ts[] = $t;

        $hash1 = $revisions->generateRevisionedDomain('domain', $ts);

        $t->setPlural('P')->setPluralTranslations(['PT']);

        $hash2 = $revisions->generateRevisionedDomain('domain', $ts);

        self::assertNotEquals($hash1, $hash2, 'Hashes are different');

        $t->setPlural('')->setPluralTranslations([]); // Unset plurals

        self::assertEquals($hash1, $revisions->generateRevisionedDomain('domain', $ts), 'Hash is equal again');
    }

    public function testSettingAndReadingRevisions()
    {
        $revisions = new MessageRevisions($this->config);

        $this->config->shouldReceive('getMoDirectory')->andReturn($this->tempDir);
        $this->config->shouldReceive('useRevisions')->andReturn(true);

        $domain = 'domain';
        $domainRevisioned = 'domain-1234';
        $locale = 'lv_LV';

        self::assertEquals(
            $domain,
            $revisions->getRevisionedDomain($locale, $domain),
            'No revision exists, return same'
        );

        $revisions->saveRevision($locale, $domain, $domainRevisioned);

        $retrieved = (new MessageRevisions($this->config))->getRevisionedDomain($locale, $domain);

        self::assertEquals($domainRevisioned, $retrieved, 'Retrieved domain matches revisioned');
    }

    public function testFailWithMissingDirectory()
    {
        $this->config->shouldReceive('getMoDirectory')->andReturn('random-path');

        $revisions = new MessageRevisions($this->config);

        self::expectException(InvalidPathException::class);

        $revisions->saveRevision('en_US', 'domain', 'domain-2');
    }

    public function testDefaultLocaleIsNotRevisioned()
    {
        $this->config->shouldReceive('getDefaultLocale')->andReturn('en_EN')->byDefault();
        $this->config->shouldReceive('getMoDirectory')->andReturn($this->tempDir)->byDefault();

        $revisions = new MessageRevisions($this->config);

        $revisions->saveRevision('en_EN', 'domain', 'domain-12345');

        self::assertEquals(
            'domain',
            $revisions->getRevisionedDomain('en_EN', 'domain'),
            'Non-revisioned domain returned'
        );
    }
}