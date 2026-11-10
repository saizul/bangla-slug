<?php
/**
 * Pure Bangla to Latin phonetic transliteration.
 *
 * @package BanglaSlug
 */

declare(strict_types=1);

namespace SaizulAmin\BanglaSlug;

/**
 * Converts Bangla script to readable lowercase Latin text.
 *
 * The class has no WordPress dependency so it can be unit tested on its own.
 *
 * Approach: every Bangla word is split into syllable-like units (a consonant
 * cluster joined by hasanta, plus an optional vowel sign). Consonants that do
 * not carry a vowel sign receive the inherent vowel "o" unless the schwa
 * deletion mode removes it (by default at the end of a word).
 */
final class Transliterator
{
    public const SCHWA_NONE   = 'none';
    public const SCHWA_FINAL  = 'final';
    public const SCHWA_MEDIAL = 'medial';

    /** @var array<string,string> */
    private array $consonants;

    /** @var array<string,string> */
    private array $vowels;

    /** @var array<string,string> */
    private array $vowelSigns;

    /** @var array<string,string> */
    private array $signs;

    /** @var array<string,string> */
    private array $digits;

    /** @var array<string,string> */
    private array $other = [];

    /** @var list<array{0:list<string>,1:string}> Conjuncts, longest first. */
    private array $conjuncts = [];

    private string $schwa;

    /**
     * @param array<string,string> $overrides Extra or replacement mappings. A key
     *                                        containing a hasanta is treated as a
     *                                        conjunct (for example "ক্ষ" => "ksh").
     * @param string               $schwa     One of the SCHWA_* constants.
     */
    public function __construct(array $overrides = [], string $schwa = self::SCHWA_FINAL)
    {
        $this->schwa = in_array($schwa, [self::SCHWA_NONE, self::SCHWA_FINAL, self::SCHWA_MEDIAL], true)
            ? $schwa
            : self::SCHWA_FINAL;

        $this->consonants = Mapping::consonants();
        $this->vowels     = Mapping::vowels();
        $this->vowelSigns = Mapping::vowelSigns();
        $this->signs      = Mapping::signs();
        $this->digits     = Mapping::digits();
        $this->other      = ["\u{09F3}" => 'tk'];

        $conjuncts = Mapping::conjuncts();

        foreach ($overrides as $from => $to) {
            $from = (string) $from;
            $to   = strtolower((string) $to);

            if ($from === '') {
                continue;
            }

            if (str_contains($from, Mapping::HASANTA) && mb_strlen($from) > 1) {
                $conjuncts[$from] = $to;
                continue;
            }

            $matched = false;
            foreach (['consonants', 'vowels', 'vowelSigns', 'signs', 'digits'] as $table) {
                if (isset($this->{$table}[$from])) {
                    $this->{$table}[$from] = $to;
                    $matched               = true;
                }
            }
            if (!$matched) {
                $this->other[$from] = $to;
            }
        }

        foreach ($conjuncts as $sequence => $roman) {
            $parts = array_values(array_filter(
                explode(Mapping::HASANTA, (string) $sequence),
                static fn (string $p): bool => $p !== ''
            ));
            if (count($parts) > 1) {
                $this->conjuncts[] = [$parts, strtolower($roman)];
            }
        }
        usort(
            $this->conjuncts,
            static fn (array $a, array $b): int => count($b[0]) <=> count($a[0])
        );
    }

    /**
     * Transliterate every Bangla run in $text. Non-Bangla characters are
     * returned unchanged.
     */
    public function transliterate(string $text): string
    {
        if ($text === '' || !self::containsBangla($text)) {
            return $text;
        }

        $text = self::normalize($text);

        $result = preg_replace_callback(
            '/[\x{0980}-\x{09FF}]+/u',
            fn (array $m): string => $this->transliterateWord($m[0]),
            $text
        );

        return $result ?? $text;
    }

    /**
     * Whether the string contains at least one character of the Bengali block.
     */
    public static function containsBangla(string $text): bool
    {
        return preg_match('/[\x{0980}-\x{09FF}]/u', $text) === 1;
    }

    /**
     * Canonical composition: precomposed nukta letters, split vowel signs and
     * removal of zero width joiners.
     */
    public static function normalize(string $text): string
    {
        return str_replace(
            [
                "\u{09A1}\u{09BC}",
                "\u{09A2}\u{09BC}",
                "\u{09AF}\u{09BC}",
                "\u{09C7}\u{09BE}",
                "\u{09C7}\u{09D7}",
                "\u{200C}",
                "\u{200D}",
            ],
            [
                "\u{09DC}",
                "\u{09DD}",
                "\u{09DF}",
                "\u{09CB}",
                "\u{09CC}",
                '',
                '',
            ],
            $text
        );
    }

    /**
     * @param string $word A run of characters from the Bengali block.
     */
    private function transliterateWord(string $word): string
    {
        $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            return $word;
        }

        $units = $this->parseUnits($chars);
        $out   = '';

        // Index of the last pronounced unit (hasanta and nukta are silent).
        $last = -1;
        foreach ($units as $i => $unit) {
            if ($unit['type'] !== 'silent') {
                $last = $i;
            }
        }

        $prevOpen = false;

