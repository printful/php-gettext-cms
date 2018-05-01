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
     * Scan directory recursively
     * @var bool
     */
    public $recursive;

    /**
     * @param string $path Path to a directory or a file to scan
     * @param array $extensions List of extensions to scan for
     * @param bool $recursive Scan directory recursively
     */
    public function __construct($path, array $extensions = [], $recursive = true)
    {
        $this->path = $path;
        $this->extensions = $extensions;
        $this->recursive = $recursive;
    }
}