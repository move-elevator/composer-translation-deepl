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

use MoveElevator\ComposerTranslationDeepl\Enum\TranslationFormat;
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
        $translationFormat = $this->translationService->detectFormat('messages.en.xlf');
        self::assertSame(TranslationFormat::XLIFF, $translationFormat);
    }

    public function testDetectFormatReturnsXliffForXliffExtension(): void
    {
        $translationFormat = $this->translationService->detectFormat('messages.en.xliff');
        self::assertSame(TranslationFormat::XLIFF, $translationFormat);
    }

    public function testDetectFormatReturnsYamlForYamlExtension(): void
    {
        $translationFormat = $this->translationService->detectFormat('messages.en.yaml');
        self::assertSame(TranslationFormat::YAML, $translationFormat);
    }

    public function testDetectFormatReturnsYamlForYmlExtension(): void
    {
        $translationFormat = $this->translationService->detectFormat('messages.en.yml');
        self::assertSame(TranslationFormat::YAML, $translationFormat);
    }

    public function testDetectFormatReturnsJsonForJsonExtension(): void
    {
        $translationFormat = $this->translationService->detectFormat('messages.en.json');
        self::assertSame(TranslationFormat::JSON, $translationFormat);
    }

    public function testDetectFormatReturnsPhpForPhpExtension(): void
    {
        $translationFormat = $this->translationService->detectFormat('messages.en.php');
        self::assertSame(TranslationFormat::PHP, $translationFormat);
    }

    public function testDetectFormatReturnsXliffAsDefault(): void
    {
        $translationFormat = $this->translationService->detectFormat('messages.en.unknown');
        self::assertSame(TranslationFormat::XLIFF, $translationFormat);
    }

    public function testLoadCatalogueReturnsEmptyCatalogueForNonExistentFile(): void
    {
        $messageCatalogue = $this->translationService->loadCatalogue('/non/existent/file.xlf', 'en');

        self::assertSame('en', $messageCatalogue->getLocale());
        self::assertEmpty($messageCatalogue->all());
    }

    public function testLoadCatalogueLoadsXliffFile(): void
    {
        $file = $this->fixturesPath.'/messages.en.xlf';
        $messageCatalogue = $this->translationService->loadCatalogue($file, 'en', 'messages');

        self::assertSame('en', $messageCatalogue->getLocale());
        self::assertTrue($messageCatalogue->has('welcome.message', 'messages'));
        self::assertSame('Welcome', $messageCatalogue->get('welcome.message', 'messages'));
    }

    public function testLoadCatalogueLoadsAllMessages(): void
    {
        $file = $this->fixturesPath.'/messages.en.xlf';
        $messageCatalogue = $this->translationService->loadCatalogue($file, 'en', 'messages');

        $messages = $messageCatalogue->all('messages');
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

            $this->translationService->saveCatalogue($messageCatalogue, $tempDir, TranslationFormat::XLIFF, false);

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

            $this->translationService->saveCatalogue($messageCatalogue, $tempDir, TranslationFormat::XLIFF, true);

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

            $this->translationService->saveCatalogue($messageCatalogue, $tempDir, TranslationFormat::YAML, false);

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

            $this->translationService->saveCatalogue($messageCatalogue, $tempDir, TranslationFormat::JSON, false);

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

            $this->translationService->saveCatalogue($messageCatalogue, $tempDir, TranslationFormat::PHP, false);

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
