# Changelog

All notable changes are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

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
