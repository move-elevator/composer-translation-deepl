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

namespace MoveElevator\ComposerTranslationDeepl\Tests\Unit\Command;

use MoveElevator\ComposerTranslationDeepl\Command\AutofillCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(AutofillCommand::class)]

/**
 * AutofillCommandTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license MIT
 */

class AutofillCommandTest extends TestCase
{
    private AutofillCommand $autofillCommand;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->autofillCommand = new AutofillCommand();

        $application = new Application();
        $application->add($this->autofillCommand);

        $this->commandTester = new CommandTester($this->autofillCommand);
    }

    public function testCommandHasCorrectName(): void
    {
        self::assertSame('autofill', $this->autofillCommand->getName());
    }

    public function testCommandHasDescription(): void
    {
        $description = $this->autofillCommand->getDescription();
        self::assertNotEmpty($description);
        self::assertStringContainsString('Auto-fill', $description);
    }

    public function testCommandFailsWithoutApiKey(): void
    {
        $this->commandTester->execute([
            'path' => __DIR__.'/../../Fixtures/symfony',
            '--target-locales' => ['de'],
        ]);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('DeepL API key is required', $output);
        self::assertSame(1, $this->commandTester->getStatusCode());
    }

    public function testCommandFailsWithoutTargetLocales(): void
    {
        putenv('DEEPL_API_KEY=test-key:fx');

        try {
            $this->commandTester->execute([
                'path' => __DIR__.'/../../Fixtures/symfony',
            ]);

            $output = $this->commandTester->getDisplay();
            self::assertStringContainsString('At least one target locale is required', $output);
            self::assertSame(1, $this->commandTester->getStatusCode());
        } finally {
            putenv('DEEPL_API_KEY');
        }
    }

    public function testCommandFailsWithNonExistentPath(): void
    {
        putenv('DEEPL_API_KEY=test-key:fx');

        try {
            $this->commandTester->execute([
                'path' => '/non/existent/path',
                '--target-locales' => ['de'],
            ]);

            $output = $this->commandTester->getDisplay();
            self::assertStringContainsString('No translation files found', $output);
            self::assertSame(1, $this->commandTester->getStatusCode());
        } finally {
            putenv('DEEPL_API_KEY');
        }
    }

    public function testCommandSupportsPathArgument(): void
    {
        $definition = $this->autofillCommand->getDefinition();

        self::assertTrue($definition->hasArgument('path'));

        $argument = $definition->getArgument('path');
        self::assertFalse($argument->isRequired());
        self::assertSame('translations/', $argument->getDefault());
    }

    public function testCommandSupportsSourceLocaleOption(): void
    {
        $definition = $this->autofillCommand->getDefinition();

        self::assertTrue($definition->hasOption('source-locale'));

        $option = $definition->getOption('source-locale');
        self::assertTrue($option->isValueRequired());
        self::assertSame('en', $option->getDefault());
    }

    public function testCommandSupportsTargetLocalesOption(): void
    {
        $definition = $this->autofillCommand->getDefinition();

        self::assertTrue($definition->hasOption('target-locales'));

        $option = $definition->getOption('target-locales');
        self::assertTrue($option->isValueRequired());
        self::assertTrue($option->isArray());
    }

    public function testCommandSupportsApiKeyOption(): void
    {
        $definition = $this->autofillCommand->getDefinition();

        self::assertTrue($definition->hasOption('api-key'));

        $option = $definition->getOption('api-key');
        self::assertTrue($option->isValueRequired());
    }

    public function testCommandSupportsFormatOption(): void
    {
        $definition = $this->autofillCommand->getDefinition();

        self::assertTrue($definition->hasOption('format'));

        $option = $definition->getOption('format');
        self::assertTrue($option->isValueRequired());
        self::assertSame('xliff', $option->getDefault());
    }

    public function testCommandSupportsDomainOption(): void
    {
        $definition = $this->autofillCommand->getDefinition();

        self::assertTrue($definition->hasOption('domain'));

        $option = $definition->getOption('domain');
        self::assertTrue($option->isValueRequired());
        self::assertSame('messages', $option->getDefault());
    }

    public function testCommandSupportsDryRunOption(): void
    {
        $definition = $this->autofillCommand->getDefinition();

        self::assertTrue($definition->hasOption('dry-run'));

        $option = $definition->getOption('dry-run');
        self::assertFalse($option->acceptValue());
    }

    public function testCommandSupportsForceOption(): void
    {
        $definition = $this->autofillCommand->getDefinition();

        self::assertTrue($definition->hasOption('force'));

        $option = $definition->getOption('force');
        self::assertFalse($option->acceptValue());
    }

    public function testCommandSupportsMarkAutoTranslatedOption(): void
    {
        $definition = $this->autofillCommand->getDefinition();

        self::assertTrue($definition->hasOption('mark-auto-translated'));

        $option = $definition->getOption('mark-auto-translated');
        self::assertFalse($option->acceptValue());
    }

    public function testCommandSupportsVerboseOption(): void
    {
        // Verbose is a default Symfony option available in the application, not command definition
        $application = new Application();
        $application->add($this->autofillCommand);

        $inputDefinition = $application->getDefinition();
        self::assertTrue($inputDefinition->hasOption('verbose'));
    }

    public function testCommandSupportsQuietOption(): void
    {
        // Quiet is a default Symfony option available in the application, not command definition
        $application = new Application();
        $application->add($this->autofillCommand);

        $inputDefinition = $application->getDefinition();
        self::assertTrue($inputDefinition->hasOption('quiet'));
    }

    public function testCommandReadsApiKeyFromEnvironment(): void
    {
        // Test that DEEPL_API_KEY env var is recognized
        putenv('DEEPL_API_KEY=test-key-from-env:fx');

        try {
            $this->commandTester->execute([
                'path' => '/non/existent/path',
                '--target-locales' => ['de'],
            ]);

            // Should fail on path not found, not API key missing
            $output = $this->commandTester->getDisplay();
            self::assertStringNotContainsString('API key is required', $output);
        } finally {
            putenv('DEEPL_API_KEY');
        }
    }

    public function testExtractLocaleFromFilenameWithSymfonyStyle(): void
    {
        $reflectionClass = new ReflectionClass($this->autofillCommand);
        $reflectionMethod = $reflectionClass->getMethod('extractLocaleFromFilename');

        $locale = $reflectionMethod->invoke($this->autofillCommand, 'messages.de.xlf');
        self::assertSame('de', $locale);
    }

    public function testExtractLocaleFromFilenameWithTypo3V10Style(): void
    {
        $reflectionClass = new ReflectionClass($this->autofillCommand);
        $reflectionMethod = $reflectionClass->getMethod('extractLocaleFromFilename');

        $locale = $reflectionMethod->invoke($this->autofillCommand, 'de.locallang.xlf');
        self::assertSame('de', $locale);
    }

    public function testExtractLocaleFromFilenameWithTypo3V11Style(): void
    {
        $reflectionClass = new ReflectionClass($this->autofillCommand);
        $reflectionMethod = $reflectionClass->getMethod('extractLocaleFromFilename');

        $locale = $reflectionMethod->invoke($this->autofillCommand, 'locallang.de.xlf');
        self::assertSame('de', $locale);
    }

    public function testExtractLocaleFromFilenameReturnsNullForInvalidFilename(): void
    {
        $reflectionClass = new ReflectionClass($this->autofillCommand);
        $reflectionMethod = $reflectionClass->getMethod('extractLocaleFromFilename');

        $locale = $reflectionMethod->invoke($this->autofillCommand, 'invalid-filename.xlf');
        self::assertNull($locale);
    }

    public function testGetLanguageNameReturnsCorrectNames(): void
    {
        $reflectionClass = new ReflectionClass($this->autofillCommand);
        $reflectionMethod = $reflectionClass->getMethod('getLanguageName');

        self::assertSame('German', $reflectionMethod->invoke($this->autofillCommand, 'de'));
        self::assertSame('French', $reflectionMethod->invoke($this->autofillCommand, 'fr'));
        self::assertSame('Spanish', $reflectionMethod->invoke($this->autofillCommand, 'es'));
        self::assertSame('Italian', $reflectionMethod->invoke($this->autofillCommand, 'it'));
    }

    public function testGetLanguageNameReturnsUppercaseLocaleForUnknown(): void
    {
        $reflectionClass = new ReflectionClass($this->autofillCommand);
        $reflectionMethod = $reflectionClass->getMethod('getLanguageName');

        self::assertSame('XX', $reflectionMethod->invoke($this->autofillCommand, 'xx'));
    }
}
