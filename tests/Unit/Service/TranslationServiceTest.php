<?php

declare(strict_types=1);

/*
 * This file is part of the "composer-translation-deepl" Composer package.
 *
 * (c) 2025 Konrad Michalik <km@move-elevator.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MoveElevator\ComposerTranslationDeepl\Tests\Unit\Service;

use MoveElevator\ComposerTranslationDeepl\Service\TranslationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\MessageCatalogue;

#[CoversClass(TranslationService::class)]
/**
 * TranslationServiceTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license MIT
 */
class TranslationServiceTest extends TestCase
{
    private TranslationService $translationService;

    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->translationService = new TranslationService();
        $this->fixturesPath = __DIR__.'/../../Fixtures/symfony';
    }

    public function testDetectFormatReturnsXliffForXlfExtension(): void
    {
        $format = $this->translationService->detectFormat('messages.en.xlf');
        self::assertSame('xliff', $format);
    }

    public function testDetectFormatReturnsXliffForXliffExtension(): void
    {
        $format = $this->translationService->detectFormat('messages.en.xliff');
        self::assertSame('xliff', $format);
    }

    public function testDetectFormatReturnsYamlForYamlExtension(): void
    {
        $format = $this->translationService->detectFormat('messages.en.yaml');
        self::assertSame('yaml', $format);
    }

    public function testDetectFormatReturnsYamlForYmlExtension(): void
    {
        $format = $this->translationService->detectFormat('messages.en.yml');
        self::assertSame('yaml', $format);
    }

    public function testDetectFormatReturnsJsonForJsonExtension(): void
    {
        $format = $this->translationService->detectFormat('messages.en.json');
        self::assertSame('json', $format);
    }

    public function testDetectFormatReturnsPhpForPhpExtension(): void
    {
        $format = $this->translationService->detectFormat('messages.en.php');
        self::assertSame('php', $format);
    }

    public function testDetectFormatReturnsXliffAsDefault(): void
    {
        $format = $this->translationService->detectFormat('messages.en.unknown');
        self::assertSame('xliff', $format);
    }

    public function testLoadCatalogueReturnsEmptyCatalogueForNonExistentFile(): void
    {
        $catalogue = $this->translationService->loadCatalogue('/non/existent/file.xlf', 'en');

        self::assertSame('en', $catalogue->getLocale());
        self::assertEmpty($catalogue->all());
    }

    public function testLoadCatalogueLoadsXliffFile(): void
    {
        $file = $this->fixturesPath.'/messages.en.xlf';
        $catalogue = $this->translationService->loadCatalogue($file, 'en', 'messages');

        self::assertSame('en', $catalogue->getLocale());
        self::assertTrue($catalogue->has('welcome.message', 'messages'));
        self::assertSame('Welcome', $catalogue->get('welcome.message', 'messages'));
    }

    public function testLoadCatalogueLoadsAllMessages(): void
    {
        $file = $this->fixturesPath.'/messages.en.xlf';
        $catalogue = $this->translationService->loadCatalogue($file, 'en', 'messages');

        $messages = $catalogue->all('messages');
        self::assertCount(3, $messages);
        self::assertArrayHasKey('welcome.message', $messages);
        self::assertArrayHasKey('goodbye.message', $messages);
        self::assertArrayHasKey('button.save', $messages);
    }

    public function testSaveCatalogueCreatesFile(): void
    {
        $tempDir = sys_get_temp_dir().'/composer-translation-deepl-test-'.uniqid();
        mkdir($tempDir, 0777, true);

        try {
            $messageCatalogue = new MessageCatalogue('de');
            $messageCatalogue->set('test.key', 'Test Wert', 'messages');

            $this->translationService->saveCatalogue($messageCatalogue, $tempDir, 'xliff', false);

            $expectedFile = $tempDir.'/messages.de.xlf';
            self::assertFileExists($expectedFile);

            // Verify content
            $content = file_get_contents($expectedFile);
            self::assertIsString($content);
            self::assertStringContainsString('test.key', $content);
            self::assertStringContainsString('Test Wert', $content);
        } finally {
            // Cleanup
            if (is_dir($tempDir)) {
                $files = glob($tempDir.'/*');
                if (false !== $files) {
                    array_map(unlink(...), $files);
                }

                rmdir($tempDir);
            }
        }
    }

    public function testSaveCatalogueWithMarkAutoTranslatedAddsMetadata(): void
    {
        $tempDir = sys_get_temp_dir().'/composer-translation-deepl-test-'.uniqid();
        mkdir($tempDir, 0777, true);

        try {
            $messageCatalogue = new MessageCatalogue('de');
            $messageCatalogue->set('test.key', 'Test Wert', 'messages');

            $this->translationService->saveCatalogue($messageCatalogue, $tempDir, 'xliff', true);

            $expectedFile = $tempDir.'/messages.de.xlf';
            self::assertFileExists($expectedFile);

            // Verify metadata in XLIFF
            $content = file_get_contents($expectedFile);
            self::assertIsString($content);
            self::assertStringContainsString('state="needs-review-translation"', $content);
            self::assertStringContainsString('Auto-translated by DeepL', $content);
            self::assertStringContainsString('deepl-autofill', $content);
        } finally {
            // Cleanup
            if (is_dir($tempDir)) {
                $files = glob($tempDir.'/*');
                if (false !== $files) {
                    array_map(unlink(...), $files);
                }

                rmdir($tempDir);
            }
        }
    }

    public function testSaveCatalogueSupportsYamlFormat(): void
    {
        $tempDir = sys_get_temp_dir().'/composer-translation-deepl-test-'.uniqid();
        mkdir($tempDir, 0777, true);

        try {
            $messageCatalogue = new MessageCatalogue('de');
            $messageCatalogue->set('test.key', 'Test Wert', 'messages');

            $this->translationService->saveCatalogue($messageCatalogue, $tempDir, 'yaml', false);

            // Symfony YAML dumper creates .yml extension, not .yaml
            $expectedFile = $tempDir.'/messages.de.yml';
            self::assertFileExists($expectedFile);
        } finally {
            // Cleanup
            if (is_dir($tempDir)) {
                $files = glob($tempDir.'/*');
                if (false !== $files) {
                    array_map(unlink(...), $files);
                }

                rmdir($tempDir);
            }
        }
    }

    public function testSaveCatalogueSupportsJsonFormat(): void
    {
        $tempDir = sys_get_temp_dir().'/composer-translation-deepl-test-'.uniqid();
        mkdir($tempDir, 0777, true);

        try {
            $messageCatalogue = new MessageCatalogue('de');
            $messageCatalogue->set('test.key', 'Test Wert', 'messages');

            $this->translationService->saveCatalogue($messageCatalogue, $tempDir, 'json', false);

            $expectedFile = $tempDir.'/messages.de.json';
            self::assertFileExists($expectedFile);

            $content = file_get_contents($expectedFile);
            self::assertIsString($content);
            $data = json_decode($content, true);
            self::assertIsArray($data);
            self::assertArrayHasKey('test.key', $data);
            self::assertSame('Test Wert', $data['test.key']);
        } finally {
            // Cleanup
            if (is_dir($tempDir)) {
                $files = glob($tempDir.'/*');
                if (false !== $files) {
                    array_map(unlink(...), $files);
                }

                rmdir($tempDir);
            }
        }
    }

    public function testSaveCatalogueSupportsPhpFormat(): void
    {
        $tempDir = sys_get_temp_dir().'/composer-translation-deepl-test-'.uniqid();
        mkdir($tempDir, 0777, true);

        try {
            $messageCatalogue = new MessageCatalogue('de');
            $messageCatalogue->set('test.key', 'Test Wert', 'messages');

            $this->translationService->saveCatalogue($messageCatalogue, $tempDir, 'php', false);

            $expectedFile = $tempDir.'/messages.de.php';
            self::assertFileExists($expectedFile);
        } finally {
            // Cleanup
            if (is_dir($tempDir)) {
                $files = glob($tempDir.'/*');
                if (false !== $files) {
                    array_map(unlink(...), $files);
                }

                rmdir($tempDir);
            }
        }
    }
}
