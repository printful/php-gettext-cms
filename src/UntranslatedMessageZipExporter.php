<?php

namespace Printful\GettextCms;

use InvalidArgumentException;
use Printful\GettextCms\Interfaces\MessageConfigInterface;
use ZipArchive;

/**
 * Class exports multiple domain untranslated PO files as a zip archive
 */
class UntranslatedMessageZipExporter
{
    /** @var UntranslatedMessageExporter */
    private $exporter;

    /** @var MessageConfigInterface */
    private $config;

    public function __construct(MessageConfigInterface $config, UntranslatedMessageExporter $exporter)
    {
        $this->exporter = $exporter;
        $this->config = $config;
    }

    /**
     * Create a zip archive with messages that require translations
     *
     * @param string $zipPathname Full pathname to the file where the ZIP archive should be written
     * @param string $locale
     * @param string[]|null $domains Domains to export. If not provided, export all domains in config
     * @return bool
     */
    public function export(string $zipPathname, string $locale, array $domains = null): bool
    {
        $dir = dirname($zipPathname);

        if (!is_dir($dir)) {
            throw new InvalidArgumentException('Directory does not exist: ' . $dir);
        }

        $zip = new ZipArchive();
        $zip->open($zipPathname, ZipArchive::CREATE);

        if (empty($domains)) {
            $domains = $this->config->getOtherDomains();
            $domains[] = $this->config->getDefaultDomain();
        }

        foreach ($domains as $domain) {
            $po = $this->exporter->exportPoString($locale, $domain);
            if ($po) {
                $zip->addFromString($locale . '-' . $domain . '.po', $po);
            }
        }

        $zip->close();

        return true;
    }
}