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

namespace MoveElevator\ComposerTranslationDeepl;

use MoveElevator\ComposerTranslationDeepl\Command\AutofillCommand;
use Symfony\Component\Console\Application as BaseApplication;

/**
 * Application.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
class Application extends BaseApplication
{
    private const NAME = 'Composer Translation DeepL Autofill';

    private const VERSION = '0.1.0';

    public function __construct()
    {
        parent::__construct(self::NAME, self::VERSION);

        // @phpstan-ignore function.alreadyNarrowedType (method only exists in Symfony Console 8+)
        if (method_exists($this, 'addCommand')) {
            $this->addCommand(new AutofillCommand());
        } else {
            // @phpstan-ignore method.notFound (Symfony Console < 8)
            $this->add(new AutofillCommand());
        }

        $this->setDefaultCommand('autofill', true);
    }
}
