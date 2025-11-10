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

namespace MoveElevator\ComposerTranslationDeepl\Enum;

/**
 * TranslationFormat.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license MIT
 */
enum TranslationFormat: string
{
    case XLIFF = 'xliff';
    case YAML = 'yaml';
    case JSON = 'json';
    case PHP = 'php';
}
