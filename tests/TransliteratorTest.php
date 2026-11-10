<?php
/**
 * Transliterator unit tests.
 *
 * @package BanglaSlug
 */

declare(strict_types=1);

namespace SaizulAmin\BanglaSlug\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SaizulAmin\BanglaSlug\Transliterator;

final class TransliteratorTest extends TestCase
{
    /**
     * @return array<string,array{string,string}>
     */
    public static function defaultCases(): array
    {
        $cases = [
            // Common words and places.
            ['বাংলা', 'bangla'],
            ['বাংলাদেশ', 'bangladesh'],
            ['ঢাকা', 'dhaka'],
            ['চট্টগ্রাম', 'chottogram'],
            ['সিলেট', 'silet'],
            ['রাজশাহী', 'rajoshahi'],
            ['খুলনা', 'khulona'],
            ['বরিশাল', 'borishal'],
            ['কলম', 'kolom'],
            ['মানুষ', 'manush'],
            ['আমার সোনার বাংলা', 'amar sonar bangla'],
            ['ভালোবাসা', 'bhalobasa'],
            ['বই', 'boi'],
            ['দেশ', 'desh'],
            ['নদী', 'nodi'],
            ['পানি', 'pani'],
            ['মা', 'ma'],
            ['বাবা', 'baba'],
            ['ভাত', 'bhat'],
            ['মাছ', 'machh'],
            // Vowels.
            ['অ', 'o'],
            ['আ', 'a'],
            ['ইট', 'it'],
            ['ঈদ', 'id'],
            ['উপর', 'upor'],
            ['ঊষা', 'usha'],
            ['ঋণ', 'rin'],
            ['এখন', 'ekhon'],
            ['ঐ', 'oi'],
            ['ওষুধ', 'oshudh'],
            ['ঔষধ', 'oushodh'],
            // Vowel signs (kar).
            ['কা', 'ka'],
            ['কি', 'ki'],
            ['কী', 'ki'],
            ['কু', 'ku'],
            ['কূ', 'ku'],
            ['কৃ', 'kri'],
            ['কে', 'ke'],
            ['কৈ', 'koi'],
            ['কো', 'ko'],
            ['কৌ', 'kou'],
            ['কৌশল', 'koushol'],
            ['সৈকত', 'soikot'],
            ['কৃষক', 'krishok'],
            // Inherent vowel and final schwa deletion.
            ['কর', 'kor'],
            ['করবে', 'korobe'],
            ['সরকার', 'sorokar'],
            ['কমলা', 'komola'],
            ['কলকাতা', 'kolokata'],
            // Anusvara, visarga, candrabindu, khanda ta.
            ['রং', 'rong'],
            ['বাংলাদেশের', 'bangladesher'],
            ['চাঁদ', 'chand'],
            ['হাঁস', 'hans'],
            ['দুঃখ', 'duhkh'],
            ['হঠাৎ', 'hothat'],
            ['সংগীত', 'songgit'],
            // Conjuncts.
            ['শিক্ষা', 'shikkha'],
            ['রক্ষা', 'rokkha'],
            ['বিজ্ঞান', 'bigyan'],
            ['জ্ঞান', 'gyan'],
            ['শঙ্কা', 'shonka'],
            ['গঙ্গা', 'gonga'],
            ['পদ্মা', 'podma'],
            ['প্রথম', 'prothom'],
            ['প্রেম', 'prem'],
            ['ক্রম', 'krom'],
            ['ত্রিশ', 'trish'],
            ['স্কুল', 'skul'],
            ['স্টেশন', 'steshon'],
            ['ছাত্র', 'chhatr'],
            ['পত্র', 'potr'],
            ['চিত্র', 'chitr'],
            ['আল্লাহ', 'allah'],
            ['উত্তর', 'uttor'],
            ['বড়', 'bor'],
            ['পড়া', 'pora'],
            ['আষাঢ়', 'asharh'],
            ['বছর', 'bochhor'],
            ['যশ', 'josh'],
            ['যাত্রা', 'jatra'],
            ['ময়দা', 'moyoda'],
            ['দয়া', 'doya'],
            // Reph.
            ['কর্ম', 'korm'],
            ['দুর্গা', 'durga'],
            ['সর্বনাশ', 'sorbonash'],
            ['বর্ষা', 'borsha'],
            ['মার্চ', 'march'],
            // Jophola and bophola.
            ['সত্য', 'sotyo'],
            ['বিদ্যা', 'bidya'],
            ['বিদ্যালয়', 'bidyaloy'],
            ['বিশ্ব', 'bishwo'],
            ['স্বাধীন', 'swadhin'],
            ['স্বাধীনতা', 'swadhinota'],
            ['বিশ্ববিদ্যালয়', 'bishwobidyaloy'],
            ['ঢাকা বিশ্ববিদ্যালয়', 'dhaka bishwobidyaloy'],
            // Digits.
            ['০', '0'],
            ['১২৩৪৫৬৭৮৯', '123456789'],
            ['২০২৪', '2024'],
            ['২০২৪ সাল', '2024 sal'],
            ['৫ম', '5m'],
            ['পৃষ্ঠা ১০', 'prishtha 10'],
            ['৳১০০', 'tk100'],
            // Mixed content and passthrough.
            ['WordPress টিউটোরিয়াল', 'WordPress tiutoriyal'],
            ['Hello world', 'Hello world'],
            ['', ''],
            ['123', '123'],
            ['ঢাকা-২০২৪', 'dhaka-2024'],
            // Normalisation.
            ["\u{09A1}\u{09BC}", 'r'],
            ["\u{09AF}\u{09BC}", 'y'],
            ["\u{0995}\u{09C7}\u{09BE}", 'ko'],
            ["\u{0995}\u{09C7}\u{09D7}", 'kou'],
            ["\u{0995}\u{200C}\u{09BE}", 'ka'],
            // Danda passes through unchanged.
            ['শেষ।', 'shesh।'],
        ];

        $out = [];
        foreach ($cases as [$in, $expected]) {
            $out[$in . ' => ' . $expected] = [$in, $expected];
        }

        return $out;
    }

