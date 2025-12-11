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

use MoveElevator\ComposerTranslationDeepl\Dumper\{XliffFileDumperWithEmptySource, YamlFileDumperWithExtension};
use MoveElevator\ComposerTranslationDeepl\Enum\TranslationFormat;
use MoveElevator\ComposerTranslationDeepl\Loader\XliffFileLoaderWithId;
use Symfony\Component\Translation\Dumper\{JsonFileDumper, PhpFileDumper, XliffFileDumper, YamlFileDumper};
use Symfony\Component\Translation\Loader\{JsonFileLoader, PhpFileLoader, YamlFileLoader};
use Symfony\Component\Translation\MessageCatalogue;

use function sprintf;

/**
 * TranslationService.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
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
        ?string $sourceLocale = null,
        ?string $targetFile = null,
    ): void {
        if ($markAutoTranslated && TranslationFormat::XLIFF === $translationFormat) {
            $this->markTranslationsAsAutoTranslated($messageCatalogue, $sourceLocale);
        }

        $dumper = $this->getDumper($translationFormat);

        // If a specific target file is provided, write directly to that file
        if (null !== $targetFile) {
            $domains = $messageCatalogue->getDomains();
            $domain = $domains[0] ?? 'messages';
            $content = $dumper->formatCatalogue($messageCatalogue, $domain, [
                'default_locale' => $sourceLocale ?? $messageCatalogue->getLocale(),
            ]);
            file_put_contents($targetFile, $content);

            return;
        }

        $options = ['path' => $outputPath];
        $dumper->dump($messageCatalogue, $options);
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

    private function getLoader(TranslationFormat $translationFormat): XliffFileLoaderWithId|YamlFileLoader|JsonFileLoader|PhpFileLoader
    {
        return match ($translationFormat) {
            TranslationFormat::XLIFF => new XliffFileLoaderWithId(),
            TranslationFormat::YAML => new YamlFileLoader(),
            TranslationFormat::JSON => new JsonFileLoader(),
            TranslationFormat::PHP => new PhpFileLoader(),
        };
    }

    private function getDumper(TranslationFormat $translationFormat): XliffFileDumper|YamlFileDumper|JsonFileDumper|PhpFileDumper
    {
        return match ($translationFormat) {
            TranslationFormat::XLIFF => new XliffFileDumperWithEmptySource(),
            TranslationFormat::YAML => new YamlFileDumperWithExtension(),
            TranslationFormat::JSON => new JsonFileDumper(),
            TranslationFormat::PHP => new PhpFileDumper(),
        };
    }

    private function markTranslationsAsAutoTranslated(MessageCatalogue $messageCatalogue, ?string $sourceLocale = null): void
    {
        $targetLocale = $messageCatalogue->getLocale();
        $noteContent = null !== $sourceLocale
            ? sprintf('Auto-translated by DeepL (%s → %s)', $sourceLocale, $targetLocale)
            : 'Auto-translated by DeepL';

        foreach ($messageCatalogue->all() as $domain => $messages) {
            foreach (array_keys($messages) as $key) {
                $metadata = $messageCatalogue->getMetadata((string) $key, $domain) ?: [];
                $metadata['target-attributes'] = ['state' => 'needs-review-translation'];
                $metadata['notes'] = [
                    [
                        'content' => $noteContent,
                        'from' => 'composer-translation-deepl',
                    ],
                ];
                $messageCatalogue->setMetadata((string) $key, $metadata, $domain);
            }
        }
    }
}
