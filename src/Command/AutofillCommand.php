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

namespace MoveElevator\ComposerTranslationDeepl\Command;

use DeepL\DeepLException;
use MoveElevator\ComposerTranslationDeepl\Enum\TranslationFormat;
use MoveElevator\ComposerTranslationDeepl\Service\{DeepLBatchTranslator, TranslationService};
use MoveElevator\ComposerTranslationValidator\FileDetector\Collector;
use MoveElevator\ComposerTranslationValidator\Validator\MismatchValidator;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\{ProgressBar, QuestionHelper};
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_slice;
use function count;
use function dirname;
use function in_array;
use function is_array;
use function is_file;
use function sprintf;

/**
 * AutofillCommand.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-3.0-or-later
 */
class AutofillCommand extends Command
{
    private readonly TranslationService $translationService;

    public function __construct()
    {
        parent::__construct('autofill');
        $this->translationService = new TranslationService();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Auto-fill missing translations using DeepL API')
            ->addArgument(
                'path',
                InputArgument::OPTIONAL,
                'Path to translation file or directory',
                'translations/',
            )
            ->addOption(
                'source-locale',
                's',
                InputOption::VALUE_REQUIRED,
                'Source locale',
                'en',
            )
            ->addOption(
                'target-locales',
                't',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Target locales (can be used multiple times)',
            )
            ->addOption(
                'api-key',
                'k',
                InputOption::VALUE_REQUIRED,
                'DeepL API key (or use DEEPL_API_KEY env variable)',
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED,
                'Translation file format (xliff, yaml, json, php)',
                'xliff',
            )
            ->addOption(
                'domain',
                null,
                InputOption::VALUE_REQUIRED,
                'Translation domain',
                'messages',
            )
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Simulate without writing files',
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Overwrite files without confirmation',
            )
            ->addOption(
                'no-mark-auto-translated',
                null,
                InputOption::VALUE_NONE,
                'Do not mark translations with XLIFF state (needs-review-translation)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);

        $symfonyStyle->title('Composer Translation DeepL Autofill');

        // Get API key
        $apiKey = $input->getOption('api-key') ?? $_ENV['DEEPL_API_KEY'] ?? getenv('DEEPL_API_KEY');
        if (!$apiKey) {
            $symfonyStyle->error('DeepL API key is required. Provide via --api-key or DEEPL_API_KEY environment variable.');

            return Command::FAILURE;
        }

        // Get target locales
        $targetLocales = $input->getOption('target-locales');
        if (empty($targetLocales)) {
            $symfonyStyle->error('At least one target locale is required. Use -t or --target-locales option.');

            return Command::FAILURE;
        }

        $path = $input->getArgument('path');
        $sourceLocale = $input->getOption('source-locale');
        $domain = $input->getOption('domain');
        $formatString = $input->getOption('format');
        $format = TranslationFormat::tryFrom($formatString) ?? TranslationFormat::XLIFF;
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');
        // Mark as auto-translated by default (can be disabled with --no-mark-auto-translated)
        $markAutoTranslated = !$input->getOption('no-mark-auto-translated');

        if ($dryRun) {
            $symfonyStyle->note('DRY RUN MODE - No files will be modified');
        }

        // Find translation files
        $output->writeln('<fg=cyan>› Scanning for translation files...</>');

        // Check if path is a single file or a directory
        if (is_file($path)) {
            // Single file mode
            if (!$this->matchesFormat($path, $format)) {
                $symfonyStyle->error(sprintf('File does not match format %s: %s', $format->value, $path));

                return Command::FAILURE;
            }

            $files = [$path];
        } else {
            // Directory mode
            $collector = new Collector();
            $filesByParser = $collector->collectFiles([$path], recursive: true);

            if ([] === $filesByParser) {
                $symfonyStyle->error('No translation files found in: '.$path);

                return Command::FAILURE;
            }

            // Flatten files array and filter by format
            $files = [];
            foreach ($filesByParser as $parserFiles) {
                foreach ($parserFiles as $directory => $fileList) {
                    foreach ($fileList as $filename => $filePaths) {
                        if (is_array($filePaths)) {
                            foreach ($filePaths as $filePath) {
                                // Filter by format extension
                                if ($this->matchesFormat($filePath, $format)) {
                                    $files[] = $filePath;
                                }
                            }
                        }
                    }
                }
            }

            if ([] === $files) {
                $symfonyStyle->error(sprintf('No %s translation files found in: %s', $format->value, $path));

                return Command::FAILURE;
            }
        }

        $output->writeln(sprintf('  <fg=cyan>▸</> Found <fg=white>%d</> translation file(s)', count($files)));
        $symfonyStyle->newLine();

        // Validate and find missing translations
        $output->writeln('<fg=cyan>› Validating translations...</>');

        $mismatchValidator = new MismatchValidator();
        $issuesData = $mismatchValidator->validate($files, null);

        $missingKeysByLocale = $this->groupMissingKeysByLocale($issuesData, $targetLocales);

        // Check for missing target files that don't exist yet
        $missingKeysByLocale = $this->detectMissingTargetFiles($files, $sourceLocale, $targetLocales, $domain, $missingKeysByLocale);

        $totalMissingKeys = array_sum(array_map(count(...), $missingKeysByLocale));

        if (0 === $totalMissingKeys) {
            $symfonyStyle->newLine();
            $symfonyStyle->writeln('<fg=green>✓ All translations are complete!</>');

            return Command::SUCCESS;
        }

        $output->writeln(sprintf('  <fg=cyan>▸</> Found <fg=white>%d</> missing translation(s)', $totalMissingKeys));
        $symfonyStyle->newLine();

        if ($dryRun) {
            $this->displayDryRunInfo($symfonyStyle, $missingKeysByLocale);

            return Command::SUCCESS;
        }

        // Confirm file overwrite if needed
        if (!$force && !$this->confirmOverwrite($symfonyStyle, $input, $output)) {
            $symfonyStyle->warning('Operation cancelled by user.');

            return Command::SUCCESS;
        }

        $symfonyStyle->newLine();

        try {
            $deepLBatchTranslator = new DeepLBatchTranslator($apiKey);
        } catch (DeepLException $deeplException) {
            $symfonyStyle->error('Failed to initialize DeepL translator: '.$deeplException->getMessage());

            return Command::FAILURE;
        }

        // Process each target locale
        $totalTranslated = 0;
        $verbose = $output->isVerbose();

        foreach ($missingKeysByLocale as $targetLocale => $missingKeys) {
            if (empty($missingKeys)) {
                continue;
            }

            $output->writeln('');
            $output->writeln(sprintf('<fg=cyan>› Translating to %s (%s)</>', $this->getLanguageName($targetLocale), $targetLocale));

            $sourceFile = $this->findSourceFile($files, $sourceLocale, $domain);
            $targetFile = $this->findTargetFile($files, $targetLocale, $domain, $sourceFile);

            if (!$sourceFile) {
                $symfonyStyle->warning(sprintf('Source file not found for locale %s', $sourceLocale));
                continue;
            }

            // Load catalogues
            $sourceCatalogue = $this->translationService->loadCatalogue($sourceFile, $sourceLocale, $domain);
            $targetCatalogue = $this->translationService->loadCatalogue($targetFile, $targetLocale, $domain);

            // Add all source keys to target catalogue to ensure XLIFF structure is complete
            // Set empty values for missing keys (will be filled by translations)
            // Also copy metadata (including original IDs) from source catalogue
            foreach ($sourceCatalogue->all($domain) as $key => $sourceText) {
                if (!$targetCatalogue->defines($key, $domain)) {
                    $targetCatalogue->set($key, '', $domain);
                }

                // Copy metadata from source to target (preserves original trans-unit IDs)
                $sourceMetadata = $sourceCatalogue->getMetadata($key, $domain);
                if (null !== $sourceMetadata) {
                    $targetMetadata = $targetCatalogue->getMetadata($key, $domain) ?? [];
                    // Preserve existing target metadata but add source ID if not present
                    if (!isset($targetMetadata['id']) && isset($sourceMetadata['id'])) {
                        $targetMetadata['id'] = $sourceMetadata['id'];
                        $targetCatalogue->setMetadata($key, $targetMetadata, $domain);
                    }
                }
            }

            // Filter out empty source texts
            $textsToTranslate = [];
            foreach ($missingKeys as $key) {
                $sourceText = $sourceCatalogue->get($key, $domain);
                if (!in_array(trim($sourceText), ['', '0'], true)) {
                    $textsToTranslate[$key] = $sourceText;
                }
            }

            if ([] === $textsToTranslate) {
                $symfonyStyle->warning('No valid texts to translate (all source texts are empty)');
                continue;
            }

            // Translate with progress bar
            $progressBar = new ProgressBar($output, count($textsToTranslate));
            $progressBar->start();

            try {
                $translations = $deepLBatchTranslator->translateBatch($textsToTranslate, $sourceLocale, $targetLocale);

                // Set translated texts (overwrites source texts from addCatalogue)
                foreach ($translations as $key => $translatedText) {
                    $targetCatalogue->set($key, $translatedText, $domain);

                    if ($verbose) {
                        $progressBar->clear();
                        $output->writeln(sprintf('  <fg=green>•</> <fg=gray>%s:</> <fg=white>"%s"</> → <fg=white>"%s"</>', $key, $textsToTranslate[$key], $translatedText));
                        $progressBar->display();
                    }

                    $progressBar->advance();
                    ++$totalTranslated;
                }

                $progressBar->finish();
                $symfonyStyle->newLine(2);

                // Save catalogue
                $outputPath = dirname($targetFile);
                $this->translationService->saveCatalogue($targetCatalogue, $outputPath, $format, $markAutoTranslated, $sourceLocale, $targetFile);

                $output->writeln(sprintf('  <fg=green>✓</> Translated <fg=white>%d</> key(s)', count($translations)));
                $output->writeln(sprintf('  <fg=cyan>▸</> Saved to: <fg=white>%s</>', basename($targetFile)));
            } catch (DeepLException $e) {
                $progressBar->finish();
                $symfonyStyle->newLine(2);
                $symfonyStyle->error('Translation failed: '.$e->getMessage());

                return Command::FAILURE;
            }
        }

        // Display statistics
        $symfonyStyle->newLine();
        $output->writeln('<fg=green>✓ Translation completed successfully</>');
        $output->writeln(sprintf('  <fg=white>%d</> key(s) translated in <fg=white>%d</> language(s)', $totalTranslated, count($missingKeysByLocale)));

        // Display API usage
        try {
            $usage = $deepLBatchTranslator->getUsage();
            $symfonyStyle->newLine();
            $output->writeln(sprintf(
                '<fg=blue>▸</> API Usage: <fg=white>%s</> / %s characters (<fg=white>%.2f%%</>)',
                number_format($usage['character_count']),
                number_format($usage['character_limit']),
                $usage['percentage'],
            ));
        } catch (DeepLException) {
            // Ignore usage errors
        }

        return Command::SUCCESS;
    }

