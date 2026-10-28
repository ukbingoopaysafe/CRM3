<?php

namespace Tests\Unit;

use App\Support\ArabicText;
use App\Support\Phone;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhoneTest extends TestCase
{
    public function test_local_egyptian_number_is_normalized_to_e164(): void
    {
        $this->assertSame('+201012345678', Phone::normalize('01012345678'));
        $this->assertSame('+201012345678', Phone::normalize('+20 101 234 5678'));
    }

    public function test_other_countries_are_supported(): void
    {
        $this->assertSame('+966501234567', Phone::normalize('0501234567', 'SA'));
    }

    public function test_invalid_number_fails_closed(): void
    {
        $this->expectException(ValidationException::class);

        Phone::normalize('abc');
    }

    public function test_international_prefix_conflict_is_detected(): void
    {
        $this->assertSame('SA', Phone::detectCountryConflict('+966501234567', 'EG'));
        $this->assertNull(Phone::detectCountryConflict('+201012345678', 'EG'));
        $this->assertNull(Phone::detectCountryConflict('01012345678', 'EG'));
    }

    public function test_arabic_name_normalization_ignores_letter_variants(): void
    {
        $this->assertSame(
            ArabicText::normalize('أحمد  على'),
            ArabicText::normalize('احمد على')
        );
        $this->assertGreaterThan(0.8, ArabicText::similarity('محمد احمد', 'محمد أحمد'));
    }
}
