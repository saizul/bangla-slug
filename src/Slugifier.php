<?php
/**
 * Turns titles into Latin slugs.
 *
 * @package BanglaSlug
 */

declare(strict_types=1);

namespace SaizulAmin\BanglaSlug;

/**
 * Pure slug builder: stopword removal, transliteration, normalisation and
 * length limiting. No WordPress functions are used.
 */
final class Slugifier
{
    private Transliterator $transliterator;

    private string $separator;

    private int $maxLength;

    private bool $removeStopwords;

    /** @var array<string,true> */
    private array $stopwords = [];

    /**
     * @param array{separator?:string,max_length?:int,remove_stopwords?:bool,stopwords?:list<string>} $options
     */
    public function __construct(Transliterator $transliterator, array $options = [])
    {
        $this->transliterator  = $transliterator;
        $this->separator       = ($options['separator'] ?? '-') === '_' ? '_' : '-';
        $this->maxLength       = max(10, (int) ($options['max_length'] ?? 120));
        $this->removeStopwords = (bool) ($options['remove_stopwords'] ?? true);

        $list = $options['stopwords'] ?? array_merge(Stopwords::bangla(), Stopwords::english());
        foreach ($list as $word) {
            $word = mb_strtolower(trim((string) $word));
            if ($word !== '') {
                $this->stopwords[$word] = true;
            }
        }
    }

    /**
     * Build a slug from arbitrary text. Returns an empty string when nothing
     * usable remains.
     */
    public function slugify(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = Transliterator::normalize($text);

        if ($this->removeStopwords) {
            $text = $this->withoutStopwords($text);
        }

        $latin = mb_strtolower($this->transliterator->transliterate($text));
        $latin = str_replace(["'", "\u{2019}", "\u{2018}"], '', $latin);

        $quoted = preg_quote($this->separator, '/');
        $slug   = preg_replace('/[^a-z0-9]+/', $this->separator, $latin) ?? '';
        $slug   = trim((string) preg_replace('/' . $quoted . '{2,}/', $this->separator, $slug), $this->separator);

        return $this->limit($slug);
    }

    /**
     * True when the (possibly percent-encoded) slug contains Bangla characters
     * and would be stored as a percent-encoded string by WordPress.
     */
    public static function needsConversion(string $slug): bool
    {
        if ($slug === '') {
            return false;
        }

        return Transliterator::containsBangla(rawurldecode($slug));
    }

    /**
     * Drop stopword tokens, but never all of them.
     */
    private function withoutStopwords(string $text): string
    {
        $tokens = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        if ($tokens === false || count($tokens) < 2) {
            return $text;
        }

        $kept = [];
        foreach ($tokens as $token) {
            $bare = mb_strtolower((string) preg_replace('/^[\p{P}\p{S}\s]+|[\p{P}\p{S}\s]+$/u', '', $token));
            if ($bare === '' || !isset($this->stopwords[$bare])) {
                $kept[] = $token;
            }
        }

        return $kept === [] ? $text : implode(' ', $kept);
    }

    /**
     * Cut at a separator boundary when possible.
     */
    private function limit(string $slug): string
    {
        if (strlen($slug) <= $this->maxLength) {
            return $slug;
        }

        $cut = substr($slug, 0, $this->maxLength);
        $pos = strrpos($cut, $this->separator);

        if ($pos !== false && $pos >= (int) floor($this->maxLength / 2)) {
            $cut = substr($cut, 0, $pos);
        }

        return rtrim($cut, $this->separator);
    }
}