    /**
     * Detect missing target files that don't exist yet.
     *
     * MismatchValidator only validates files that exist on disk. This method
     * detects target locales that don't have a translation file yet and marks
     * all source keys as missing for those locales.
     *
     * @param array<string>                $files
     * @param array<string>                $targetLocales
     * @param array<string, array<string>> $existingMissingKeys
     *
     * @return array<string, array<string>>
     */
    private function detectMissingTargetFiles(
        array $files,
        string $sourceLocale,
        array $targetLocales,
        string $domain,
        array $existingMissingKeys,
    ): array {
        $sourceFile = $this->findSourceFile($files, $sourceLocale, $domain);
        if (!$sourceFile) {
            return $existingMissingKeys;
        }

        $messageCatalogue = $this->translationService->loadCatalogue($sourceFile, $sourceLocale, $domain);
        $allSourceKeys = array_keys($messageCatalogue->all($domain));

        foreach ($targetLocales as $targetLocale) {
            // Skip if we already found missing keys for this locale
            if (!empty($existingMissingKeys[$targetLocale])) {
                continue;
            }

            $targetFile = $this->findTargetFile($files, $targetLocale, $domain, $sourceFile);

            // If target file doesn't exist, all source keys are missing
            if (!file_exists($targetFile)) {
                $existingMissingKeys[$targetLocale] = $allSourceKeys;
            }
        }

        return $existingMissingKeys;
    }

