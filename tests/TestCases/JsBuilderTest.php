<?php

namespace Printful\GettextCms\Tests\TestCases;

use Gettext\Translation;
use Printful\GettextCms\MessageArrayRepository;
use Printful\GettextCms\MessageJsBuilder;
use Printful\GettextCms\MessageStorage;
use Printful\GettextCms\Tests\TestCase;

class JsBuilderTest extends TestCase
{
    private $locale;

    /** @var MessageStorage */
    private $storage;

    /** @var MessageJsBuilder */
    private $builder;

    protected function setUp()
    {
        parent::setUp();

        $this->storage = new MessageStorage(new MessageArrayRepository());
        $this->builder = new MessageJSBuilder($this->storage);
    }

    public function testExportJsonp()
    {
        $this->locale = 'en_US';

        $this->add('D1', true, 'D1-O1', 'D1-T1');
        $this->add('D1', true, 'D1-O2', 'D1-T2');
        $this->add('D1', false, 'non-js', 'non-js'); // Non-js translation
        $this->add('D2', true, 'D2-O1', 'D2-T2');

        $domains = ['D1', 'D2'];

        $js = $this->builder->exportJsonp($this->locale, $domains, 'loaded');

        self::assertNotContains('non-js', $js, 'Non-js translation is not included');

        foreach ($domains as $domain) {
            $ts = $this->storage->getEnabledTranslatedJs($this->locale, $domain);

            self::assertTrue(count($ts) > 0, 'Domain ' . $domain . ' has translations');

            foreach ($ts as $t) {
                /** @var Translation $t */
                self::assertContains($t->getOriginal(), $js, $t->getOriginal() . ' is within JS');
                self::assertContains($t->getTranslation(), $js, $t->getTranslation() . ' translation is within JS');
            }
        }
    }

    public function testEmptyDomainIsNotExported()
    {
        $this->locale = 'en_US';
        $domains = ['D1'];

        $js = $this->builder->exportJsonp($this->locale, $domains, 'loaded');

        self::assertEmpty($js, 'JS string is empty, because domain is empty');
    }

    private function add(string $domain, bool $isJs, string $original, string $translation, string $context = ''): self
    {
        $translation = (new Translation($context, $original))->setTranslation($translation);

        if ($isJs) {
            $translation->addReference('file.js', 1);
        }

        $this->storage->createOrUpdateSingle(
            $this->locale,
            $domain,
            $translation
        );

        return $this;
    }
}