<?php
/** @noinspection PhpUnhandledExceptionInspection */


namespace Printful\GettextCms\Tests\Extractor;


use Printful\GettextCms\Exceptions\UnknownExtractorException;
use Printful\GettextCms\MessageExtractor;
use Printful\GettextCms\Structures\ScanItem;
use Printful\GettextCms\Tests\TestCase;

class MessageExtractionTest extends TestCase
{
    /** @var MessageExtractor */
    private $scanner;

    protected function setUp()
    {
        parent::setUp();
        $this->scanner = new MessageExtractor;
    }

    public function testUnknownExtractorFails()
    {
        self::expectException(UnknownExtractorException::class);

        $this->scanner->extract([
            new ScanItem(__DIR__ . '/../assets/dummy-directory/sub-directory/text-file.txt', ['txt']),
        ]);
    }

    public function testExtractDefaultDomain()
    {
        $allTranslations = $this->scanner->extract([
            new ScanItem($this->getDummyFile()),
        ]);

        $translations = array_pop($allTranslations);

        self::assertEquals(2, $translations->count(), 'Scanned message count matches');
        self::assertEquals('', $translations->getDomain(), 'Domain is empty');

        $messages = array_map(function (\Gettext\Translation $t) {
            return $t->getOriginal();
        }, (array)$translations);

        sort($messages);

        self::assertContains('default-message-1', $messages, 'First message is present');
        self::assertContains('default-message-2', $messages, 'Second message is present');
    }

    public function testDomainOnlySearch()
    {
        $domain = 'custom-domain';

        $items = [
            new ScanItem($this->getDummyFile()),
        ];

        $allTranslations = $this->scanner->extract($items, false, [$domain]);

        self::assertCount(1, $allTranslations, 'One domain only scanned');

        $translations = array_pop($allTranslations);

        $messages = array_map(function (\Gettext\Translation $t) {
            return $t->getOriginal();
        }, (array)$translations);

        self::assertEquals(1, $translations->count(), 'One message exists');

        self::assertEquals($domain, $translations->getDomain(), 'Domain matches');
        self::assertContains('custom-domain-message', $messages, 'Domain message is present');
    }

    /**
     * @return string
     */
    private function getDummyFile(): string
    {
        return __DIR__ . '/../assets/dummy-directory/dummy-file.php';
    }
}