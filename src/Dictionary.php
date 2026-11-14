<?php
/**
 * Whole-word and phrase overrides applied before transliteration.
 *
 * @package BanglaSlug
 */

declare(strict_types=1);

namespace SaizulAmin\BanglaSlug;

/**
 * Maps whole Bangla words or phrases to a fixed Latin spelling, for names,
 * brands and loanwords that phonetic rules cannot get right (for example
 * "কম্পিউটার" => "computer" or "ঢাকা বিশ্ববিদ্যালয়" => "dhaka university").
 *
 * Matching works on whitespace separated tokens, ignores punctuation at the
 * edges of a token, is case-insensitive for Latin keys and prefers the longest
 * phrase. The last word of a phrase may carry a common Bangla case ending
 * (বাংলাদেশের, কম্পিউটারে ...); the ending is then kept and transliterated,
 * dropped, or not matched at all, depending on the suffix mode.
 *
 * No WordPress functions are used.
 */
final class Dictionary
{
    public const SUFFIX_KEEP = 'keep';
    public const SUFFIX_DROP = 'drop';
    public const SUFFIX_OFF  = 'off';

    /** Punctuation, symbols and spaces at the start or end of a token. */
    private const EDGE = '/^([\p{P}\p{S}\s]*)(.*?)([\p{P}\p{S}\s]*)$/us';

    /** @var array<string,string> Normalised key => Latin words separated by single spaces. */
    private array $entries = [];

    /** @var list<string> Normalised suffixes, longest first. */
    private array $suffixes = [];

    private string $suffixMode;

    private int $maxWords = 0;

