<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Printful\GettextCms\Tests\Builder;

use Gettext\Extractors\Mo;
use Gettext\Translations;
use Mockery;
use Mockery\Mock;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Printful\GettextCms\Exceptions\InvalidPathException;
use Printful\GettextCms\Interfaces\MessageConfigInterface;
use Printful\GettextCms\Interfaces\MessageRepositoryInterface;
use Printful\GettextCms\MessageBuilder;
use Printful\GettextCms\MessageStorage;
use Printful\GettextCms\Structures\MessageItem;
use Printful\GettextCms\Tests\TestCase;

class BuilderTest extends TestCase
{
    /** @var Mock|MessageConfigInterface */
    private $mockConfig;

    /** @var Mock|MessageRepositoryInterface */
    private $mockRepository;

    /** @var MessageBuilder */
    private $exporter;

    /** @var vfsStreamDirectory */
    private $root;

    protected function setUp()
    {
        parent::setUp();

        $this->root = vfsStream::setup('virtualMoDirectory/');

        $this->mockConfig = Mockery::mock(MessageConfigInterface::class);
        $this->mockRepository = Mockery::mock(MessageRepositoryInterface::class);
        $this->exporter = new MessageBuilder($this->mockConfig, new MessageStorage($this->mockRepository));
    }

    public function testExportToMoFile()
    {
        $locale = 'lv_LV';
        $domain = 'custom-domain';

        /** @var MessageItem[] $messages */
        $messages = [
            $this->getMessageItem('O1', 'T1', ''),
            $this->getMessageItem('O2', 'T2', ''),
            $this->getMessageItem('O3', 'T3', 'CTX3'),
        ];

        $this->mockRepository->shouldReceive('getAll')->with($locale, $domain)->andReturn($messages)->once();

        $dir = $this->root->url();

        $expectedPath = $locale . '/LC_MESSAGES/' . $domain . '.mo';

        self::assertFalse($this->root->hasChild($expectedPath), 'Mo files does not exist');

        $this->mockConfig->shouldReceive('getMoDirectory')->andReturn($dir)->once();

        $this->exporter->export($locale, $domain);

        self::assertTrue($this->root->hasChild($expectedPath), 'Mo file was created');

        $this->verifyTranslations($messages, $locale, $domain, $this->root->url() . '/' . $expectedPath);
    }

    public function testExceptionOnMissingDir()
    {
        $this->mockRepository->shouldReceive('getAll')->andReturn([
            $this->getMessageItem('Original', 'Translation', ''),
        ])->once();

        $this->mockConfig->shouldReceive('getMoDirectory')->andReturn('missing-directory')->once();

        self::expectException(InvalidPathException::class);
        $this->exporter->export('en_US', 'domain');
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

    private function getMessageItem($original, $translation, $context): MessageItem
    {
        $i = new MessageItem;

        $i->original = $original;
        $i->translation = $translation;
        $i->context = $context;

        return $i;
    }
}