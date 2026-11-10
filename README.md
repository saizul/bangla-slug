# Bangla Slug

[![CI](https://github.com/saizul/bangla-slug/actions/workflows/ci.yml/badge.svg)](https://github.com/saizul/bangla-slug/actions/workflows/ci.yml)
[![License: GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)
![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777bb4.svg)

A WordPress plugin that turns Bangla titles into readable Latin slugs. A Bangla title normally becomes a long percent-encoded URL such as `%e0%a6%ac%e0%a6%be%e0%a6%82%e0%a6%b2%e0%a6%be`; with this plugin it becomes `bangla`.

By [Saizul Amin](https://saizul.com). See [বাংলা বিবরণ](#বাংলা-বিবরণ) for the Bangla summary.

| Title | Slug |
| --- | --- |
| বাংলাদেশ | `bangladesh` |
| আমার সোনার বাংলা | `amar-sonar-bangla` |
| ঢাকা এবং চট্টগ্রাম | `dhaka-chottogram` |
| ২০২৪ সালের বাজেট | `2024-saler-bajet` |
| বিশ্ববিদ্যালয় | `bishwobidyaloy` |

## Features

- Phonetic transliteration of vowels, consonants, kar (vowel signs), hasanta, conjuncts, ya-phala, ba-phala, reph, anusvara, visarga, candrabindu, khanda ta and Bangla digits.
- Applies on save **only when WordPress would otherwise store percent-encoded Bangla**. Latin slugs you type are never touched.
- Posts, pages, custom post types (WooCommerce products included) and terms.
- Stopword removal for Bangla and English, plus your own list.
- Configurable character and conjunct mapping.
- Bulk tool (Tools > Bangla Slug) with dry-run preview, and WP-CLI.
- Old to new slugs stored in a database table; old URLs 301-redirect to the new permalink.
- Settings page with live preview.
- Filters for customisation.

Requirements: WordPress 6.0+, PHP 8.1+.

## Installation

Download the repository as a zip and install it from Plugins > Add New > Upload, or clone it into `wp-content/plugins/bangla-slug`, then activate. Configure under Settings > Bangla Slug.

## How the transliteration works

Each Bangla word is split into units: a consonant cluster (letters joined by hasanta) plus an optional vowel sign. A consonant without a vowel sign gets the inherent vowel `o` (কলম = `kolom`), except at the end of a word where it is dropped (`kolom`, not `kolomo`). Special cases:

- ক্ষ = `kkh` (শিক্ষা = `shikkha`), জ্ঞ = `gy` (বিজ্ঞান = `bigyan`).
- ya-phala is `y`, ba-phala is `w`; at the end of a word they add `o` (সত্য = `sotyo`, বিশ্ব = `bishwo`).
- ং = `ng`, ঁ = `n`, ঃ = `h`, ৎ = `t`.
- Bangla digits become 0-9.

The inherent vowel mode is configurable: `final` (default), `medial` (heuristic: কলকাতা = `kolkata`, but কমলা also becomes `komla`) or `none`.

This is a readable URL romanisation, not a scholarly scheme. Fix any spelling you dislike with the custom mapping.

## Settings

Settings > Bangla Slug: enable/disable, post types, taxonomies, stopwords, inherent vowel mode, maximum length, separator, custom mapping, redirects, delete-data-on-uninstall, and a live preview that uses the form values before you save.

Custom mapping uses one `from=to` per line:

```
ফ=f
ক্ষ=ksh
```

Targets may only contain letters and digits. Keys containing a hasanta are treated as conjuncts.

## Bulk conversion

**Admin:** Tools > Bangla Slug. Choose post types and taxonomies, press Preview, then Run. Each run handles up to the chosen number of items; repeat until nothing is left.

**WP-CLI:**

```sh
wp bangla-slug regenerate --post_type=post --dry-run
wp bangla-slug regenerate --post_type=post,page --taxonomy=category,post_tag
wp bangla-slug convert "আমার সোনার বাংলা"
wp bangla-slug redirects list
wp bangla-slug redirects clear
```

Options for `regenerate`: `--post_type`, `--taxonomy`, `--status`, `--batch`, `--dry-run`, `--format`.

Take a database backup first. The old slug of every changed item is stored in `{prefix}bangla_slug_redirects`.

## Redirects

When a request would be a 404, the plugin looks at the path segments for an old slug recorded in the table and redirects (301) to the item's current permalink. Disable this in the settings if your server or another plugin handles redirects.

## Filters and actions

```php
// Override mapping (keys: Bangla letter or conjunct with hasanta).
add_filter( 'bangla_slug_map', function ( array $map ): array {
    $map['ফ'] = 'f';
    return $map;
} );

// Add stopwords.
add_filter( 'bangla_slug_stopwords', fn ( array $words ): array => array_merge( $words, [ 'আজ' ] ) );

// Skip a post type or term conversion.
add_filter( 'bangla_slug_should_convert', function ( bool $convert, string $slug, array $context ): bool {
    return ( $context['post_type'] ?? '' ) !== 'event';
}, 10, 3 );

// Change the text that is transliterated.
add_filter( 'bangla_slug_source', fn ( string $text ): string => preg_replace( '/\(.*?\)/u', '', $text ) );

// Change the final slug before WordPress makes it unique.
add_filter( 'bangla_slug_result', fn ( string $slug ): string => 'bn-' . $slug );

// Override a redirect target; return null to skip.
add_filter( 'bangla_slug_redirect_target', fn ( ?string $url, object $row ): ?string => $url, 10, 2 );

// Run code after a bulk change.
add_action( 'bangla_slug_slug_regenerated', function ( array $item ): void {} );
```

Also available: `bangla_slug_options` to override any setting.

## Development

```sh
composer install
composer test        # PHPUnit; does not need WordPress
composer php-lint
composer lint        # PHPCS
```

The transliteration lives in `src/Transliterator.php`, `src/Slugifier.php`, `src/Mapping.php` and `src/Stopwords.php` with no WordPress dependency. See [CONTRIBUTING.md](CONTRIBUTING.md).

## বাংলা বিবরণ

**Bangla Slug** একটি ওয়ার্ডপ্রেস প্লাগিন, যা বাংলা শিরোনামকে পড়ার উপযোগী ইংরেজি হরফের স্লাগে রূপান্তর করে। সাধারণত বাংলা শিরোনাম থেকে `%e0%a6%ac...` ধরনের লম্বা ও অপঠনযোগ্য লিংক তৈরি হয়। এই প্লাগিন ব্যবহার করলে "আমার সোনার বাংলা" হয়ে যায় `amar-sonar-bangla`।

বৈশিষ্ট্য:

- ধ্বনিভিত্তিক ট্রান্সলিটারেশন: স্বরবর্ণ, ব্যঞ্জনবর্ণ, কার-চিহ্ন, হসন্ত, যুক্তাক্ষর, য-ফলা, ব-ফলা, রেফ, অনুস্বার, বিসর্গ, চন্দ্রবিন্দু ও বাংলা সংখ্যা।
- শুধু তখনই কাজ করে যখন স্লাগটি অন্যথায় বাংলা অক্ষরের এনকোডেড রূপে সংরক্ষিত হতো। আপনি নিজে ইংরেজিতে লেখা স্লাগ কখনো বদলানো হয় না।
- পোস্ট, পেজ, কাস্টম পোস্ট টাইপ (ওয়াকমার্সের পণ্যসহ) এবং ক্যাটাগরি/ট্যাগের জন্য কাজ করে।
- অপ্রয়োজনীয় শব্দ (এবং, ও, এর ইত্যাদি) বাদ দেওয়ার সুবিধা এবং নিজের তালিকা যোগ করার সুযোগ।
- অক্ষরের ম্যাপিং নিজের মতো পরিবর্তন করা যায়।
- পুরনো পোস্টের স্লাগ একসাথে পরিবর্তনের জন্য অ্যাডমিন টুল (টুলস > Bangla Slug) এবং WP-CLI কমান্ড।
- পুরনো লিংক নতুন লিংকে স্বয়ংক্রিয়ভাবে ৩০১ রিডাইরেক্ট হয়।
- লাইভ প্রিভিউসহ সেটিংস পেজ।

ব্যবহার:

1. প্লাগিনটি ইনস্টল ও অ্যাক্টিভ করুন।
2. সেটিংস > Bangla Slug থেকে পছন্দমতো কনফিগার করুন।
3. পুরনো কনটেন্ট রূপান্তর করতে আগে ব্যাকআপ নিন, তারপর `wp bangla-slug regenerate --post_type=post --dry-run` দিয়ে পূর্বরূপ দেখুন এবং `--dry-run` বাদ দিয়ে চালান।

মনে রাখবেন, এটি পাঠযোগ্য ইউআরএলের জন্য সহজ রোমান বানান, কোনো কঠোর আন্তর্জাতিক পদ্ধতি নয়। কোনো বানান পছন্দ না হলে কাস্টম ম্যাপিং ব্যবহার করুন।

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
