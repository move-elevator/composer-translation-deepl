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
use function sprintf;

/**
 * AutofillCommand.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license MIT
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
                'Path to translation files directory',
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
                'mark-auto-translated',
                'm',
                InputOption::VALUE_NONE,
                'Mark translations with XLIFF state (needs-review-translation)',
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
        $format = $input->getOption('format');
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');
        $markAutoTranslated = $input->getOption('mark-auto-translated');

        if ($dryRun) {
            $symfonyStyle->note('DRY RUN MODE - No files will be modified');
        }

        // Find translation files
        $symfonyStyle->text('Finding translation files...');
        $collector = new Collector();
        $filesByParser = $collector->collectFiles([$path], recursive: true);

        if ([] === $filesByParser) {
            $symfonyStyle->error('No translation files found in: '.$path);

            return Command::FAILURE;
        }

        // Flatten files array
        $files = [];
        foreach ($filesByParser as $parserFiles) {
            foreach ($parserFiles as $filePath => $data) {
                $files[] = $filePath;
            }
        }

        $symfonyStyle->success(sprintf('Found %d translation file(s)', count($files)));

        // Validate and find missing translations
        $symfonyStyle->text('Validating translations...');

        $mismatchValidator = new MismatchValidator();
        $issuesData = $mismatchValidator->validate($files, null);

        $missingKeysByLocale = $this->groupMissingKeysByLocale($issuesData, $targetLocales);

        $totalMissingKeys = array_sum(array_map(count(...), $missingKeysByLocale));

        if (0 === $totalMissingKeys) {
            $symfonyStyle->success('All translations are complete!');

            return Command::SUCCESS;
        }

        $symfonyStyle->success(sprintf('Found %d missing translation(s)', $totalMissingKeys));

        if ($dryRun) {
            $this->displayDryRunInfo($symfonyStyle, $missingKeysByLocale);

            return Command::SUCCESS;
        }

        // Confirm file overwrite if needed
        if (!$force && !$this->confirmOverwrite($symfonyStyle, $input, $output)) {
            $symfonyStyle->warning('Operation cancelled by user.');

            return Command::SUCCESS;
        }

        // Initialize DeepL translator
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

            $symfonyStyle->section(sprintf('Translating to %s (%s)', $this->getLanguageName($targetLocale), $targetLocale));

            $sourceFile = $this->findSourceFile($files, $sourceLocale, $domain);
            $targetFile = $this->findTargetFile($files, $targetLocale, $domain, $sourceFile);

            if (!$sourceFile) {
                $symfonyStyle->warning(sprintf('Source file not found for locale %s', $sourceLocale));
                continue;
            }

            // Load catalogues
            $sourceCatalogue = $this->translationService->loadCatalogue($sourceFile, $sourceLocale, $domain);
            $targetCatalogue = $this->translationService->loadCatalogue($targetFile, $targetLocale, $domain);

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

                foreach ($translations as $key => $translatedText) {
                    $targetCatalogue->set($key, $translatedText, $domain);

                    if ($verbose) {
                        $progressBar->clear();
                        $symfonyStyle->text(sprintf('  ✓ %s: "%s" → "%s"', $key, $textsToTranslate[$key], $translatedText));
                        $progressBar->display();
                    }

                    $progressBar->advance();
                    ++$totalTranslated;
                }

                $progressBar->finish();
                $symfonyStyle->newLine(2);

                // Save catalogue
                $outputPath = dirname($targetFile);
                $this->translationService->saveCatalogue($targetCatalogue, $outputPath, $format, $markAutoTranslated);

                $symfonyStyle->success(sprintf('Translated %d key(s) for locale %s', count($translations), $targetLocale));
            } catch (DeepLException $e) {
                $progressBar->finish();
                $symfonyStyle->newLine(2);
                $symfonyStyle->error('Translation failed: '.$e->getMessage());

                return Command::FAILURE;
            }
        }

        // Display statistics
        $symfonyStyle->newLine();
        $symfonyStyle->success(sprintf('Successfully translated %d key(s) in %d language(s)', $totalTranslated, count($missingKeysByLocale)));

        // Display API usage
        try {
            $usage = $deepLBatchTranslator->getUsage();
            $symfonyStyle->text(sprintf(
                '✓ API Usage: %s / %s characters (%.2f%%)',
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

                    if ($locale && null === $value && in_array($locale, $targetLocales, true)) {
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
        foreach ($files as $file) {
            if ($this->matchesLocaleAndDomain($file, $locale, $domain)) {
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
        if (preg_match('/^([^.]+)\.([a-z]{2})\.(.+)$/', $basename, $matches)) {
            $domain = $matches[1];

            return sprintf('%s/%s.%s.%s', $dir, $domain, $locale, $extension);
        }

        // TYPO3 style: locallang.xlf → de.locallang.xlf or locallang.de.xlf
        // Default to TYPO3 v11+ style
        $name = pathinfo($sourceFile, \PATHINFO_FILENAME);

        return sprintf('%s/%s.%s.%s', $dir, $name, $locale, $extension);
    }

    private function confirmOverwrite(SymfonyStyle $symfonyStyle, InputInterface $input, OutputInterface $output): bool
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $confirmationQuestion = new ConfirmationQuestion('Translation files will be modified. Continue? (y/n) ', false);

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
        $languages = [
            'de' => 'German',
            'fr' => 'French',
            'es' => 'Spanish',
            'it' => 'Italian',
            'nl' => 'Dutch',
            'pl' => 'Polish',
            'pt' => 'Portuguese',
            'ru' => 'Russian',
            'ja' => 'Japanese',
            'zh' => 'Chinese',
        ];

        return $languages[$locale] ?? strtoupper($locale);
    }
}
