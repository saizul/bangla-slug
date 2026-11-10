<?php
/**
 * Slugifier unit tests.
 *
 * @package BanglaSlug
 */

declare(strict_types=1);

namespace SaizulAmin\BanglaSlug\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SaizulAmin\BanglaSlug\Slugifier;
use SaizulAmin\BanglaSlug\Transliterator;

final class SlugifierTest extends TestCase
{
    private function make(array $options = [], array $map = []): Slugifier
    {
        return new Slugifier(new Transliterator($map), $options);
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function slugCases(): array
    {
        return [
            'simple'              => ['বাংলাদেশ', 'bangladesh'],
            'phrase'              => ['আমার সোনার বাংলা', 'amar-sonar-bangla'],
            'stopword and'        => ['ঢাকা এবং চট্টগ্রাম', 'dhaka-chottogram'],
            'stopword o'          => ['রহিম ও করিম', 'rohim-korim'],
            'stopword for'        => ['শিক্ষার জন্য প্রযুক্তি', 'shikkhar-projukti'],
            'digits'              => ['২০২৪ সালের বাজেট', '2024-saler-bajet'],
            'punctuation'         => ['বাংলা, ভাষা: ইতিহাস!', 'bangla-bhasha-itihas'],
            'danda'               => ['কথা শেষ।', 'kotha-shesh'],
            'question mark'       => ['এটা কী?', 'ki'],
            'mixed latin'         => ['WordPress টিউটোরিয়াল', 'wordpress-tiutoriyal'],
            'english stopword'    => ['The গাইড', 'gaid'],
            'html entity'         => ['ঢাকা &amp; খুলনা', 'dhaka-khulona'],
            'html tags'           => ['<b>বাংলা</b> ভাষা', 'bangla-bhasha'],
            'apostrophe'          => ["ডা. রহিম's", 'da-rohims'],
            'multiple spaces'     => ["ঢাকা    শহর", 'dhaka-shohor'],
            'leading trailing'    => ['  বাংলা  ', 'bangla'],
            'hyphen kept'         => ['বাংলা-ইংরেজি', 'bangla-ingreji'],
            'latin only'          => ['Hello World', 'hello-world'],
            'woocommerce product' => ['পুরুষদের পাঞ্জাবি - ১০০% সুতি', 'purushoder-panjabi-100-suti'],
            'only stopword'       => ['এবং', 'ebong'],
            'all stopwords'       => ['এবং ও', 'ebong-o'],
        ];
    }

    #[DataProvider('slugCases')]
    public function testSlugify(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->make()->slugify($input));
    }

    public function testStopwordsDisabled(): void
    {
        $this->assertSame(
            'dhaka-ebong-chottogram',
            $this->make(['remove_stopwords' => false])->slugify('ঢাকা এবং চট্টগ্রাম')
        );
    }

    public function testCustomStopwords(): void
    {
        $slug = $this->make(['stopwords' => ['ঢাকা']])->slugify('ঢাকা শহর');

        $this->assertSame('shohor', $slug);
    }

    public function testUnderscoreSeparator(): void
    {
        $this->assertSame('amar_sonar_bangla', $this->make(['separator' => '_'])->slugify('আমার সোনার বাংলা'));
    }

    public function testInvalidSeparatorFallsBackToHyphen(): void
    {
        $this->assertSame('amar-sonar', $this->make(['separator' => '.'])->slugify('আমার সোনার'));
    }

    public function testMaxLengthCutsAtWordBoundary(): void
    {
        $slug = $this->make(['max_length' => 20])->slugify('বাংলাদেশের স্বাধীনতা সংগ্রামের ইতিহাস');

        $this->assertLessThanOrEqual(20, strlen($slug));
        $this->assertStringEndsNotWith('-', $slug);
        $this->assertSame('bangladesher', $slug);
    }

    public function testMaxLengthHardCutWhenNoSeparator(): void
    {
        $slug = $this->make(['max_length' => 10])->slugify('বিশ্ববিদ্যালয়');

        $this->assertSame('bishwobidy', $slug);
    }

    public function testEmptyStringGivesEmptySlug(): void
    {
        $this->assertSame('', $this->make()->slugify(''));
    }

    public function testOnlyPunctuationGivesEmptySlug(): void
    {
        $this->assertSame('', $this->make()->slugify('।।। ???'));
    }

    public function testCustomMappingAffectsSlug(): void
    {
        $slug = $this->make([], ["\u{09AB}" => 'f'])->slugify('ফটো গ্যালারি');

        $this->assertSame('foto-gyalari', $slug);
    }

    public function testNeedsConversionForPercentEncodedBangla(): void
    {
        $this->assertTrue(Slugifier::needsConversion(rawurlencode('বাংলা')));
        $this->assertTrue(Slugifier::needsConversion(strtolower(rawurlencode('ঢাকা-২০২৪'))));
    }

    public function testNeedsConversionForRawBangla(): void
    {
        $this->assertTrue(Slugifier::needsConversion('বাংলা'));
    }

    public function testNeedsConversionFalseForLatinSlug(): void
    {
        $this->assertFalse(Slugifier::needsConversion('hello-world'));
        $this->assertFalse(Slugifier::needsConversion(''));
        $this->assertFalse(Slugifier::needsConversion('caf%c3%a9'));
    }

    public function testSlugsContainOnlySafeCharacters(): void
    {
        $slug = $this->make()->slugify('বাংলা ভাষা @ #১২৩ _ ঢাকা');

        $this->assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
    }
}
