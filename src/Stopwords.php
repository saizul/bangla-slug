<?php
/**
 * Default stopword lists.
 *
 * @package BanglaSlug
 */

declare(strict_types=1);

namespace SaizulAmin\BanglaSlug;

/**
 * Short function words that add nothing to a URL.
 */
final class Stopwords
{
    /**
     * @return list<string>
     */
    public static function bangla(): array
    {
        return [
            'এবং', 'ও', 'বা', 'কিংবা', 'অথবা', 'এর', 'এই', 'ওই', 'সেই', 'যে', 'যা',
            'এটি', 'এটা', 'ওটা', 'একটি', 'একটা', 'থেকে', 'জন্য', 'হয়', 'হয়েছে',
            'দিয়ে', 'মধ্যে', 'সাথে', 'সঙ্গে', 'কিন্তু', 'তবে', 'তাই', 'যেন', 'কেন',
        ];
    }

    /**
     * @return list<string>
     */
    public static function english(): array
    {
        return ['a', 'an', 'the', 'of', 'and', 'or', 'in', 'on', 'to', 'for', 'is', 'are'];
    }
}
