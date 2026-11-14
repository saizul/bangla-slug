<?php
/**
 * Dictionary unit tests.
 *
 * @package BanglaSlug
 */

declare(strict_types=1);

namespace SaizulAmin\BanglaSlug\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SaizulAmin\BanglaSlug\Dictionary;
use SaizulAmin\BanglaSlug\Slugifier;
use SaizulAmin\BanglaSlug\Transliterator;

final class DictionaryTest extends TestCase
{
    /**
     * @return array<string,string>
     */
    private static function entries(): array
    {
        return [
            'কম্পিউটার'            => 'computer',
            'বাংলাদেশ'             => 'Bangladesh',
            'ঢাকা বিশ্ববিদ্যালয়'     => 'dhaka-university',
            'ঢাকা'                => 'dhaka',
            'গুগল'                => 'google',
            'কম'                  => 'kom',
            'wp'                  => 'wordpress',
            'বাংলা একাডেমি'        => 'bangla academy of letters',
        ];
    }

    private function slugifier(string $mode = Dictionary::SUFFIX_KEEP, array $options = []): Slugifier
    {
        $options['dictionary'] = new Dictionary(self::entries(), $mode);

        return new Slugifier(new Transliterator(), $options);
    }

    public function testParseReadsLinesAndSkipsNoise(): void
    {
        $text = "# names\n\nকম্পিউটার = computer\r\nno equals sign\n=orphan\nempty=\n  ঢাকা বিশ্ববিদ্যালয় =dhaka university \nকম্পিউটার=pc\nএ=b=c";

        $this->assertSame(
            [
                'কম্পিউটার'        => 'pc',
                'ঢাকা বিশ্ববিদ্যালয়' => 'dhaka university',
                'এ'               => 'b=c',
            ],
            Dictionary::parse($text)
        );
    }

    public function testParseEmptyText(): void
    {
        $this->assertSame([], Dictionary::parse(''));
    }

    public function testValuesAreSanitised(): void
    {
        $dictionary = new Dictionary(
            [
                'ক' => 'Dhaka-University!',
                'খ' => "Rahim's",
                'গ' => '***',
                ''  => 'x',
                '।' => 'danda',
            ]
        );

        $this->assertSame(['ক' => 'dhaka university', 'খ' => 'rahims'], $dictionary->entries());
        $this->assertSame(2, $dictionary->count());
        $this->assertFalse($dictionary->isEmpty());
    }

    public function testEmptyDictionary(): void
    {
        $dictionary = new Dictionary();

        $this->assertTrue($dictionary->isEmpty());
        $this->assertSame('ঢাকা শহর', $dictionary->apply('  ঢাকা   শহর '));
        $this->assertSame([], $dictionary->tokens('   '));
    }

    public function testKeysAreNormalised(): void
    {
        // Decomposed ya + nukta in the key, precomposed in the text and vice versa.
        $dictionary = new Dictionary(["বিশ্ববিদ্যাল\u{09AF}\u{09BC}" => 'university', '  ঢাকা,  ' => 'dhaka']);

        $this->assertSame('university', $dictionary->lookup("বিশ্ববিদ্যাল\u{09DF}"));
        $this->assertSame('dhaka', $dictionary->lookup('ঢাকা'));
        $this->assertNull($dictionary->lookup('খুলনা'));
    }

    public function testInvalidSuffixModeFallsBackToKeep(): void
    {
        $this->assertSame(Dictionary::SUFFIX_KEEP, (new Dictionary([], 'bogus'))->suffixMode());
        $this->assertSame(Dictionary::SUFFIX_DROP, Dictionary::validMode('drop'));
        $this->assertSame(Dictionary::SUFFIX_KEEP, Dictionary::validMode(''));
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function keepCases(): array
    {
        return [
            'exact word'          => ['কম্পিউটার শিক্ষা', 'computer-shikkha'],
            'uppercase value'     => ['বাংলাদেশ', 'bangladesh'],
            'genitive er'         => ['বাংলাদেশের ইতিহাস', 'bangladesher-itihas'],
            'locative e'          => ['কম্পিউটারে কাজ', 'computere-kaj'],
            'plural marker'       => ['কম্পিউটারগুলো নতুন', 'computergulo-notun'],
            'phrase beats word'   => ['ঢাকা বিশ্ববিদ্যালয় ভর্তি', 'dhaka-university-bhorti'],
            'phrase with suffix'  => ['ঢাকা বিশ্ববিদ্যালয়ের ছাত্র', 'dhaka-universityer-chhatr'],
            'word when no phrase' => ['ঢাকা শহর', 'dhaka-shohor'],
            'punctuation kept'    => ['"গুগল", ফেসবুক!', 'google-phesobuk'],
            'comma breaks phrase' => ['ঢাকা, বিশ্ববিদ্যালয়', 'dhaka-bishwobidyaloy'],
            'unknown ending'      => ['কমলা', 'komola'],
            'short entry suffix'  => ['কমের', 'komer'],
            'latin key any case'  => ['WP টিউটোরিয়াল', 'wordpress-tiutoriyal'],
            'no match'            => ['আমার সোনার বাংলা', 'amar-sonar-bangla'],
            'value not stopword'  => ['বাংলা একাডেমি', 'bangla-academy-of-letters'],
        ];
    }

    #[DataProvider('keepCases')]
    public function testSlugifyKeepMode(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->slugifier()->slugify($input));
    }

