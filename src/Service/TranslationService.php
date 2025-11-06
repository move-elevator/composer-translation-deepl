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
    private const FORMAT_XLIFF = 'xliff';

    private const FORMAT_YAML = 'yaml';

    private const FORMAT_JSON = 'json';

    private const FORMAT_PHP = 'php';

    public function loadCatalogue(string $file, string $locale, string $domain = 'messages'): MessageCatalogue
    {
        if (!file_exists($file)) {
            return new MessageCatalogue($locale);
        }

        $format = $this->detectFormat($file);
        $loader = $this->getLoader($format);

        return $loader->load($file, $locale, $domain);
    }

    public function saveCatalogue(
        MessageCatalogue $messageCatalogue,
        string $outputPath,
        string $format = self::FORMAT_XLIFF,
        bool $markAutoTranslated = false,
    ): void {
        if ($markAutoTranslated && self::FORMAT_XLIFF === $format) {
            $this->markTranslationsAsAutoTranslated($messageCatalogue);
        }

        $dumper = $this->getDumper($format);
        $dumper->dump($messageCatalogue, ['path' => $outputPath]);
    }

    public function detectFormat(string $file): string
    {
        $extension = pathinfo($file, \PATHINFO_EXTENSION);

        return match ($extension) {
            'xlf', 'xliff' => self::FORMAT_XLIFF,
            'yaml', 'yml' => self::FORMAT_YAML,
            'json' => self::FORMAT_JSON,
            'php' => self::FORMAT_PHP,
            default => self::FORMAT_XLIFF,
        };
    }

    private function getLoader(string $format): XliffFileLoader|YamlFileLoader|JsonFileLoader|PhpFileLoader
    {
        return match ($format) {
            self::FORMAT_XLIFF => new XliffFileLoader(),
            self::FORMAT_YAML => new YamlFileLoader(),
            self::FORMAT_JSON => new JsonFileLoader(),
            self::FORMAT_PHP => new PhpFileLoader(),
            default => new XliffFileLoader(),
        };
    }

    private function getDumper(string $format): XliffFileDumper|YamlFileDumper|JsonFileDumper|PhpFileDumper
    {
        return match ($format) {
            self::FORMAT_XLIFF => new XliffFileDumper(),
            self::FORMAT_YAML => new YamlFileDumper(),
            self::FORMAT_JSON => new JsonFileDumper(),
            self::FORMAT_PHP => new PhpFileDumper(),
            default => new XliffFileDumper(),
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
