<?php


namespace Printful\GettextCms\Structures;


class ScanItem
{
    /**
     * Path to a directory or a file to scan
     * @var string
     */
    public $path;

    /**
     * File extensions to scan.
     * Missing value means all supported files will be scanned.
     *
     * @var string[]
     */
    public $extensions = null;

    /**
     * List of functions to scan for
     * Format is: [ custom-function => gettext-function, ..]
     * For example: [translate => gettext, translatePlural => ngettext]
     *
     * Default values can be found here, for example:
     * @see \Gettext\Extractors\JsCode::$options
     * @see \Gettext\Extractors\PhpCode::$options

     *
     * @var array If null, we use default functions from Gettext library (standard gettext functions)
     */
    public $functions = null;

    /**
     * Scan directory recursively
     * @var bool
     */
    public $recursive;

    /**
     * @param string $path Path to a directory or a file to scan
     * @param array $extensions List of extensions to scan for
     * @param bool $recursive Scan directory recursively
     * @param array|null $functions
     *
     * @see ScanItem::$functions
     */
    public function __construct($path, array $extensions = null, $recursive = true, array $functions = null)
    {
        $this->path = $path;
        $this->extensions = $extensions;
        $this->recursive = $recursive;
        $this->functions = $functions;
    }
}