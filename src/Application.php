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
 * @license MIT
 */
class Application extends BaseApplication
{
    private const NAME = 'Composer Translation DeepL Autofill';

    private const VERSION = '1.0.0';

    public function __construct()
    {
        parent::__construct(self::NAME, self::VERSION);

        $this->add(new AutofillCommand());
        $this->setDefaultCommand('autofill', true);
    }
}