        foreach ($units as $i => $unit) {
            switch ($unit['type']) {
                case 'consonant':
                    $out .= $unit['roman'];
                    if ($unit['vowel'] !== null) {
                        $out     .= $unit['vowel'];
                        $prevOpen = true;
                        break;
                    }
                    if ($unit['closed']) {
                        $prevOpen = false;
                        break;
                    }

                    $add = $this->inherentVowel($units, $i, $last, $prevOpen);
                    $out .= $add;
                    $prevOpen = $add !== '';
                    break;

                case 'vowel':
                    $out     .= $unit['roman'];
                    $prevOpen = true;
                    break;

                case 'silent':
                    break;

                default:
                    $out     .= $unit['roman'];
                    $prevOpen = false;
                    break;
            }
        }

        return $out;
    }

    /**
     * Decide whether a bare consonant receives the inherent vowel.
     *
     * @param list<array<string,mixed>> $units
     */
    private function inherentVowel(array $units, int $i, int $last, bool $prevOpen): string
    {
        $unit = $units[$i];

        if ($this->schwa === self::SCHWA_NONE) {
            return 'o';
        }

        if ($i >= $last) {
            // Word final: keep an "o" only after a y/w phala (satya => sotyo).
            return $unit['glide'] ? 'o' : '';
        }

        if ($this->schwa === self::SCHWA_MEDIAL
            && $unit['letters'] === 1
            && $i > 0
            && $prevOpen
            && ($units[$i + 1]['type'] ?? '') === 'consonant'
            && ($units[$i + 1]['vowel'] ?? null) !== null
        ) {
            return '';
        }

        return 'o';
    }

    /**
     * @param list<string> $chars
     * @return list<array<string,mixed>>
     */
    private function parseUnits(array $chars): array
    {
        $units = [];
        $n     = count($chars);
        $i     = 0;

        while ($i < $n) {
            $ch = $chars[$i];

            if (isset($this->consonants[$ch])) {
                $letters = [$ch];
                $i++;
                while ($i + 1 < $n && $chars[$i] === Mapping::HASANTA && isset($this->consonants[$chars[$i + 1]])) {
                    $letters[] = $chars[$i + 1];
                    $i        += 2;
                }

                $closed = false;
                if ($i < $n && $chars[$i] === Mapping::HASANTA) {
                    $closed = true;
                    $i++;
                }

                $vowel = null;
                if (!$closed && $i < $n && isset($this->vowelSigns[$chars[$i]])) {
                    $vowel = $this->vowelSigns[$chars[$i]];
                    $i++;
                }

                [$roman, $glide] = $this->renderCluster($letters);

                $units[] = [
                    'type'    => 'consonant',
                    'roman'   => $roman,
                    'vowel'   => $vowel,
                    'closed'  => $closed,
                    'glide'   => $glide,
                    'letters' => count($letters),
                ];
                continue;
            }

            $i++;

            if (isset($this->vowels[$ch])) {
                $units[] = ['type' => 'vowel', 'roman' => $this->vowels[$ch]];
            } elseif (isset($this->vowelSigns[$ch])) {
                $units[] = ['type' => 'vowel', 'roman' => $this->vowelSigns[$ch]];
            } elseif (isset($this->digits[$ch])) {
                $units[] = ['type' => 'digit', 'roman' => $this->digits[$ch]];
            } elseif (isset($this->signs[$ch])) {
                $roman   = $this->signs[$ch];
                $units[] = $roman === '' ? ['type' => 'silent', 'roman' => ''] : ['type' => 'sign', 'roman' => $roman];
            } elseif (isset($this->other[$ch])) {
                $units[] = ['type' => 'sign', 'roman' => $this->other[$ch]];
            } elseif ($ch === Mapping::HASANTA || $ch === Mapping::NUKTA) {
                $units[] = ['type' => 'silent', 'roman' => ''];
            } else {
                // Unknown letter or punctuation of the block: act as a separator.
                $units[] = ['type' => 'sign', 'roman' => ' '];
            }
        }

        return $units;
    }

    /**
     * Romanise a consonant cluster.
     *
     * @param list<string> $letters
     * @return array{0:string,1:bool} Romanisation and whether it ends in a y/w phala.
     */
    private function renderCluster(array $letters): array
    {
        $out   = '';
        $glide = false;
        $count = count($letters);
        $k     = 0;

        while ($k < $count) {
            foreach ($this->conjuncts as [$parts, $roman]) {
                $len = count($parts);
                if ($k + $len <= $count && array_slice($letters, $k, $len) === $parts) {
                    $out  .= $roman;
                    $k    += $len;
                    $glide = str_ends_with($roman, 'w') || str_ends_with($roman, 'y');
                    continue 2;
                }
            }

            $letter = $letters[$k];
            $glide  = false;

            // A ba/ya directly after a reph (র্) is a full consonant, not a phala.
            $afterReph = $k > 0 && $letters[$k - 1] === "\u{09B0}";

            if ($k > 0 && !$afterReph && $letter === "\u{09AC}") {
                $out  .= 'w';
                $glide = true;
            } elseif ($k > 0 && !$afterReph && $letter === "\u{09AF}") {
                $out  .= 'y';
                $glide = true;
            } else {
                $out .= $this->consonants[$letter];
            }
            $k++;
        }

        return [$out, $glide];
    }
}
