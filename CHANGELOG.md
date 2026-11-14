# Changelog

All notable changes are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [1.1.0] - 2026-11-14

### Added
- Word dictionary: fixed Latin spellings for whole words and multi-word phrases (`কম্পিউটার=computer`, `ঢাকা বিশ্ববিদ্যালয়=dhaka university`) for names, brands and loanwords that phonetic rules cannot get right. Matches are applied before transliteration, take precedence over the character mapping, prefer the longest phrase and are never removed as stopwords.
- Case ending handling for dictionary words (বাংলাদেশের, কম্পিউটারে, কম্পিউটারগুলো ...): keep and transliterate the ending (default), drop it, or match exact words only.
- Settings field for the dictionary with an entry count, also honoured by the live preview, the bulk tool and WP-CLI.
- Filter `bangla_slug_dictionary` to add or change entries in code.
- WP-CLI: `wp bangla-slug dictionary list|count|lookup`.
- `Dictionary` class and unit tests; it has no WordPress dependency.

### Changed
- Stopword removal now runs on dictionary-aware tokens. With an empty dictionary slugs are unchanged.

## [1.0.0] - 2026-11-10

### Added
- Phonetic Bangla to Latin transliteration: vowels, consonants, vowel signs (kar), hasanta, conjuncts, ya-phala, ba-phala, reph, anusvara, visarga, candrabindu, khanda ta and Bangla digits.
- Inherent vowel handling with three modes: drop at word end (default), also drop in the middle (heuristic), or always keep.
- Stopword removal for Bangla and English with a custom list.
- Configurable character and conjunct mapping.
- Automatic conversion on save for posts, pages, custom post types and terms, only when the slug would otherwise be percent-encoded Bangla.
- Bulk tool under Tools > Bangla Slug with dry-run preview.
- WP-CLI: `wp bangla-slug regenerate`, `convert` and `redirects`.
- Old to new slug table and automatic 301 redirects for URLs that would otherwise be a 404.
- Settings page with live preview.
- Filters: `bangla_slug_options`, `bangla_slug_map`, `bangla_slug_stopwords`, `bangla_slug_should_convert`, `bangla_slug_source`, `bangla_slug_result`, `bangla_slug_redirect_target`; action `bangla_slug_slug_regenerated`.
- PHPUnit test suite for the transliterator and slugifier that runs without WordPress.
