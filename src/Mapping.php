<?php
/**
 * Default Bangla to Latin character tables.
 *
 * @package BanglaSlug
 */

declare(strict_types=1);

namespace SaizulAmin\BanglaSlug;

/**
 * Holds the default phonetic mapping. Every table can be overridden through
 * the `$overrides` argument of {@see Transliterator} (or the `bangla_slug_map`
 * filter inside WordPress).
 */
final class Mapping
{
    public const HASANTA   = "\u{09CD}";
    public const NUKTA     = "\u{09BC}";
    public const CANDRABINDU = "\u{0981}";
    public const ANUSVARA  = "\u{0982}";
    public const VISARGA   = "\u{0983}";
    public const KHANDA_TA = "\u{09CE}";

    /**
     * Consonants (inherent vowel is added by the transliterator).
     *
     * @return array<string,string>
     */
    public static function consonants(): array
    {
        return [
            "\u{0995}" => 'k',
            "\u{0996}" => 'kh',
            "\u{0997}" => 'g',
            "\u{0998}" => 'gh',
            "\u{0999}" => 'ng',
            "\u{099A}" => 'ch',
            "\u{099B}" => 'chh',
            "\u{099C}" => 'j',
            "\u{099D}" => 'jh',
            "\u{099E}" => 'n',
            "\u{099F}" => 't',
            "\u{09A0}" => 'th',
            "\u{09A1}" => 'd',
            "\u{09A2}" => 'dh',
            "\u{09A3}" => 'n',
            "\u{09A4}" => 't',
            "\u{09A5}" => 'th',
            "\u{09A6}" => 'd',
            "\u{09A7}" => 'dh',
            "\u{09A8}" => 'n',
            "\u{09AA}" => 'p',
            "\u{09AB}" => 'ph',
            "\u{09AC}" => 'b',
            "\u{09AD}" => 'bh',
            "\u{09AE}" => 'm',
            "\u{09AF}" => 'j',
            "\u{09B0}" => 'r',
            "\u{09B2}" => 'l',
            "\u{09B6}" => 'sh',
            "\u{09B7}" => 'sh',
            "\u{09B8}" => 's',
            "\u{09B9}" => 'h',
            "\u{09DC}" => 'r',
            "\u{09DD}" => 'rh',
            "\u{09DF}" => 'y',
            "\u{09F0}" => 'r',
            "\u{09F1}" => 'w',
        ];
    }

    /**
     * Independent vowels.
     *
     * @return array<string,string>
     */
    public static function vowels(): array
    {
        return [
            "\u{0985}" => 'o',
            "\u{0986}" => 'a',
            "\u{0987}" => 'i',
            "\u{0988}" => 'i',
            "\u{0989}" => 'u',
            "\u{098A}" => 'u',
            "\u{098B}" => 'ri',
            "\u{098F}" => 'e',
            "\u{0990}" => 'oi',
            "\u{0993}" => 'o',
            "\u{0994}" => 'ou',
        ];
    }

    /**
     * Dependent vowel signs (kar).
     *
     * @return array<string,string>
     */
    public static function vowelSigns(): array
    {
        return [
            "\u{09BE}" => 'a',
            "\u{09BF}" => 'i',
            "\u{09C0}" => 'i',
            "\u{09C1}" => 'u',
            "\u{09C2}" => 'u',
            "\u{09C3}" => 'ri',
            "\u{09C7}" => 'e',
            "\u{09C8}" => 'oi',
            "\u{09CB}" => 'o',
            "\u{09CC}" => 'ou',
        ];
    }

    /**
     * Bangla digits.
     *
     * @return array<string,string>
     */
    public static function digits(): array
    {
        return [
            "\u{09E6}" => '0',
            "\u{09E7}" => '1',
            "\u{09E8}" => '2',
            "\u{09E9}" => '3',
            "\u{09EA}" => '4',
            "\u{09EB}" => '5',
            "\u{09EC}" => '6',
            "\u{09ED}" => '7',
            "\u{09EE}" => '8',
            "\u{09EF}" => '9',
        ];
    }

    /**
     * Signs that are not letters: anusvara, visarga, candrabindu (as a nasal "n"), khanda ta.
     *
     * @return array<string,string>
     */
    public static function signs(): array
    {
        return [
            self::ANUSVARA    => 'ng',
            self::VISARGA     => 'h',
            self::CANDRABINDU => 'n',
            self::KHANDA_TA   => 't',
        ];
    }

    /**
     * Conjuncts (consonant + hasanta + consonant ...) whose conventional
     * romanisation differs from simply joining the parts.
     *
     * @return array<string,string>
     */
    public static function conjuncts(): array
    {
        return [
            "\u{0995}\u{09CD}\u{09B7}" => 'kkh', // ksha.
            "\u{099C}\u{09CD}\u{099E}" => 'gy',  // jnya.
            "\u{0999}\u{09CD}\u{0995}" => 'nk',
            "\u{0999}\u{09CD}\u{0996}" => 'nkh',
            "\u{0999}\u{09CD}\u{0997}" => 'ng',
            "\u{0999}\u{09CD}\u{0998}" => 'ngh',
            "\u{09B8}\u{09CD}\u{09AC}" => 'sw',
            "\u{09B6}\u{09CD}\u{09AC}" => 'shw',
        ];
    }
}
