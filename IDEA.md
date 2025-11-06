# Proof of Concept: DeepL Translation Autofill Package

## Projektübersicht

Ein **schlankes, modernes Composer-Package**, das automatisch fehlende Übersetzungen in Translation-Dateien mittels DeepL API ergänzt.

**Kern-Prinzip:** Eine Aufgabe, einfach gemacht.

**Haupt-Anwendungsfall:** TYPO3 Extensions (XLIFF) - mit Support für Symfony/Laravel (YAML, JSON, PHP).

## Design-Prinzipien (KISS)

✅ **Fokus**: Fehlende Übersetzungen automatisch ergänzen - nichts mehr
✅ **Minimal**: Nur 3-4 Kern-Klassen, keine Over-Engineering
✅ **Nachhaltig**: Nutzt Symfony Translation (Loader/Dumper) - keine Eigenentwicklungen
✅ **Wartbar**: Kleine, testbare Komponenten
✅ **Erweiterbar**: Phase 2 Features als separate Packages (nicht im Core!)

## Architektur (2 Kern-Komponenten!)

```
src/
├── Plugin.php                     # Composer Plugin (10 Zeilen)
└── Command/
    └── AutofillCommand.php        # Hauptlogik (ca. 120 Zeilen)
```

**Das wars.** Nur 2 Dateien!

**Bewusst NICHT dabei:**
- ❌ DeepL Client → Nutzt offizielle Library direkt
- ❌ Separate Loader/Writer → Nutzt Symfony direkt
- ❌ Komplexe Config-Klassen → Input-Validation im Command
- ❌ Result-Objects → Einfaches Array
- ❌ Service-Layer → YAGNI

## Dependencies

```json
{
  "require": {
    "php": "^8.1",
    "composer-plugin-api": "^2.0",
    "move-elevator/composer-translation-validator": "^1.0",
    "symfony/translation": "^5.0 || ^6.0 || ^7.0",
    "symfony/console": "^5.0 || ^6.0 || ^7.0",
    "deeplcom/deepl-php": "^1.9"
  }
}
```

**Hinweis:** Nutzt die offizielle DeepL PHP Library statt eigenem Guzzle-Wrapper.

## Workflow (5 Schritte)

1. **MismatchValidator** findet fehlende Keys (aus validator-package)
2. **Symfony XliffFileLoader** lädt Source + Target Kataloge (funktioniert für TYPO3, Symfony, Laravel)
3. **DeepL\Translator** übersetzt fehlende Werte (offizielle Library)
4. **MessageCatalogue** speichert Übersetzungen (optional: Metadata für XLIFF)
5. **Symfony XliffFileDumper** schreibt zurück (preserviert TYPO3 XLIFF-Struktur)

**Wichtig für TYPO3:** Symfony's XLIFF Loader/Dumper sind kompatibel mit TYPO3 XLIFF-Format (1.2 & 2.0).

## Command Interface

```bash
# Minimal
composer autofill-translations -t de

# Standard
composer autofill-translations translations/ -s en -t de -t fr

# Mit XLIFF Markierung
composer autofill-translations -t de --mark-auto-translated

# Dry Run
composer autofill-translations -t de --dry-run
```

### CLI-Optionen (minimal)

**Basis:**
- `path` (optional, default: `translations/`)
- `-s, --source-locale` (default: `en`)
- `-t, --target-locales` (array, required)
- `-k, --api-key` (oder `DEEPL_API_KEY` env)
- `-f, --format` (xliff, yaml, json, php - default: xliff)
- `--domain` (default: `messages`)

**Sicherheit & Workflow:**
- `-d, --dry-run` - Simulation ohne Schreiben
- `--force` - Überschreibt Dateien ohne Nachfrage
- `-m, --mark-auto-translated` (XLIFF state)

**Output:**
- `-v, --verbose` - Detaillierte Ausgabe mit Statistiken
- `-q, --quiet` - Nur Errors ausgeben

## Implementierung (Konzepte, kein vollständiger Code)

### 1. DeepL Integration (Offizielle Library)

```php
use DeepL\Translator;

// Im Command
$translator = new Translator($apiKey);

// Übersetzen
$result = $translator->translateText(
    $text,
    $sourceLocale,  // 'en'
    $targetLocale   // 'de'
);

$translatedText = $result->text;
```

**Das wars!** Kein eigener Client nötig.