    public function testDropModeRemovesEnding(): void
    {
        $slugifier = $this->slugifier(Dictionary::SUFFIX_DROP);

        $this->assertSame('bangladesh-itihas', $slugifier->slugify('বাংলাদেশের ইতিহাস'));
        $this->assertSame('google-search', $slugifier->slugify('গুগলে search'));
        $this->assertSame('dhaka-university-chhatr', $slugifier->slugify('ঢাকা বিশ্ববিদ্যালয়ের ছাত্র'));
    }

    public function testOffModeMatchesExactWordsOnly(): void
    {
        $slugifier = $this->slugifier(Dictionary::SUFFIX_OFF);

        $this->assertSame('computer', $slugifier->slugify('কম্পিউটার'));
        $this->assertSame('bangladesher-itihas', $slugifier->slugify('বাংলাদেশের ইতিহাস'));
        $this->assertSame('kompiutare', $slugifier->slugify('কম্পিউটারে'));
    }

    public function testCustomSuffixList(): void
    {
        $dictionary = new Dictionary(['ঢাকা' => 'dhaka'], Dictionary::SUFFIX_DROP, ['বাসী']);

        $this->assertSame('dhaka', $dictionary->apply('ঢাকাবাসী'));
        $this->assertSame('ঢাকার', $dictionary->apply('ঢাকার'));
    }

    public function testSuffixAloneIsNotAMatch(): void
    {
        $dictionary = new Dictionary(['র' => 'r-letter']);

        // "র" is an exact entry, but "ের" must not match an empty stem.
        $this->assertSame('r letter', $dictionary->apply('র'));
        $this->assertSame('ের', $dictionary->apply('ের'));
    }

    public function testTokensFlagFixedEntries(): void
    {
        $tokens = (new Dictionary(self::entries()))->tokens('গুগল এবং ফেসবুক');

        $this->assertSame(
            [
                ['text' => 'google', 'fixed' => true],
                ['text' => 'এবং', 'fixed' => false],
                ['text' => 'ফেসবুক', 'fixed' => false],
            ],
            $tokens
        );
    }

    public function testDictionaryWordsAreNeverStopwords(): void
    {
        $slugifier = new Slugifier(
            new Transliterator(),
            ['stopwords' => ['এবং', 'ঢাকা'], 'dictionary' => ['ঢাকা' => 'dhaka']]
        );

        $this->assertSame('dhaka-chottogram', $slugifier->slugify('ঢাকা এবং চট্টগ্রাম'));
    }

    public function testDictionaryRespectsUnderscoreSeparator(): void
    {
        $slug = $this->slugifier(Dictionary::SUFFIX_KEEP, ['separator' => '_'])->slugify('ঢাকা বিশ্ববিদ্যালয়');

        $this->assertSame('dhaka_university', $slug);
    }

    public function testDictionaryRespectsMaxLength(): void
    {
        $slug = $this->slugifier(Dictionary::SUFFIX_KEEP, ['max_length' => 12])->slugify('বাংলা একাডেমি');

        $this->assertSame('bangla', $slug);
    }

    public function testDictionaryWinsOverCharacterMapping(): void
    {
        $slugifier = new Slugifier(
            new Transliterator(["\u{0995}" => 'q']),
            ['dictionary' => ['কম্পিউটার' => 'computer']]
        );

        $this->assertSame('computer-qaj', $slugifier->slugify('কম্পিউটার কাজ'));
    }

    public function testArrayDictionaryOptionAndAccessor(): void
    {
        $slugifier = new Slugifier(new Transliterator(), ['dictionary' => ['গুগল' => 'google']]);

        $this->assertSame(1, $slugifier->dictionary()->count());
        $this->assertSame('google', $slugifier->slugify('গুগল'));
    }

    public function testSlugifierWithoutDictionaryIsUnchanged(): void
    {
        $slugifier = new Slugifier(new Transliterator());

        $this->assertTrue($slugifier->dictionary()->isEmpty());
        $this->assertSame('dhaka-chottogram', $slugifier->slugify('ঢাকা এবং চট্টগ্রাম'));
    }
}