    /**
     * @param array<string, mixed> $issuesData
     * @param array<string>        $targetLocales
     *
     * @return array<string, array<string>>
     */
    private function groupMissingKeysByLocale(array $issuesData, array $targetLocales): array
    {
        $grouped = [];

        foreach ($targetLocales as $locale) {
            $grouped[$locale] = [];
        }

        // Extract keys and locales from issue data
        foreach ($issuesData as $issueData) {
            if (!is_array($issueData)) {
                continue;
            }

            foreach ($issueData as $issue) {
                if (!is_array($issue)) {
                    continue;
                }

                $key = $issue['key'] ?? null;
                $files = $issue['files'] ?? [];
                if (!$key) {
                    continue;
                }

                if (!is_array($files)) {
                    continue;
                }

                // Determine which locales are missing this key
                foreach ($files as $fileInfo) {
                    if (!is_array($fileInfo)) {
                        continue;
                    }

                    $file = $fileInfo['file'] ?? '';
                    $value = $fileInfo['value'] ?? null;

                    // Extract locale from filename
                    $locale = $this->extractLocaleFromFilename($file);

                    if ($locale && (null === $value || '' === $value) && in_array($locale, $targetLocales, true)) {
                        $grouped[$locale][] = $key;
                    }
                }
            }
        }

        return $grouped;
    }

