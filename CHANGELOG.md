# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial release of composer-translation-deepl
- Automatic translation of missing translation keys using DeepL API
- Support for XLIFF, YAML, JSON, and PHP translation formats
- Batch translation processing (up to 50 keys per API request)
- TYPO3 extension support (v10 and v11+ XLIFF formats)
- Symfony and Laravel framework compatibility
- Dry-run mode for previewing translations
- Progress tracking with visual progress bar
- Verbose mode with detailed translation output
- API usage statistics display
- XLIFF state marking for auto-translated content
- Force mode to skip confirmation prompts
- Custom domain support for translation files
- Multiple target locale support in single command

### Dependencies
- PHP 8.1+ support
- Symfony Console component for CLI interface
- Symfony Translation component for file handling
- DeepL PHP SDK for API integration
- composer-translation-validator for missing key detection

### Documentation
- Comprehensive README with usage examples
- Command option reference table
- File structure examples for different frameworks
- Output examples for all modes
- DeepL API setup guide
- Security best practices

## [1.0.0] - TBD

Initial release.

[Unreleased]: https://github.com/move-elevator/composer-translation-deepl/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/move-elevator/composer-translation-deepl/releases/tag/v1.0.0
