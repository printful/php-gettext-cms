<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Printful\GettextCms\Tests\TestCases;

use Mockery;
use Printful\GettextCms\Exceptions\InvalidPathException;
use Printful\GettextCms\Interfaces\MessageConfigInterface;
use Printful\GettextCms\MessageExtractor;
use Printful\GettextCms\Structures\ScanItem;
use Printful\GettextCms\Tests\TestCase;

class FileResolvingTest extends TestCase
{
    /** @var MessageExtractor */
    private $scanner;

    protected function setUp()
    {
        parent::setUp();

        /** @var MessageConfigInterface $config */
        $config = Mockery::mock(MessageConfigInterface::class);

        $this->scanner = new MessageExtractor($config);
    }

    public function testExceptionOnInvalidPath()
    {
        self::expectException(InvalidPathException::class);

        $this->scanner->resolvePathnames(new ScanItem('invalid-path'));
    }

    public function testFilesAreResolved()
    {
        $dir = $this->getAssetPath() . '/dummy-directory';

        $files = $this->scanner->resolvePathnames(new ScanItem($dir, ['php']));

        self::assertCount(3, $files, 'Found php file count matches');

        $files = $this->scanner->resolvePathnames(new ScanItem($dir, ['txt']));
        self::assertCount(1, $files, 'One txt file is found');
    }

    public function testMultiDirectoriesFound()
    {
        $dir = $this->getAssetPath();

        $files1 = $this->scanner->resolvePathnames(
            new ScanItem($dir . '/dummy-directory/sub-directory', ['php'])
        );

        $files2 = $this->scanner->resolvePathnames(
            new ScanItem($dir . '/dummy-directory/sub-directory2/')
        );

        $files = array_merge($files1, $files2);

        self::assertCount(2, $files, 'Two files are found in separate directories');
    }

    public function testSingleFileResolving()
    {
        $files = $this->scanner->resolvePathnames(
            new ScanItem($this->getAssetPath() . '/dummy-directory/dummy-file.php')
        );

        self::assertCount(1, $files, 'One file is found');
    }

    public function testNonRecursiveScan()
    {
        $files = $this->scanner->resolvePathnames(
            new ScanItem($this->getAssetPath() . '/dummy-directory', [], false)
        );

        self::assertCount(1, $files, 'One file is found non-recursively');
    }

    /**
     * @return string
     */
    private function getAssetPath(): string
    {
        return __DIR__ . '/../assets';
    }
}