<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Printful\GettextCms\Tests\TestCases;

use Gettext\Translation;
use Gettext\Translations;
use Mockery;
use Printful\GettextCms\Interfaces\MessageConfigInterface;
use Printful\GettextCms\MessageArrayRepository;
use Printful\GettextCms\MessageExtractor;
use Printful\GettextCms\MessageStorage;
use Printful\GettextCms\Structures\ScanItem;
use Printful\GettextCms\Tests\TestCase;

class JsMessageTest extends TestCase
{
    /** @var Mockery\Mock|MessageConfigInterface */
    private $mockConfig;

    /** @var MessageExtractor */
    private $scanner;

    /** @var MessageStorage */
    private $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockConfig = Mockery::mock(MessageConfigInterface::class);
        $this->mockConfig->shouldReceive('getDefaultDomain')->andReturn('domain')->byDefault();
        $this->mockConfig->shouldReceive('getOtherDomains')->andReturn([])->byDefault();

        $this->scanner = new MessageExtractor($this->mockConfig);
        $this->storage = new MessageStorage(new MessageArrayRepository());
    }

    public function testExtract()
    {
        $translationsByDomain = $this->scanner->extract([
            new ScanItem($this->getDummyFile('js/dummy.js')), // 1 JS message
            new ScanItem($this->getDummyFile('js/dummy.vue')), // 2 JS messages
            new ScanItem($this->getDummyFile('js/dummy-ts.ts')),
            new ScanItem($this->getDummyFile('dummy-file.php')), // PHP messages, should be ignored
        ]);

        // Translate and store all
        foreach ($translationsByDomain as $v) {
            /** @var Translation[]|Translations $v */
            $v->setLanguage('en_US');
            foreach ($v as $v2) {
                $v2->setTranslation('Translation');
            }
            $this->storage->createOrUpdate($v);
        }

        $translations = $this->storage->getEnabledTranslatedJs('en_US', 'domain');

        self::assertCount(4, $translations, 'The number of translations extracted does not match');
        self::assertNotFalse($translations->find('ctx', 'JS 1'), 'Translation not found');
        self::assertNotFalse($translations->find('', 'JS 2'), 'Translation not found');
        self::assertNotFalse($translations->find('', 'JS 3'), 'Translation not found');
        self::assertNotFalse($translations->find('', 'JS TS'), 'Translation not found');
    }
}