    #[DataProvider('defaultCases')]
    public function testDefaultTransliteration(string $input, string $expected): void
    {
        $this->assertSame($expected, (new Transliterator())->transliterate($input));
    }

    public function testSchwaNoneKeepsFinalInherentVowel(): void
    {
        $t = new Transliterator([], Transliterator::SCHWA_NONE);

        $this->assertSame('kolomo', $t->transliterate('কলম'));
        $this->assertSame('manusho', $t->transliterate('মানুষ'));
        $this->assertSame('bangla', $t->transliterate('বাংলা'));
    }

    public function testSchwaMedialDropsMiddleVowel(): void
    {
        $t = new Transliterator([], Transliterator::SCHWA_MEDIAL);

        $this->assertSame('kolkata', $t->transliterate('কলকাতা'));
        $this->assertSame('sorkar', $t->transliterate('সরকার'));
        $this->assertSame('kolom', $t->transliterate('কলম'));
    }

    public function testInvalidSchwaModeFallsBackToFinal(): void
    {
        $this->assertSame('kolom', (new Transliterator([], 'bogus'))->transliterate('কলম'));
    }

    public function testSingleCharacterOverride(): void
    {
        $t = new Transliterator(["\u{0996}" => 'kh', "\u{09AB}" => 'f']);

        $this->assertSame('foto', $t->transliterate('ফটো'));
        $this->assertSame('bhalobasa', $t->transliterate('ভালোবাসা'));
    }

    public function testConjunctOverride(): void
    {
        $t = new Transliterator(["\u{0995}\u{09CD}\u{09B7}" => 'ksh']);

        $this->assertSame('shiksha', $t->transliterate('শিক্ষা'));
        $this->assertSame('roksha', $t->transliterate('রক্ষা'));
    }

    public function testOverrideValuesAreLowercased(): void
    {
        $t = new Transliterator(["\u{0995}" => 'KK']);

        $this->assertSame('kkaa', $t->transliterate('কা') . 'a');
    }

    public function testDigitOverride(): void
    {
        $t = new Transliterator(["\u{09E6}" => 'zero']);

        $this->assertSame('zero1', $t->transliterate('০১'));
    }

    public function testUnknownCharacterOverrideIsAdded(): void
    {
        $t = new Transliterator(["\u{09F2}" => 'x']);

        $this->assertSame('x', $t->transliterate("\u{09F2}"));
    }

    public function testEmptyOverrideKeyIsIgnored(): void
    {
        $t = new Transliterator(['' => 'x']);

        $this->assertSame('bangla', $t->transliterate('বাংলা'));
    }

    public function testContainsBangla(): void
    {
        $this->assertTrue(Transliterator::containsBangla('Hello বাংলা'));
        $this->assertFalse(Transliterator::containsBangla('Hello world'));
        $this->assertFalse(Transliterator::containsBangla(''));
    }

    public function testNormalizeIsIdempotent(): void
    {
        $once = Transliterator::normalize("\u{09A1}\u{09BC}\u{09C7}\u{09BE}");

        $this->assertSame($once, Transliterator::normalize($once));
    }

    public function testOutputContainsNoBanglaForPureBanglaInput(): void
    {
        $out = (new Transliterator())->transliterate('এটি একটি পরীক্ষা: বাংলা ভাষা ও সাহিত্য, ২০২৪।');

        $this->assertFalse(Transliterator::containsBangla($out));
    }

    public function testPunctuationAndSpacingPreserved(): void
    {
        $this->assertSame('dhaka, bangladesh!', (new Transliterator())->transliterate('ঢাকা, বাংলাদেশ!'));
    }

    public function testStrayHasantaAtWordEndKeepsConsonantClosed(): void
    {
        $this->assertSame('t', (new Transliterator())->transliterate("\u{09A4}\u{09CD}"));
    }

    public function testTransliterationIsDeterministic(): void
    {
        $t = new Transliterator();

        $this->assertSame($t->transliterate('বিশ্ববিদ্যালয়'), $t->transliterate('বিশ্ববিদ্যালয়'));
    }
}
