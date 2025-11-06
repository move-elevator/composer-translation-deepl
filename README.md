<div align="center">

# Composer Translation DeepL

[![Coverage](https://img.shields.io/coverallsCoverage/github/move-elevator/composer-translation-deepl?logo=coveralls)](https://coveralls.io/github/move-elevator/composer-translation-deepl)
[![CGL](https://img.shields.io/github/actions/workflow/status/move-elevator/composer-translation-deepl/cgl.yml?label=cgl&logo=github)](https://github.com/move-elevator/composer-translation-deepl/actions/workflows/cgl.yml)
[![Tests](https://img.shields.io/github/actions/workflow/status/move-elevator/composer-translation-deepl/tests.yml?label=tests&logo=github)](https://github.com/move-elevator/composer-translation-deepl/actions/workflows/tests.yml)
[![Supported PHP Versions](https://img.shields.io/packagist/dependency-v/move-elevator/composer-translation-deepl/php?logo=php)](https://packagist.org/packages/move-elevator/composer-translation-deepl)

</div>

Auto-fill missing translations using the DeepL API. A lightweight, modern Composer package that automatically completes missing translations in your translation files.

## ✨ Features

* **Automatic Translation** - Uses DeepL API to fill missing translation keys
* **Batch Processing** - Translates up to 50 keys per API request for optimal performance
* **Multiple Formats** - XLIFF, YAML, JSON, PHP support
* **TYPO3 Compatible** - Works with TYPO3 XLIFF format
* **Symfony/Laravel Ready** - Supports standard translation file structures
* **Dry-Run Mode** - Preview changes before applying
* **Progress Tracking** - Visual progress bar and detailed statistics
* **API Usage Display** - Shows your DeepL character usage
* **XLIFF Marking** - Optional state marking for auto-translated content

## 🔥 Installation

[![Packagist](https://img.shields.io/packagist/v/move-elevator/composer-translation-deepl?label=version&logo=packagist)](https://packagist.org/packages/move-elevator/composer-translation-deepl)
[![Packagist Downloads](https://img.shields.io/packagist/dt/move-elevator/composer-translation-deepl?color=brightgreen)](https://packagist.org/packages/move-elevator/composer-translation-deepl)

```bash
composer require move-elevator/composer-translation-deepl
```

## 📋 Prerequisites

- PHP 8.1 or higher
- DeepL API key ([Get one for free](https://www.deepl.com/pro-api))

## 📊 Usage

### Basic Usage

```bash
# Set your DeepL API key
export DEEPL_API_KEY=your-api-key-here

# Translate to German
vendor/bin/autotranslate translations/ -t de

# Translate to multiple languages
vendor/bin/autotranslate translations/ -t de -t fr -t es
```

### Advanced Options

```bash
# Custom source locale
vendor/bin/autotranslate -s en-US -t de-DE

# Specific format
vendor/bin/autotranslate -t de -f yaml

# Mark translations as auto-translated (XLIFF only)
vendor/bin/autotranslate -t de --mark-auto-translated

# Force overwrite without confirmation
vendor/bin/autotranslate -t de --force

# Quiet mode (errors only)
vendor/bin/autotranslate -t de -q

# Custom domain
vendor/bin/autotranslate -t de --domain validators
```

## 📝 Documentation

### Command Options

| Option | Short | Description | Default |
|--------|-------|-------------|---------|
| `path` | - | Path to translation files directory | `translations/` |
| `--source-locale` | `-s` | Source locale | `en` |
| `--target-locales` | `-t` | Target locales (multiple) | required |
| `--api-key` | `-k` | DeepL API key | `DEEPL_API_KEY` env |
| `--format` | `-f` | File format (xliff, yaml, json, php) | `xliff` |
| `--domain` | - | Translation domain | `messages` |
| `--dry-run` | `-d` | Simulate without writing | `false` |
| `--force` | - | Overwrite without confirmation | `false` |
| `--mark-auto-translated` | `-m` | Mark with XLIFF state | `false` |
| `--verbose` | `-v` | Detailed output | `false` |
| `--quiet` | `-q` | Only errors | `false` |

### Supported File Formats

Translation files are detected and processed based on their format:

| Format | Frameworks | Example files                          |
|--------|------------|----------------------------------------|
| **XLIFF** | TYPO3 CMS, Symfony | `locallang.xlf`, `de.locallang.xlf`, `messages.de.xlf` |
| **YAML** | Symfony | `messages.en.yaml`, `messages.de.yaml` |
| **JSON** | Laravel, Symfony | `messages.en.json`, `messages.de.json` |
| **PHP** | Laravel | `en/messages.php`, `messages.en.php` |

## 🧑‍💻 Contributing

Please have a look at [`CONTRIBUTING.md`](CONTRIBUTING.md).

## ⭐ License

This project is licensed under [GNU General Public License 3.0 (or later)](LICENSE).
