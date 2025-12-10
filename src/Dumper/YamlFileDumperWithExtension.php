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

namespace MoveElevator\ComposerTranslationDeepl\Dumper;

use Symfony\Component\Translation\Dumper\YamlFileDumper;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * YamlFileDumperWithExtension.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
class YamlFileDumperWithExtension extends YamlFileDumper
{
    private string $fileExtension = 'yml';

    public function setExtension(string $extension): void
    {
        $this->fileExtension = $extension;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function dump(MessageCatalogue $messages, array $options = []): void
    {
        // If extension is provided in options, use it
        if (isset($options['extension'])) {
            $this->setExtension($options['extension']);
        }

        parent::dump($messages, $options);
    }

    protected function getExtension(): string
    {
        return $this->fileExtension;
    }
}
