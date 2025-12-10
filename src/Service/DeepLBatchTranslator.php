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

use DeepL\{DeepLException, Translator};

/**
 * DeepLBatchTranslator.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
class DeepLBatchTranslator
{
    private const BATCH_SIZE = 50;

    private readonly Translator $translator;

    public function __construct(string $apiKey)
    {
        $this->translator = new Translator($apiKey);
    }

    /**
     * Translates an array of texts in batches.
     *
     * @param array<string, string> $texts        Array with keys as identifiers and values as texts to translate
     * @param string                $sourceLocale Source language code (e.g., 'en')
     * @param string                $targetLocale Target language code (e.g., 'de')
     *
     * @return array<string, string> Array with keys and translated texts
     *
     * @throws DeepLException
     */
    public function translateBatch(array $texts, string $sourceLocale, string $targetLocale): array
    {
        if ([] === $texts) {
            return [];
        }

        $keys = array_keys($texts);
        $values = array_values($texts);
        $chunks = array_chunk($values, self::BATCH_SIZE, false);
        $keyChunks = array_chunk($keys, self::BATCH_SIZE, false);

        $allTranslations = [];

        foreach ($chunks as $index => $chunk) {
            $results = $this->translator->translateText(
                $chunk,
                $sourceLocale,
                $targetLocale,
            );

            // translateText always returns an array for array input
            foreach ($results as $i => $result) {
                $key = $keyChunks[$index][$i];
                $allTranslations[$key] = $result->text;
            }
        }

        return $allTranslations;
    }

    /**
     * Translates a single text.
     *
     * @param string $text         Text to translate
     * @param string $sourceLocale Source language code
     * @param string $targetLocale Target language code
     *
     * @return string Translated text
     *
     * @throws DeepLException
     */
    public function translate(string $text, string $sourceLocale, string $targetLocale): string
    {
        $textResult = $this->translator->translateText($text, $sourceLocale, $targetLocale);

        return $textResult->text;
    }

    /**
     * Gets the current API usage statistics.
     *
     * @return array{character_count: int, character_limit: int, percentage: float}
     *
     * @throws DeepLException
     */
    public function getUsage(): array
    {
        $usage = $this->translator->getUsage();

        $characterCount = $usage->character->count ?? 0;
        $characterLimit = $usage->character->limit ?? 0;
        $percentage = $characterLimit > 0 ? ($characterCount / $characterLimit) * 100 : 0;

        return [
            'character_count' => $characterCount,
            'character_limit' => $characterLimit,
            'percentage' => round($percentage, 2),
        ];
    }
}