    /**
     * @param array<string,string> $entries    Bangla (or Latin) word or phrase => Latin spelling.
     * @param string               $suffixMode One of the SUFFIX_* constants.
     * @param list<string>|null    $suffixes   Case endings to recognise, null for the defaults.
     */
    public function __construct(array $entries = [], string $suffixMode = self::SUFFIX_KEEP, ?array $suffixes = null)
    {
        $this->suffixMode = self::validMode($suffixMode);

        foreach ($entries as $from => $to) {
            $key   = self::key((string) $from);
            $value = self::value((string) $to);
            if ($key === '' || $value === '') {
                continue;
            }
            $this->entries[$key] = $value;
            $this->maxWords      = max($this->maxWords, substr_count($key, ' ') + 1);
        }

        if ($this->suffixMode !== self::SUFFIX_OFF) {
            foreach ($suffixes ?? self::defaultSuffixes() as $suffix) {
                $suffix = mb_strtolower(Transliterator::normalize(trim((string) $suffix)));
                if ($suffix !== '' && !in_array($suffix, $this->suffixes, true)) {
                    $this->suffixes[] = $suffix;
                }
            }
            usort(
                $this->suffixes,
                static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a)
            );
        }
    }

    /**
     * Common Bangla case endings and plural / classifier markers.
     *
     * @return list<string>
     */
    public static function defaultSuffixes(): array
    {
        return [
            'গুলোর', 'গুলির', 'গুলো', 'গুলি', 'দের', 'য়ের', 'য়ে', 'েরা', 'ের', 'এর', 'র', 'য়',
            'কে', 'তে', 'টি', 'টা', 'রা', 'ে',
        ];
    }

    /**
     * Normalise a suffix mode, falling back to SUFFIX_KEEP.
     */
    public static function validMode(string $mode): string
    {
        return in_array($mode, [self::SUFFIX_KEEP, self::SUFFIX_DROP, self::SUFFIX_OFF], true) ? $mode : self::SUFFIX_KEEP;
    }

    /**
     * Parse "word=latin" lines. Blank lines, lines starting with # and lines
     * without "=" are ignored; a later line wins over an earlier one.
     *
     * @return array<string,string>
     */
    public static function parse(string $text): array
    {
        $map = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$from, $to] = array_map('trim', explode('=', $line, 2));
            if ($from !== '' && $to !== '') {
                $map[$from] = $to;
            }
        }

        return $map;
    }

    /**
     * Number of usable entries.
     */
    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * All usable entries, keyed by their normalised form.
     *
     * @return array<string,string>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function suffixMode(): string
    {
        return $this->suffixMode;
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    /**
     * The Latin spelling for a single word or phrase, or null.
     */
    public function lookup(string $phrase): ?string
    {
        return $this->entries[self::key($phrase)] ?? null;
    }

    /**
     * Split text into tokens and replace dictionary matches.
     *
     * Each returned token has the token text and whether it came from the
     * dictionary ("fixed"). Fixed tokens hold Latin text, optionally followed by
     * a kept Bangla suffix, and must not be treated as stopwords.
     *
     * @return list<array{text:string,fixed:bool}>
     */
    public function tokens(string $text): array
    {
        $words = preg_split('/\s+/u', trim(Transliterator::normalize($text)), -1, PREG_SPLIT_NO_EMPTY);
        if ($words === false || $words === []) {
            return [];
        }

        if ($this->entries === []) {
            return array_map(static fn (string $w): array => ['text' => $w, 'fixed' => false], $words);
        }

        $parts = [];
        foreach ($words as $word) {
            preg_match(self::EDGE, $word, $m);
            $parts[] = [
                'lead'  => $m[1] ?? '',
                'bare'  => mb_strtolower($m[2] ?? $word),
                'trail' => $m[3] ?? '',
                'raw'   => $word,
            ];
        }

        $out   = [];
        $count = count($parts);
        $i     = 0;

        while ($i < $count) {
            $match = $this->matchAt($parts, $i);
            if ($match === null) {
                $out[] = ['text' => $parts[$i]['raw'], 'fixed' => false];
                $i++;
                continue;
            }

            [$length, $latin, $suffix] = $match;
            $last                      = $parts[$i + $length - 1];

            $out[] = [
                'text'  => $parts[$i]['lead'] . $latin . $suffix . $last['trail'],
                'fixed' => true,
            ];
            $i += $length;
        }

        return $out;
    }

    /**
     * Replace dictionary matches in a string and return the result.
     */
    public function apply(string $text): string
    {
        return implode(' ', array_column($this->tokens($text), 'text'));
    }

    /**
     * Longest match starting at token $i.
     *
     * @param list<array{lead:string,bare:string,trail:string,raw:string}> $parts
     * @return array{0:int,1:string,2:string}|null Token count, Latin text, kept suffix.
     */
    private function matchAt(array $parts, int $i): ?array
    {
        $max = min($this->maxWords, count($parts) - $i);

        for ($n = $max; $n >= 1; $n--) {
            $words = [];
            for ($k = 0; $k < $n; $k++) {
                $words[] = $parts[$i + $k]['bare'];
            }

            // Inner punctuation (a comma after the first word ...) breaks a phrase.
            for ($k = 0; $k < $n - 1; $k++) {
                if ($parts[$i + $k]['trail'] !== '' || ($k > 0 && $parts[$i + $k]['lead'] !== '')) {
                    continue 2;
                }
            }
            if ($n > 1 && $parts[$i + $n - 1]['lead'] !== '') {
                continue;
            }

            $key = implode(' ', $words);
            if ($key === '') {
                continue;
            }
            if (isset($this->entries[$key])) {
                return [$n, $this->entries[$key], ''];
            }

            $lastWord = $words[$n - 1];
            foreach ($this->suffixes as $suffix) {
                if (!str_ends_with($lastWord, $suffix) || strlen($lastWord) <= strlen($suffix)) {
                    continue;
                }
                $words[$n - 1] = substr($lastWord, 0, -strlen($suffix));
                $stemKey       = implode(' ', $words);
                if (isset($this->entries[$stemKey])) {
                    return [$n, $this->entries[$stemKey], $this->suffixMode === self::SUFFIX_DROP ? '' : $suffix];
                }
            }
        }

        return null;
    }

    /**
     * Normalised lookup key: canonical Bangla, lowercase, single spaces and no
     * punctuation at the edges of each word.
     */
    private static function key(string $phrase): string
    {
        $phrase = mb_strtolower(Transliterator::normalize($phrase));
        $words  = [];
        foreach (preg_split('/\s+/u', trim($phrase), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $word = (string) preg_replace('/^[\p{P}\p{S}]+|[\p{P}\p{S}]+$/u', '', $word);
            if ($word !== '') {
                $words[] = $word;
            }
        }

        return implode(' ', $words);
    }

    /**
     * Sanitised Latin value: lowercase letters and digits, words separated by
     * single spaces so the slug separator setting is respected.
     */
    private static function value(string $latin): string
    {
        $latin = strtolower(str_replace(["'", "\u{2019}", "\u{2018}"], '', $latin));

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $latin));
    }
}
