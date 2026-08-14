<?php

namespace Tests\Unit;

use App\Support\Display;
use App\Support\PublicId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** API_REQUIREMENTS.md §1.7 display strings vs raw values. */
class DisplayTest extends TestCase
{
    #[DataProvider('salaryCases')]
    public function test_it_formats_salary_ranges(?int $min, ?int $max, ?string $expected): void
    {
        $this->assertSame($expected, Display::salary($min, $max));
    }

    public static function salaryCases(): array
    {
        return [
            'range' => [25000, 40000, '₹25K – ₹40K'],
            'lakhs' => [100000, 150000, '₹1L – ₹1.5L'],
            'identical bounds collapse' => [30000, 30000, '₹30K'],
            'only a maximum' => [null, 40000, '₹40K'],
            'only a minimum' => [35000, null, '₹35K'],
            'sub-thousand' => [900, 900, '₹900'],
            'nothing' => [null, null, null],
        ];
    }

    #[DataProvider('amountCases')]
    public function test_it_parses_display_amounts(?string $input, ?int $expected): void
    {
        $this->assertSame($expected, Display::parseAmount($input));
    }

    public static function amountCases(): array
    {
        return [
            ['35K', 35000],
            ['1L', 100000],
            ['1.5L', 150000],
            ['₹25,000', 25000],
            ['20', 20],
            ['', null],
            [null, null],
            ['not a number', null],
        ];
    }

    #[DataProvider('experienceCases')]
    public function test_it_parses_experience_bands(?string $band, array $expected): void
    {
        $this->assertSame($expected, Display::experienceYears($band));
    }

    public static function experienceCases(): array
    {
        return [
            'fresher' => ['Fresher', [0, 0]],
            'en dash band' => ['1–3 yrs', [1, 3]],
            'hyphen band' => ['5-10 yrs', [5, 10]],
            'open ended' => ['10+ yrs', [10, null]],
            'sub-year band' => ['0–1 yr', [0, 1]],
            'freeform range' => ['2–4 yrs', [2, 4]],
            'single value' => ['3 yrs', [3, 3]],
            'empty' => ['', [null, null]],
            'null' => [null, [null, null]],
        ];
    }

    public function test_it_cleans_freeform_lists(): void
    {
        $this->assertSame(['ICU', 'OPD'], Display::cleanList(['ICU', ' ICU ', 'OPD', '', '   ', null]));
        $this->assertSame([], Display::cleanList('not an array'));
        $this->assertSame([], Display::cleanList(null));
    }

    public function test_public_ids_round_trip(): void
    {
        $this->assertSame('j_501', PublicId::encode('j', 501));
        $this->assertSame(501, PublicId::decode('j', 'j_501'));
        // Bare ids are accepted too, so route params stay forgiving.
        $this->assertSame(501, PublicId::decode('j', '501'));
        $this->assertNull(PublicId::decode('j', 'j_abc'));
        $this->assertNull(PublicId::decode('j', null));
    }
}