    /**
     * Extract locale from filename.
     *
     * Supports formats like:
     * - messages.de.xlf (Symfony)
     * - de.locallang.xlf (TYPO3 v10)
     * - locallang.de.xlf (TYPO3 v11+)
     */
    private function extractLocaleFromFilename(string $filename): ?string
    {
        $basename = basename($filename);

        // Match patterns like messages.de.xlf or locallang.de.xlf
        if (preg_match('/\.([a-z]{2})\./', $basename, $matches)) {
            return $matches[1];
        }

        // Match patterns like de.locallang.xlf
        if (preg_match('/^([a-z]{2})\./', $basename, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param array<string> $files
     */
    private function findSourceFile(array $files, string $locale, string $domain): ?string
    {
        // First try to find file with locale in name
        foreach ($files as $file) {
            if ($this->matchesLocaleAndDomain($file, $locale, $domain)) {
                return $file;
            }
        }

        // Fallback: find file without locale in name (treat as source locale)
        foreach ($files as $file) {
            if (null === $this->extractLocaleFromFilename($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * @param array<string> $files
     */
    private function findTargetFile(array $files, string $locale, string $domain, ?string $sourceFile): string
    {
        foreach ($files as $file) {
            if ($this->matchesLocaleAndDomain($file, $locale, $domain)) {
                return $file;
            }
        }

        // Generate target file path based on source file
        if (null === $sourceFile) {
            throw new RuntimeException('Source file is required to generate target file path');
        }

        return $this->generateTargetFilePath($sourceFile, $locale);
    }

    private function matchesLocaleAndDomain(string $file, string $locale, string $domain): bool
    {
        $basename = basename($file);

        // Symfony style: messages.de.xlf
        if (str_contains($basename, sprintf('%s.%s.', $domain, $locale))) {
            return true;
        }

        // TYPO3 style v10: de.locallang.xlf
        return str_contains($basename, sprintf('%s.%s.', $locale, $domain));
    }

    private function generateTargetFilePath(string $sourceFile, string $locale): string
    {
        $dir = dirname($sourceFile);
        $basename = basename($sourceFile);
        $extension = pathinfo($sourceFile, \PATHINFO_EXTENSION);

        // Try to detect naming convention from source file
        // Symfony style: messages.en.xlf → messages.de.xlf
        if (preg_match('/^(.+)\.([a-z]{2})\.([^.]+)$/', $basename, $matches)) {
            $domain = $matches[1];

            return sprintf('%s/%s.%s.%s', $dir, $domain, $locale, $extension);
        }

        // TYPO3 v10 style: de.locallang.xlf → fr.locallang.xlf
        if (preg_match('/^[a-z]{2}\.(.+)$/', $basename, $matches)) {
            $rest = $matches[1];

            return sprintf('%s/%s.%s', $dir, $locale, $rest);
        }

        // File without locale in name: locallang.xlf → locallang.de.xlf (TYPO3 v11+ style)
        $nameWithoutExtension = pathinfo($basename, \PATHINFO_FILENAME);

        return sprintf('%s/%s.%s.%s', $dir, $nameWithoutExtension, $locale, $extension);
    }

    private function confirmOverwrite(SymfonyStyle $symfonyStyle, InputInterface $input, OutputInterface $output): bool
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $confirmationQuestion = new ConfirmationQuestion('Translation files will be modified. Continue? (Y/n) ', true);

        return $helper->ask($input, $output, $confirmationQuestion);
    }

    /**
     * @param array<string, array<string>> $missingKeysByLocale
     */
    private function displayDryRunInfo(SymfonyStyle $symfonyStyle, array $missingKeysByLocale): void
    {
        foreach ($missingKeysByLocale as $locale => $keys) {
            if (empty($keys)) {
                continue;
            }

            $symfonyStyle->section(sprintf('Would translate %d key(s) for locale %s:', count($keys), $locale));
            $symfonyStyle->listing(array_slice($keys, 0, 10));

            if (count($keys) > 10) {
                $symfonyStyle->text(sprintf('... and %d more', count($keys) - 10));
            }
        }
    }

    private function getLanguageName(string $locale): string
    {
        // All languages supported by DeepL API
        $languages = [
            'ar' => 'Arabic',
            'bg' => 'Bulgarian',
            'cs' => 'Czech',
            'da' => 'Danish',
            'de' => 'German',
            'el' => 'Greek',
            'en' => 'English',
            'es' => 'Spanish',
            'et' => 'Estonian',
            'fi' => 'Finnish',
            'fr' => 'French',
            'he' => 'Hebrew',
            'hu' => 'Hungarian',
            'id' => 'Indonesian',
            'it' => 'Italian',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'lt' => 'Lithuanian',
            'lv' => 'Latvian',
            'nb' => 'Norwegian Bokmål',
            'nl' => 'Dutch',
            'pl' => 'Polish',
            'pt' => 'Portuguese',
            'ro' => 'Romanian',
            'ru' => 'Russian',
            'sk' => 'Slovak',
            'sl' => 'Slovenian',
            'sv' => 'Swedish',
            'th' => 'Thai',
            'tr' => 'Turkish',
            'uk' => 'Ukrainian',
            'vi' => 'Vietnamese',
            'zh' => 'Chinese',
        ];

        return $languages[$locale] ?? strtoupper($locale);
    }

    private function matchesFormat(string $file, TranslationFormat $translationFormat): bool
    {
        $extension = pathinfo($file, \PATHINFO_EXTENSION);

        return match ($translationFormat) {
            TranslationFormat::XLIFF => in_array($extension, ['xlf', 'xliff'], true),
            TranslationFormat::YAML => in_array($extension, ['yaml', 'yml'], true),
            TranslationFormat::JSON => 'json' === $extension,
            TranslationFormat::PHP => 'php' === $extension,
        };
    }
}
