<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Printful\GettextCms\Tests\TestCases;

use Gettext\Generators\Po;
use Gettext\Translations;
use Mockery;
use Mockery\Mock;
use Printful\GettextCms\Interfaces\MessageConfigInterface;
use Printful\GettextCms\MessageArrayRepository;
use Printful\GettextCms\MessageManager;
use Printful\GettextCms\Structures\ScanItem;
use Printful\GettextCms\Tests\TestCase;

class MessageManagerTest extends TestCase
{
    /** @var MessageConfigInterface|Mock $config */
    private $config;

    /** @var string */
    private $tempDir;

    /** @var string */
    private $zipPathname;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = Mockery::mock(MessageConfigInterface::class);

        // Make a temp directory for MO files
        $this->tempDir = realpath(__DIR__ . '/../assets/temp') . '/manager';
        $this->zipPathname = $this->tempDir . '/' . 'export.zip';

        $this->cleanup();
        @mkdir($this->tempDir);
    }

    private function cleanup()
    {
        $this->deleteDirectory($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    public function testWholeFlow()
    {
        $dOther = 'other';
        $dDefault = 'default';
        $dDynamic = 'dynamic';

        $this->config->shouldReceive('getDefaultLocale')->andReturn('en_US')->atLeast()->once();
        $this->config->shouldReceive('getLocales')->andReturn(['en_US', 'de_DE'])->atLeast()->once();
        $this->config->shouldReceive('getOtherDomains')->andReturn([$dOther, $dDynamic])->atLeast()->once();
        $this->config->shouldReceive('getDefaultDomain')->andReturn($dDefault)->atLeast()->once();
        $this->config->shouldReceive('getMoDirectory')->andReturn($this->tempDir)->atLeast()->once();
        $this->config->shouldReceive('useRevisions')->andReturn(true)->atLeast()->once();
        $this->config->shouldReceive('useShortFunctions')->andReturn(true)->atLeast()->once();
        $this->config->shouldReceive('getRepository')->andReturn(new MessageArrayRepository())->atLeast()->once();

        $manager = MessageManager::init($this->config);

        // Add untranslated messages to repository
        $manager->extractAndSaveFromFiles([
            new ScanItem(__DIR__ . '/../assets/dummy-directory-2/custom-functions.php'),
        ]);

        // Add untranslated dynamic messages
        $manager->getDynamicMessageImporter()
            ->add('d1')
            ->add('d2 ctx', 'ctx')
            ->saveAndDisabledPrevious($dDynamic);

        // Export zip
        $manager->exportUntranslatedPoZip($this->zipPathname, 'de_DE');
        self::assertFileExists($this->zipPathname, 'Zip file is stored');

        // Translate default domain and import translations
        $ts = Translations::fromPoString($manager->exportUntranslatedPo('de_DE', $dDefault));
        $ts->find('', '_')->setTranslation('_ t');
        $ts->find('ctx', '_c')->setTranslation('_c t');
        $ts->find('', '_n')->setTranslation('_n t')->setPluralTranslations(['_n pt']);
        $ts->find('ctx', '_nc')->setTranslation('_nc t')->setPluralTranslations(['_nc pt']);
        $manager->importTranslated(Po::toString($ts));

        // Translate other domain and import translations
        $ts = Translations::fromPoString($manager->exportUntranslatedPo('de_DE', $dOther));
        $ts->find('', '_d')->setTranslation('_d t');
        $ts->find('ctx', '_dc')->setTranslation('_dc t');
        $ts->find('', '_dn')->setTranslation('_dn t')->setPluralTranslations(['_dn pt']);
        $ts->find('ctx', '_dnc')->setTranslation('_dnc t')->setPluralTranslations(['_dnc pt']);
        $manager->importTranslated(Po::toString($ts));

        // Translate dynamic messages
        $ts = Translations::fromPoString($manager->exportUntranslatedPo('de_DE', $dDynamic));
        $ts->find('', 'd1')->setTranslation('d1 t');
        $ts->find('ctx', 'd2 ctx')->setTranslation('d2 ctx t');
        $manager->importTranslated(Po::toString($ts));

        $manager->buildTranslationFiles();

        $manager->setLocale('de_DE');

        // Verify all default domain translations
        self::assertEquals('_ t', _('_'), 'Correct default translation');
        self::assertEquals('_c t', _c('ctx', '_c'), 'Default ctx');
        self::assertEquals('_n t', _n('_n', '_n p', 1), 'Default singular');
        self::assertEquals('_n pt', _n('_n', '_n p', 2), 'Default plural');
        self::assertEquals('_nc t', _nc('ctx', '_nc', '_nc p', 1), 'Default ctx singular');
        self::assertEquals('_nc pt', _nc('ctx', '_nc', '_nc p', 2), 'Default ctx plural');

        // Verify all other domain translations
        self::assertEquals('_d t', _d($dOther, '_d'), 'Other');
        self::assertEquals('_dc t', _dc($dOther, 'ctx', '_dc'), 'Other ctx');
        self::assertEquals('_dn t', _dn($dOther, '_dn', '_dn p', 1), 'Other singular');
        self::assertEquals('_dn pt', _dn($dOther, '_dn', '_dn p', 2), 'Other plural');
        self::assertEquals('_dnc t', _dnc($dOther, 'ctx', '_dnc', '_dnc p', 1), 'Other ctx singular');
        self::assertEquals('_dnc pt', _dnc($dOther, 'ctx', '_dnc', '_dnc p', 2), 'Other ctx plural');

        // Verify dynamic messages
        self::assertEquals('d1 t', _d($dDynamic, 'd1'), 'Dynamic');
        self::assertEquals('d2 ctx t', _dc($dDynamic, 'ctx', 'd2 ctx'), 'Dynamic ctx');
    }
}