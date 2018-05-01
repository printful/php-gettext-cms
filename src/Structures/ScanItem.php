<?php


namespace Printful\GettextCms\Structures;


class ScanItem
{
    /**
     * Path to directory to a directory or a file to scan
     * @var string
     */
    public $path;

    /**
     * File extensions to scan.
     * Empty directory means all supported files will be scanned.
     *
     * @var string[]
     */
    public $extensions = [];

    /**
     * @param string $path Path to a directory or a file to scan
     * @param array $extensions List of extensions to scan for
     */
    public function __construct($path, array $extensions = [])
    {
        $this->path = $path;
        $this->extensions = $extensions;
    }
}