**Features der Library:**
- Auto-Detection Free/Pro API
- Error Handling (Exceptions)
- Type Safety
- Batch Translation Support
- Usage API (`$translator->getUsage()`)
- Alle DeepL Features (Glossaries, Formality, etc.)

### 2. Command (Hauptlogik)

```php
use DeepL\Translator;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Dumper\XliffFileDumper;

protected function execute(InputInterface $input, OutputInterface $output): int {
    // 1. File Overwrite Check (wie in JS Version)
    if (!$force && file_exists($targetFile)) {
        $question = new ConfirmationQuestion(
            "File {$targetFile} already exists. Overwrite? (y/n) ",
            false
        );
        if (!$helper->ask($input, $output, $question)) {
            return Command::SUCCESS;
        }
    }

    // 2. Init DeepL
    $translator = new Translator($apiKey);

    // 3. MismatchValidator nutzen
    $validator = new MismatchValidator();
    $issues = $validator->validate($files, null);

    // 4. Symfony Loader
    $loader = new XliffFileLoader();
    $sourceCatalogue = $loader->load($sourceFile, $sourceLocale);
    $targetCatalogue = $loader->load($targetFile, $targetLocale);

    // 5. Übersetzen mit Statistiken
    $translatedCount = 0;
    foreach ($missingKeys as $key) {
        $sourceText = $sourceCatalogue->get($key);

        // Skip empty values
        if (empty(trim($sourceText))) continue;

        $result = $translator->translateText($sourceText, $sourceLocale, $targetLocale);
        $targetCatalogue->set($key, $result->text);

        // Optional: XLIFF Marking
        if ($markAutoTranslated) {
            $targetCatalogue->setMetadata($key, [...], 'messages');
        }

        $translatedCount++;

        // Verbose output (wie in JS)
        if ($verbose) {
            $output->writeln("  ✓ {$key}: \"{$sourceText}\" → \"{$result->text}\"");
        }
    }

    // 6. Zurückschreiben
    if (!$dryRun) {
        $dumper = new XliffFileDumper();
        $dumper->dump($targetCatalogue, ['path' => dirname($targetFile)]);
    }

    // 7. Statistik-Output (wie in JS)
    $output->writeln("<info>✓ Translated {$translatedCount} keys</info>");

    return Command::SUCCESS;
}
```

### 3. XLIFF State Marking (optional)

```php
// Nutzt Symfony MessageCatalogue Metadata API
$catalogue->setMetadata($key, [
    'target-attributes' => ['state' => 'needs-review-translation'],
    'notes' => [['content' => 'Auto-translated by DeepL', 'from' => 'deepl-autofill']]
], $domain);
```

**Resultat in XLIFF:**
```xml
<trans-unit id="xyz" resname="welcome.message">
  <source>Welcome</source>
  <target state="needs-review-translation">Willkommen</target>
  <note from="deepl-autofill">Auto-translated by DeepL</note>
</trans-unit>
```

## Verwendung

### Installation

```bash
composer require move-elevator/composer-translation-autofill
```

### Dateistruktur

**TYPO3 Style** (Haupt-Use-Case):
```
Resources/Private/Language/
├── locallang.xlf           # Default/English
├── de.locallang.xlf        # Deutsch
└── fr.locallang.xlf        # Französisch

# Oder TYPO3 11+
Resources/Private/Language/
├── locallang.xlf
├── locallang.de.xlf
└── locallang.fr.xlf
```

**Symfony Style** (auch unterstützt):
```
translations/
├── messages.en.xlf
├── messages.de.xlf
└── messages.fr.xlf
```

**Laravel Style** (auch unterstützt):
```
resources/lang/
├── en/messages.xlf
├── de/messages.xlf
└── fr/messages.xlf
```

### Beispiele

**TYPO3 Extension:**
```bash
export DEEPL_API_KEY=your-key

# Standard TYPO3 locallang
cd my_extension/
composer autofill-translations Resources/Private/Language/ -t de -t fr

# Mit verbose output
composer autofill-translations Resources/Private/Language/ -t de -v

# Dry-Run (testen ohne zu schreiben)
composer autofill-translations Resources/Private/Language/ -t de --dry-run
```

