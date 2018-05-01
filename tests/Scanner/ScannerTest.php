<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Printful\GettextCms\Tests\Scanner;

use Printful\GettextCms\Exceptions\InvalidPathException;
use Printful\GettextCms\Structures\ScanItem;
use Printful\GettextCms\Tests\TestCase;
use Printful\GettextCms\TranslationScanner;

class ScannerTest extends TestCase
{
    /** @var TranslationScanner */
    private $scanner;

    protected function setUp()
    {
        parent::setUp();
        $this->scanner = new TranslationScanner;
    }

    public function testExceptionOnInvalidPath()
    {
        self::expectException(InvalidPathException::class);

        $this->scanner->resolveFiles([new ScanItem('invalid-path')]);
    }

    public function testFilesAreResolved()
    {
        $dir = $this->getAssetPath() . '/dummy-directory';

        $files = $this->scanner->resolveFiles([new ScanItem($dir, ['php'])]);

        self::assertCount(3, $files, 'Found php file count matches');

        $files = $this->scanner->resolveFiles([new ScanItem($dir, ['txt'])]);
        self::assertCount(1, $files, 'One txt file is found');
    }

    public function testMultiDirectoriesFound()
    {
        $dir = $this->getAssetPath();

        $files = $this->scanner->resolveFiles([
            new ScanItem($dir . '/dummy-directory/sub-directory', ['php']),
            new ScanItem($dir . '/dummy-directory/sub-directory2/', ['php']),
        ]);

        self::assertCount(2, $files, 'Two files are found in separate directories');
    }

    /**
     * @return string
     */
    private function getAssetPath(): string
    {
        return __DIR__ . '/../assets';
    }
}