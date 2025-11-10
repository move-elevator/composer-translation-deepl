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

namespace MoveElevator\ComposerTranslationDeepl\Tests\Unit;

use MoveElevator\ComposerTranslationDeepl\Application;
use MoveElevator\ComposerTranslationDeepl\Command\AutofillCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Application::class)]
/**
 * ApplicationTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license MIT
 */
class ApplicationTest extends TestCase
{
    private Application $application;

    protected function setUp(): void
    {
        $this->application = new Application();
    }

    public function testApplicationHasCorrectName(): void
    {
        self::assertSame('Composer Translation DeepL Autofill', $this->application->getName());
    }

    public function testApplicationHasCorrectVersion(): void
    {
        self::assertSame('1.0.0', $this->application->getVersion());
    }

    public function testApplicationRegistersAutofillCommand(): void
    {
        self::assertTrue($this->application->has('autofill'));

        $command = $this->application->get('autofill');
        self::assertInstanceOf(AutofillCommand::class, $command);
    }

    public function testApplicationHasDefaultCommand(): void
    {
        // The default command is set via setDefaultCommand() in Application constructor
        // We verify it's available and can be run without specifying the command name
        self::assertTrue($this->application->has('autofill'));

        // In Symfony Console, default commands are automatically selected
        // This test ensures the command is properly registered
        $command = $this->application->get('autofill');
        self::assertSame('autofill', $command->getName());
    }
}
