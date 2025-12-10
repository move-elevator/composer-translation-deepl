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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(AutofillCommand::class)]
/**
 * AutofillCommandTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
class AutofillCommandTest extends TestCase
{
    private AutofillCommand $autofillCommand;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->autofillCommand = new AutofillCommand();

        $application = new Application();
        $this->addCommandToApplication($application, $this->autofillCommand);

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
        $inputDefinition = $this->autofillCommand->getDefinition();

        self::assertTrue($inputDefinition->hasArgument('path'));

        $inputArgument = $inputDefinition->getArgument('path');
        self::assertFalse($inputArgument->isRequired());
        self::assertSame('translations/', $inputArgument->getDefault());
    }

    public function testCommandSupportsSourceLocaleOption(): void
    {
        $inputDefinition = $this->autofillCommand->getDefinition();

        self::assertTrue($inputDefinition->hasOption('source-locale'));

        $inputOption = $inputDefinition->getOption('source-locale');
        self::assertTrue($inputOption->isValueRequired());
        self::assertSame('en', $inputOption->getDefault());
    }

    public function testCommandSupportsTargetLocalesOption(): void
    {
        $inputDefinition = $this->autofillCommand->getDefinition();

        self::assertTrue($inputDefinition->hasOption('target-locales'));

        $inputOption = $inputDefinition->getOption('target-locales');
        self::assertTrue($inputOption->isValueRequired());
        self::assertTrue($inputOption->isArray());
    }

    public function testCommandSupportsApiKeyOption(): void
    {
        $inputDefinition = $this->autofillCommand->getDefinition();

        self::assertTrue($inputDefinition->hasOption('api-key'));

        $inputOption = $inputDefinition->getOption('api-key');
        self::assertTrue($inputOption->isValueRequired());
    }

    public function testCommandSupportsFormatOption(): void
    {
        $inputDefinition = $this->autofillCommand->getDefinition();

        self::assertTrue($inputDefinition->hasOption('format'));

        $inputOption = $inputDefinition->getOption('format');
        self::assertTrue($inputOption->isValueRequired());
        self::assertSame('xliff', $inputOption->getDefault());
    }

    public function testCommandSupportsDomainOption(): void
    {
        $inputDefinition = $this->autofillCommand->getDefinition();

        self::assertTrue($inputDefinition->hasOption('domain'));

        $inputOption = $inputDefinition->getOption('domain');
        self::assertTrue($inputOption->isValueRequired());
        self::assertSame('messages', $inputOption->getDefault());
    }

    public function testCommandSupportsDryRunOption(): void
    {
        $inputDefinition = $this->autofillCommand->getDefinition();

        self::assertTrue($inputDefinition->hasOption('dry-run'));

        $inputOption = $inputDefinition->getOption('dry-run');
        self::assertFalse($inputOption->acceptValue());
    }

    public function testCommandSupportsForceOption(): void
    {
        $inputDefinition = $this->autofillCommand->getDefinition();

        self::assertTrue($inputDefinition->hasOption('force'));

        $inputOption = $inputDefinition->getOption('force');
        self::assertFalse($inputOption->acceptValue());
    }

    public function testCommandSupportsNoMarkAutoTranslatedOption(): void
    {
        $inputDefinition = $this->autofillCommand->getDefinition();

        self::assertTrue($inputDefinition->hasOption('no-mark-auto-translated'));

        $inputOption = $inputDefinition->getOption('no-mark-auto-translated');
        self::assertFalse($inputOption->acceptValue());
    }

    public function testCommandSupportsVerboseOption(): void
    {
        // Verbose is a default Symfony option available in the application, not command definition
        $application = new Application();
        $this->addCommandToApplication($application, $this->autofillCommand);

        $inputDefinition = $application->getDefinition();
        self::assertTrue($inputDefinition->hasOption('verbose'));
    }

    public function testCommandSupportsQuietOption(): void
    {
        // Quiet is a default Symfony option available in the application, not command definition
        $application = new Application();
        $this->addCommandToApplication($application, $this->autofillCommand);

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

    private function addCommandToApplication(Application $application, Command $command): void
    {
        // @phpstan-ignore function.alreadyNarrowedType (method only exists in Symfony Console 8+)
        if (method_exists($application, 'addCommand')) {
            $application->addCommand($command);
        } else {
            // @phpstan-ignore method.notFound (Symfony Console < 8)
            $application->add($command);
        }
    }
}
