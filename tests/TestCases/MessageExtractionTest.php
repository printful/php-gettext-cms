<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Printful\GettextCms\Tests\TestCases;

use Gettext\Translation;
use Gettext\Translations;
use Mockery;
use Printful\GettextCms\Exceptions\UnknownExtractorException;
use Printful\GettextCms\Interfaces\MessageConfigInterface;
use Printful\GettextCms\MessageExtractor;
use Printful\GettextCms\Structures\ScanItem;
use Printful\GettextCms\Tests\TestCase;

class MessageExtractionTest extends TestCase
{
    /** @var MessageExtractor */
    private $scanner;

    /** @var Mockery\Mock|MessageConfigInterface */
    private $mockConfig;

    const DOMAIN_DEFAULT = 'default';

    protected function setUp()
    {
        parent::setUp();

        $this->mockConfig = Mockery::mock(MessageConfigInterface::class);
        $this->setDefaultDomain(self::DOMAIN_DEFAULT);
        $this->setOtherDomains([]);

        $this->scanner = new MessageExtractor($this->mockConfig);
    }

    public function testUnknownExtractorFails()
    {
        self::expectException(UnknownExtractorException::class);

        $this->scanner->extract(
            [
                new ScanItem(__DIR__ . '/../assets/dummy-directory/sub-directory/text-file.txt', ['txt']),
            ]
        );
    }

    public function testExtractDefaultDomain()
    {
        $allTranslations = $this->scanner->extract(
            [
                new ScanItem($this->getDummyFile()),
            ]
        );

        $translations = array_pop($allTranslations);

        self::assertEquals(2, $translations->count(), 'Scanned message count matches');
        self::assertEquals(self::DOMAIN_DEFAULT, $translations->getDomain(), 'Domain is empty');

        $messages = array_map(
            function (Translation $t) {
                return $t->getOriginal();
            },
            (array)$translations
        );

        sort($messages);

        self::assertContains('default-message-1', $messages, 'First message is present');
        self::assertContains('default-message-2', $messages, 'Second message is present');
    }

    public function testDomainOnlySearch()
    {
        $domain = 'custom-domain';

        $this->setOtherDomains([$domain]);

        $items = [
            new ScanItem($this->getDummyFile()),
        ];

        $allTranslations = $this->scanner->extract($items);

        self::assertCount(2, $allTranslations, 'Two domains scanned, default and custom');

        // Find translation for our domain
        /** @var Translations $domainTranslations */
        $domainTranslations = null;

        foreach ($allTranslations as $v) {
            if ($v->getDomain() === $domain) {
                $domainTranslations = $v;
            }
        }

        self::assertEquals(1, $domainTranslations->count(), 'One message exists');

        self::assertEquals($domain, $domainTranslations->getDomain(), 'Domain matches');

        $message = $domainTranslations->find('', 'custom-domain-message');

        self::assertInstanceOf(Translation::class, $message, 'Message was found');
    }

    public function testExtractSpecificFunctions()
    {
        $allTranslations = $this->scanner->extract(
            [
                new ScanItem($this->getDummyFile(), null, true, ['my_custom_function' => 'gettext']),
            ]
        );

        $translations = array_shift($allTranslations);

        $translation = $translations->find('', 'custom function translation');

        self::assertEquals(['Extracted comment'], $translation->getExtractedComments(), 'Comment was extracted');
        self::assertNotFalse($translation, 'Translation was found');
        self::assertCount(1, $translations, 'Only one translation was found for custom function');
    }

    public function testExtractOnlySpecificDomain()
    {
        $translations = $this->scanner->extract(
            [
                new ScanItem($this->getDummyFile('multi-domains.php', 'mixed-domains')),
            ],
            ['domain1', 'domain3']
        );

        $domainsFound = array_map(
            function (Translations $t) {
                return $t->getDomain() . '|' . $t->count();
            },
            $translations
        );

        self::assertContains('domain1|2', $domainsFound, 'Domain 1 was found with two strings');
        self::assertContains('domain3|1', $domainsFound, 'Domain 3 was found with one string');
        self::assertCount(2, $domainsFound, 'Two domains are found in total');
    }

    private function setDefaultDomain(string $domain)
    {
        $this->mockConfig->shouldReceive('getDefaultDomain')->andReturn($domain)->byDefault();
    }

    private function setOtherDomains(array $domains)
    {
        $this->mockConfig->shouldReceive('getOtherDomains')->andReturn($domains)->byDefault();
    }
}