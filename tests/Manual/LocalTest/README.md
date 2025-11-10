# Local Test Setup

This setup allows you to test the package locally without publishing it to Packagist.

## 🚀 Setup

### 1. Set DeepL API Key

```bash
export DEEPL_API_KEY=your-api-key-here
```

Or create a `.env` file in the project root:
```bash
echo "DEEPL_API_KEY=your-api-key-here" > .env
```

### 2. Test the Package

#### Option A: Direct binary execution (recommended)

```bash
# From project root
./bin/autotranslate tests/Manual/LocalTest/translations/ -t de

# With --dry-run to preview without making changes
./bin/autotranslate tests/Manual/LocalTest/translations/ -t de --dry-run

# Without auto-translation marking (marking is enabled by default)
./bin/autotranslate tests/Manual/LocalTest/translations/ -t de --no-mark-auto-translated

# Verbose output for detailed information
./bin/autotranslate tests/Manual/LocalTest/translations/ -t de -v
```

#### Option B: Via Composer

```bash
# From project root
composer autotranslate tests/Manual/LocalTest/translations/ -- -t de
```

#### Option C: Direct PHP execution

```bash
php bin/autotranslate tests/Manual/LocalTest/translations/ -t de
```

## 📁 Test Files

- `translations/messages.en.xlf` - English source file (complete with 6 entries)
- `translations/messages.de.xlf` - German target file (only 1 of 6 entries translated)

The German file intentionally has only one translated message (`welcome.message`).
The other 5 translations are missing and will be auto-filled by DeepL.

## 🧪 What You Can Test

### 1. Dry-Run Test
```bash
./bin/autotranslate tests/Manual/LocalTest/translations/ -t de --dry-run
```
Shows what would be translated without modifying any files.

### 2. Actual Translation
```bash
./bin/autotranslate tests/Manual/LocalTest/translations/ -t de
```
Performs the translation and saves the results.

### 3. Multiple Languages
```bash
# First create a French file from the distribution template
cp tests/Manual/LocalTest/translations/dist.messages.fr.xlf tests/Manual/LocalTest/translations/messages.fr.xlf

# Translate both German and French
./bin/autotranslate tests/Manual/LocalTest/translations/ -t de -t fr
```

**Note**: Both empty files and missing files are now properly detected:
- **Empty files** (with no `trans-unit` elements): Detected by the `MismatchValidator` in the `composer-translation-validator` package (Phase 1 implementation)
- **Missing files** (files that don't exist yet): Detected by `detectMissingTargetFiles()` in this package

This means you can now translate to completely new languages without needing to create a template file first!

### 4. Without Auto-Translation Marking
```bash
./bin/autotranslate tests/Manual/LocalTest/translations/ -t de --no-mark-auto-translated
```
By default, all auto-translated entries in XLIFF are marked with `state="needs-review-translation"` and a note indicating they were auto-translated by DeepL. Use this flag to disable the marking.

### 5. Test Different Formats

#### YAML
```bash
# Create YAML files
echo "welcome.message: Welcome
goodbye.message: Goodbye
button.save: Save" > tests/Manual/LocalTest/translations/messages.en.yaml

echo "welcome.message: Willkommen" > tests/Manual/LocalTest/translations/messages.de.yaml

# Translate
./bin/autotranslate tests/Manual/LocalTest/translations/ -t de -f yaml
```

#### JSON
```bash
# Create JSON files
echo '{"welcome.message":"Welcome","goodbye.message":"Goodbye"}' > tests/Manual/LocalTest/translations/messages.en.json
echo '{"welcome.message":"Willkommen"}' > tests/Manual/LocalTest/translations/messages.de.json

# Translate
./bin/autotranslate tests/Manual/LocalTest/translations/ -t de -f json
```

## 🔍 Verify Results

After translation, you can inspect the results:

```bash
# Show the translated file
cat tests/Manual/LocalTest/translations/messages.de.xlf

# Or with XML formatting (if xmllint is installed)
xmllint --format tests/Manual/LocalTest/translations/messages.de.xlf
```

## 🧹 Cleanup

To reset the test files:

```bash
# Copy from the distribution template
cp tests/Manual/LocalTest/translations/dist.messages.de.xlf tests/Manual/LocalTest/translations/messages.de.xlf

# Or restore from git (if committed)
git checkout tests/Manual/LocalTest/translations/messages.de.xlf

# Clean up backup files
rm tests/Manual/LocalTest/translations/*.backup
```

The `dist.messages.de.xlf` file serves as a template that can be copied whenever you want to reset the test.

## 💡 Tips

1. **DeepL Free API**: Use the free DeepL API key for testing (500,000 characters/month)
2. **Backups**: Original files are automatically backed up with `.backup` suffix
3. **Git**: The `.gitignore` is already configured to ignore backup files
4. **Verbose Mode**: Use `-v` for detailed output during development
5. **Error Testing**: Try removing the API key to test error handling
6. **Batch Testing**: The setup includes 5 missing translations to test batch processing

## 📊 Expected Output

When running the translation, you should see:

- Progress bar showing translation progress
- Statistics about missing/translated keys
- Filename of the saved translation file
- DeepL API usage information

Example output:
```
Composer Translation DeepL Autofill
===================================

› Scanning for translation files...
  ▸ Found 3 translation file(s)

› Validating translations...
  ▸ Found 5 missing translation(s)

› Translating to German (de)

 5/5 [============================] 100%

  ✓ Translated 5 key(s)
  ▸ Saved to: messages.de.xlf

✓ Translation completed successfully
  5 key(s) translated in 1 language(s)

▸ API Usage: 45 / 500,000 characters (0.01%)
```