**Symfony/Laravel:**
```bash
# Mehrere Sprachen
composer autofill-translations translations/ -s en -t de -t fr -t es

# YAML Format
composer autofill-translations translations/ -f yaml -t de

# Mit State Marking
composer autofill-translations translations/ -t de --mark-auto-translated
```

## Testing

**Unit Tests (2 Tests):**
- `CommandTest` → Mock DeepL\Translator
- `XliffMarkingTest` → Metadata Assertions

**Integration Tests (2 Tests):**
- `Typo3XliffTest` → End-to-End mit TYPO3 locallang Fixtures
- `SymfonyXliffTest` → End-to-End mit Symfony XLIFF Fixtures

**Fixture-Dateien:**
```
tests/Fixtures/
├── typo3/
│   ├── locallang.xlf           # TYPO3 Source
│   ├── de.locallang.xlf        # TYPO3 Target (unvollständig)
│   └── expected_de.locallang.xlf
└── symfony/
    ├── messages.en.xlf
    ├── messages.de.xlf
    └── expected_messages.de.xlf
```

## Inspiriert von Ihrer JS-Version

Folgende Features aus Ihrer `translate.js` wurden ins POC übernommen:

✅ **File Overwrite Protection** - Interaktive Bestätigung (außer mit `--force`)
✅ **Statistik-Output** - "✓ Translated X keys"
✅ **Verbose Mode** - Detaillierte Ausgabe jeder Übersetzung
✅ **Empty Value Skip** - Überspringt leere/whitespace Werte
✅ **Colored CLI Output** - Symfony Console (grün/rot)
✅ **Environment Variable** - `DEEPL_API_KEY` Support
✅ **Error Handling** - Klare Fehlermeldungen

## Was bewusst NICHT im v1.0 ist

Phase 2 Features (separate Packages/Extensions):
- **Batch Translation** (50 Keys/Request) - Ihre JS-Version nutzt das! Wichtig für Performance bei >20 Keys
- **Formality Settings** (`prefer_more`/`prefer_less`) - DeepL Feature
- Translation Memory / Caching
- DeepL Glossaries
- HTML/Placeholder Preservation
- Interactive Mode (Key-by-Key Bestätigung)
- Git Integration
- Alternative Provider (Google, Azure)

**Grund:** KISS - Erst die Basis solide bauen, dann erweitern.

**Hinweis:** Batch Translation sollte evtl. schon in v1.0, da es Performance drastisch verbessert (1 API Call für 50 Keys statt 50 Calls).

## DeepL API

**Free API:** 500.000 Zeichen/Monat (Key endet mit `:fx`)
**Pro API:** Pay-as-you-go

**Unterstützte Sprachen:** EN, DE, FR, ES, IT, NL, PL, PT, RU, JA, ZH, BG, CS, DA, EL, ET, FI, HU, LV, LT, RO, SK, SL, SV, TR, UK, ID, KO, NB, AR

## Security

- API Key via `DEEPL_API_KEY` Environment Variable
- Niemals in Git committen
- CI/CD: Nutze Secrets

## Nächste Schritte

1. Repository aufsetzen (GitHub/GitLab)
2. Grundstruktur (2 Dateien!) erstellen
3. Command implementieren + testen
4. Integration Test
5. README + Dokumentation
6. Packagist Release

**Geschätzte Entwicklungszeit:** 1 Tag (bei KISS-Fokus + offizielle Library)

## Zusätzliche Ressourcen

- **DeepL PHP Library:** https://github.com/DeepLcom/deepl-php
- **DeepL API Docs:** https://developers.deepl.com/docs
- **Symfony Translation:** https://symfony.com/doc/current/translation.html
- **TYPO3 XLIFF Format:** https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Internationalization/TranslationFiles/Index.html

---

## Domain-Erklärung

**Was ist eine Domain?**

Domains sind **thematische Gruppierungen** von Übersetzungen:

```
translations/
├── messages.de.xlf      # Domain: "messages" (Standard)
├── validators.de.xlf    # Domain: "validators"
├── security.de.xlf      # Domain: "security"
```

**Verwendung in Symfony:**
```php
$translator->trans('hello.world');  // Domain: messages (default)
$translator->trans('email.required', [], 'validators');  // Domain: validators
```

**Im Package:**
```bash
# Standard Domain
composer autofill-translations -t de

# Spezifische Domain
composer autofill-translations -t de --domain=validators
```

Für 95% der Projekte: Domain = `messages` (default).
