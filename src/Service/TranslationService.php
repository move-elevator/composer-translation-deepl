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

namespace MoveElevator\ComposerTranslationDeepl\Service;

use MoveElevator\ComposerTranslationDeepl\Enum\TranslationFormat;
use Symfony\Component\Translation\Dumper\{JsonFileDumper, PhpFileDumper, XliffFileDumper, YamlFileDumper};
use Symfony\Component\Translation\Loader\{JsonFileLoader, PhpFileLoader, XliffFileLoader, YamlFileLoader};
use Symfony\Component\Translation\MessageCatalogue;

/**
 * TranslationService.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license MIT
 */
class TranslationService
{
    public function loadCatalogue(string $file, string $locale, string $domain = 'messages'): MessageCatalogue
    {
        if (!file_exists($file)) {
            return new MessageCatalogue($locale);
        }

        $translationFormat = $this->detectFormat($file);

        return $this->getLoader($translationFormat)->load($file, $locale, $domain);
    }

    public function saveCatalogue(
        MessageCatalogue $messageCatalogue,
        string $outputPath,
        TranslationFormat $translationFormat = TranslationFormat::XLIFF,
        bool $markAutoTranslated = false,
    ): void {
        if ($markAutoTranslated && TranslationFormat::XLIFF === $translationFormat) {
            $this->markTranslationsAsAutoTranslated($messageCatalogue);
        }

        $dumper = $this->getDumper($translationFormat);
        $dumper->dump($messageCatalogue, ['path' => $outputPath]);
    }

    public function detectFormat(string $file): TranslationFormat
    {
        $extension = pathinfo($file, \PATHINFO_EXTENSION);

        return match ($extension) {
            'yaml', 'yml' => TranslationFormat::YAML,
            'json' => TranslationFormat::JSON,
            'php' => TranslationFormat::PHP,
            default => TranslationFormat::XLIFF,
        };
    }

    private function getLoader(TranslationFormat $translationFormat): XliffFileLoader|YamlFileLoader|JsonFileLoader|PhpFileLoader
    {
        return match ($translationFormat) {
            TranslationFormat::XLIFF => new XliffFileLoader(),
            TranslationFormat::YAML => new YamlFileLoader(),
            TranslationFormat::JSON => new JsonFileLoader(),
            TranslationFormat::PHP => new PhpFileLoader(),
        };
    }

    private function getDumper(TranslationFormat $translationFormat): XliffFileDumper|YamlFileDumper|JsonFileDumper|PhpFileDumper
    {
        return match ($translationFormat) {
            TranslationFormat::XLIFF => new XliffFileDumper(),
            TranslationFormat::YAML => new YamlFileDumper(),
            TranslationFormat::JSON => new JsonFileDumper(),
            TranslationFormat::PHP => new PhpFileDumper(),
        };
    }

    private function markTranslationsAsAutoTranslated(MessageCatalogue $messageCatalogue): void
    {
        foreach ($messageCatalogue->all() as $domain => $messages) {
            foreach (array_keys($messages) as $key) {
                $messageCatalogue->setMetadata((string) $key, [
                    'target-attributes' => ['state' => 'needs-review-translation'],
                    'notes' => [
                        [
                            'content' => 'Auto-translated by DeepL',
                            'from' => 'deepl-autofill',
                        ],
                    ],
                ], $domain);
            }
        }
    }
}
