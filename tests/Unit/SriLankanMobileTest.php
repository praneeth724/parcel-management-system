<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Rules\SriLankanMobile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SriLankanMobileTest extends TestCase
{
    #[Test]
    #[DataProvider('validNumbers')]
    public function it_accepts_valid_sri_lankan_mobile_numbers(string $input): void
    {
        $this->assertTrue(
            $this->passes($input),
            "Expected [{$input}] to be accepted."
        );
    }

    #[Test]
    #[DataProvider('invalidNumbers')]
    public function it_rejects_anything_that_is_not_a_sri_lankan_mobile(string $input): void
    {
        $this->assertFalse(
            $this->passes($input),
            "Expected [{$input}] to be rejected."
        );
    }

    #[Test]
    #[DataProvider('normalisationCases')]
    public function it_normalises_every_accepted_format_to_the_local_form(
        string $input,
        string $expected,
    ): void {
        $this->assertSame($expected, SriLankanMobile::normalize($input));
    }

    #[Test]
    public function normalising_blank_input_yields_null(): void
    {
        $this->assertNull(SriLankanMobile::normalize(null));
        $this->assertNull(SriLankanMobile::normalize('   '));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validNumbers(): array
    {
        return [
            'local Dialog' => ['0771234567'],
            'local Mobitel' => ['0711234567'],
            'local Hutch' => ['0781234567'],
            'local Airtel' => ['0751234567'],
            'international with plus' => ['+94771234567'],
            'international without plus' => ['94771234567'],
            'leading zero omitted' => ['771234567'],
            'spaced' => ['077 123 4567'],
            'hyphenated' => ['077-123-4567'],
            'bracketed' => ['(077) 1234567'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNumbers(): array
    {
        return [
            'landline Colombo' => ['0112345678'],
            'landline Kandy' => ['0812234567'],
            'unissued prefix 073' => ['0731234567'],
            'unissued prefix 079' => ['0791234567'],
            'too short' => ['077123456'],
            'too long' => ['07712345678'],
            'US number' => ['+15550100'],
            'letters' => ['not-a-number'],
            'empty' => [''],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function normalisationCases(): array
    {
        return [
            'already local' => ['0771234567', '0771234567'],
            'plus 94' => ['+94771234567', '0771234567'],
            'bare 94' => ['94771234567', '0771234567'],
            'no leading zero' => ['771234567', '0771234567'],
            'with separators' => ['077 123-4567', '0771234567'],
        ];
    }

    /**
     * Exercise the rule directly rather than through the Validator facade, so
     * this stays a true unit test that needs no application container.
     */
    private function passes(string $value): bool
    {
        $failed = false;

        (new SriLankanMobile)->validate(
            'mobile',
            $value,
            function () use (&$failed): void {
                $failed = true;
            }
        );

        return ! $failed;
    }
}
