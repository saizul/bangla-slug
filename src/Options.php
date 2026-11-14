<?php
/**
 * Plugin settings.
 *
 * @package BanglaSlug
 */

declare(strict_types=1);

namespace SaizulAmin\BanglaSlug;

/**
 * Reads, validates and turns settings into a configured Slugifier.
 */
final class Options
{
    public const KEY = 'bangla_slug_options';

    /**
     * @return array<string,mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled'           => true,
            'post_types'        => ['post', 'page', 'product'],
            'convert_terms'     => true,
            'taxonomies'        => ['category', 'post_tag', 'product_cat', 'product_tag'],
            'remove_stopwords'  => true,
            'stopwords_extra'   => '',
            'schwa'             => Transliterator::SCHWA_FINAL,
            'max_length'        => 120,
            'separator'         => '-',
            'custom_map'        => '',
            'dictionary'        => '',
            'dictionary_suffix' => Dictionary::SUFFIX_KEEP,
            'redirects'         => true,
            'delete_data'       => false,
        ];
    }

    /**
     * Current settings merged with defaults.
     *
     * @return array<string,mixed>
     */
    public static function get(): array
    {
        $stored = get_option(self::KEY, []);
        $merged = array_merge(self::defaults(), is_array($stored) ? $stored : []);

        /**
         * Filters the effective plugin options.
         *
         * @param array<string,mixed> $merged Options.
         */
        return (array) apply_filters('bangla_slug_options', $merged);
    }

    /**
     * Validate raw input (settings form or preview request).
     *
     * @param mixed $input Raw input.
     * @return array<string,mixed>
     */
    public static function sanitize(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $out   = self::defaults();

        $out['enabled']          = !empty($input['enabled']);
        $out['convert_terms']    = !empty($input['convert_terms']);
        $out['remove_stopwords'] = !empty($input['remove_stopwords']);
        $out['redirects']        = !empty($input['redirects']);
        $out['delete_data']      = !empty($input['delete_data']);

        $out['post_types'] = array_values(array_filter(
            array_map('sanitize_key', (array) ($input['post_types'] ?? [])),
            'post_type_exists'
        ));
        $out['taxonomies'] = array_values(array_filter(
            array_map('sanitize_key', (array) ($input['taxonomies'] ?? [])),
            'taxonomy_exists'
        ));

        $out['stopwords_extra'] = sanitize_textarea_field((string) ($input['stopwords_extra'] ?? ''));
        $out['custom_map']      = sanitize_textarea_field((string) ($input['custom_map'] ?? ''));
        $out['dictionary']      = sanitize_textarea_field((string) ($input['dictionary'] ?? ''));

        $out['dictionary_suffix'] = Dictionary::validMode((string) ($input['dictionary_suffix'] ?? Dictionary::SUFFIX_KEEP));

        $schwa = (string) ($input['schwa'] ?? Transliterator::SCHWA_FINAL);
        $out['schwa'] = in_array($schwa, [Transliterator::SCHWA_NONE, Transliterator::SCHWA_FINAL, Transliterator::SCHWA_MEDIAL], true)
            ? $schwa
            : Transliterator::SCHWA_FINAL;

        $out['max_length'] = max(20, min(200, (int) ($input['max_length'] ?? 120)));
        $out['separator']  = ($input['separator'] ?? '-') === '_' ? '_' : '-';

        return $out;
    }

    /**
     * Parse "from=to" lines into a mapping array. Blank lines and lines
     * starting with # are ignored; targets are limited to [a-z0-9].
     *
     * @return array<string,string>
     */
    public static function parseMap(string $text): array
    {
        $map = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$from, $to] = array_map('trim', explode('=', $line, 2));
            $to = strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', $to));
            if ($from !== '') {
                $map[$from] = $to;
            }
        }

        return $map;
    }

    /**
     * Build the slugifier for the given (or current) options.
     *
     * @param array<string,mixed>|null $options Options, null for the saved ones.
     */
    public static function slugifier(?array $options = null): Slugifier
    {
        $options ??= self::get();

        /**
         * Filters the character mapping overrides.
         *
         * Keys are Bangla characters or conjunct sequences (with hasanta),
         * values are lowercase Latin strings.
         *
         * @param array<string,string> $map     Overrides.
         * @param array<string,mixed>  $options Options.
         */
        $map = (array) apply_filters('bangla_slug_map', self::parseMap((string) $options['custom_map']), $options);

        $stopwords = array_merge(Stopwords::bangla(), Stopwords::english());
        foreach (preg_split('/[\s,]+/u', (string) $options['stopwords_extra'], -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $stopwords[] = $word;
        }

        /**
         * Filters the list of stopwords removed from titles.
         *
         * @param list<string>        $stopwords Stopwords.
         * @param array<string,mixed> $options   Options.
         */
        $stopwords = (array) apply_filters('bangla_slug_stopwords', $stopwords, $options);

        return new Slugifier(
            new Transliterator($map, (string) $options['schwa']),
            [
                'separator'        => $options['separator'],
                'max_length'       => (int) $options['max_length'],
                'remove_stopwords' => (bool) $options['remove_stopwords'],
                'stopwords'        => array_values(array_map('strval', $stopwords)),
                'dictionary'       => self::dictionary($options),
            ]
        );
    }

    /**
     * Build the whole-word dictionary for the given (or current) options.
     *
     * @param array<string,mixed>|null $options Options, null for the saved ones.
     */
    public static function dictionary(?array $options = null): Dictionary
    {
        $options ??= self::get();

        /**
         * Filters the whole-word dictionary.
         *
         * Keys are Bangla words or phrases (words separated by spaces), values
         * are the Latin spelling used in the slug. Entries are matched before
         * transliteration and take precedence over the character mapping.
         *
         * @param array<string,string> $entries Word or phrase => Latin spelling.
         * @param array<string,mixed>  $options Options.
         */
        $entries = (array) apply_filters('bangla_slug_dictionary', Dictionary::parse((string) ($options['dictionary'] ?? '')), $options);

        return new Dictionary(
            array_map('strval', $entries),
            (string) ($options['dictionary_suffix'] ?? Dictionary::SUFFIX_KEEP)
        );
    }
}
