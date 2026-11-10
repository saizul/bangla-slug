# Contributing

Contributions are welcome, especially transliteration corrections from native Bangla speakers.

## Setup

Requirements: PHP 8.1 or newer.

```sh
composer install
composer test        # PHPUnit, no WordPress needed
composer php-lint    # php -l on every file
composer lint        # PHPCS (WordPress Coding Standards)
```

You can also run a downloaded `phpunit.phar` (version 10.5) directly: `php phpunit.phar -c phpunit.xml.dist`.

## Architecture

- `src/Transliterator.php`, `src/Slugifier.php`, `src/Mapping.php`, `src/Stopwords.php` are pure PHP with no WordPress calls. Keep them that way so they stay unit testable.
- Everything else (`Plugin`, `SlugHooks`, `Regenerator`, `Redirector`, `Admin`, `Cli`, `Repository`, `Options`) integrates with WordPress.

## Reporting a wrong transliteration

Open an issue with the Bangla word, the output you got and the output you expect. Better, add a case to `TransliteratorTest::defaultCases()` in `tests/TransliteratorTest.php` and fix the rule. The transliteration is a readable URL romanisation, not a strict scholarly scheme, so prefer commonly used spellings.

## Pull requests

1. Branch from `main`.
2. Add or update tests; the suite must pass on PHP 8.1, 8.2 and 8.3.
3. Update `CHANGELOG.md` and, for user-visible changes, `readme.txt` and `README.md`.
4. Never commit real site data or credentials. Use `example.com` in examples.

## License

By contributing you agree that your work is licensed under GPL-2.0-or-later.
