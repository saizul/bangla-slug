=== Bangla Slug ===
Contributors: saizulamin
Tags: bangla, bengali, slug, permalink, transliteration
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn Bangla titles into clean, readable Latin slugs. Includes a bulk tool, WP-CLI command and automatic 301 redirects for old URLs.

== Description ==

By default WordPress stores a Bangla title as a long percent-encoded slug such as `%e0%a6%ac%e0%a6%be%e0%a6%82%e0%a6%b2%e0%a6%be`. These URLs are unreadable when copied, break in some chat apps and are hard to type.

Bangla Slug converts the title phonetically so the slug becomes `bangla`.

* Phonetic transliteration: vowels, consonants, vowel signs (kar), hasanta, conjuncts (juktakkhor), ya-phala, ba-phala, reph, anusvara, visarga, candrabindu and Bangla digits.
* Applies on save only when the slug would otherwise be percent-encoded Bangla. Slugs you type in Latin are never changed.
* Works for posts, pages, custom post types (including WooCommerce products) and terms.
* Stopword removal (এবং, ও, এর, the, of ...) with your own additions.
* Configurable character and conjunct mapping.
* Word dictionary for names, brands and loanwords (কম্পিউটার=computer, ঢাকা বিশ্ববিদ্যালয়=dhaka university), with handling of case endings such as -er.
* Bulk tool (Tools > Bangla Slug) with a dry-run preview.
* WP-CLI: `wp bangla-slug regenerate --post_type=post --dry-run`.
* Old to new slugs are stored in a table and old URLs get a 301 redirect when they would otherwise be a 404.
* Settings page with live preview.
* Developer filters for every step.

Examples:

* আমার সোনার বাংলা becomes amar-sonar-bangla
* ঢাকা এবং চট্টগ্রাম becomes dhaka-chottogram
* ২০২৪ সালের বাজেট becomes 2024-saler-bajet

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the zip from Plugins > Add New.
2. Activate the plugin.
3. Review Settings > Bangla Slug.
4. Optional: convert existing content from Tools > Bangla Slug, or with WP-CLI.

== Frequently Asked Questions ==

= Will it change slugs I typed myself? =

No. Only slugs that contain Bangla (usually percent-encoded) are converted.

= Will old links break after bulk conversion? =

The old slug is stored and a visit to the old URL is redirected with a 301 to the new permalink. Redirects only run for requests that would be a 404. Always take a backup before a bulk run.

= Can I change how letters are romanised? =

Yes. Use Custom mapping in the settings (for example `ফ=f`) or the `bangla_slug_map` filter.

= Some words come out wrong, for example কম্পিউটার becomes kompiutar. Can I fix whole words? =

Yes. Add them to Word dictionary in the settings, one per line: `কম্পিউটার=computer`. Phrases work too: `ঢাকা বিশ্ববিদ্যালয়=dhaka university`. Inflected forms such as কম্পিউটারের or কম্পিউটারে are matched as well; choose whether the ending is kept (computerer), dropped (computer) or whether only exact words match. Developers can use the `bangla_slug_dictionary` filter.

= How do I use WP-CLI? =

`wp bangla-slug regenerate --post_type=post,page --taxonomy=category --dry-run` previews changes; remove `--dry-run` to apply. `wp bangla-slug convert "আমার সোনার বাংলা"` tests a title. `wp bangla-slug redirects list` shows stored redirects. `wp bangla-slug dictionary list` shows the word dictionary.

= Which filters are available? =

`bangla_slug_options`, `bangla_slug_map`, `bangla_slug_dictionary`, `bangla_slug_stopwords`, `bangla_slug_should_convert`, `bangla_slug_source`, `bangla_slug_result`, `bangla_slug_redirect_target` and the action `bangla_slug_slug_regenerated`. See the README on GitHub for examples.

== Changelog ==

= 1.1.0 =
* New: word dictionary with fixed spellings for whole words and phrases, applied before transliteration.
* New: case endings on dictionary words (বাংলাদেশের, কম্পিউটারে) can be kept, dropped or not matched.
* New: `bangla_slug_dictionary` filter and `wp bangla-slug dictionary list|count|lookup`.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.0 =
Adds a word dictionary for names and loanwords. Existing slugs and settings are unchanged until you add entries.

= 1.0.0 =
Initial release.
