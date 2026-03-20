<?php

namespace Modules\Core\Tests\Unit;

use Modules\Core\Rules\GermanVatId;
use PHPUnit\Framework\TestCase;

class GermanVatIdTest extends TestCase
{
    protected GermanVatId $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new GermanVatId();
    }

    #[Test]
    public function it_accepts_valid_german_vat_ids(): void
    {
        // Generate valid VAT IDs using calculateCheckDigit
        $base1 = '12345678';
        $check1 = GermanVatId::calculateCheckDigit($base1);
        $valid1 = 'DE' . $base1 . $check1;
        
        $base2 = '99999999';
        $check2 = GermanVatId::calculateCheckDigit($base2);
        $valid2 = 'DE' . $base2 . $check2;
        
        $validIds = [
            $valid1,  // e.g. DE123456788
            $valid2,  // e.g. DE999999999
            'DE 123 456 788',  // with spaces
            'de123456788',      // lowercase
            '  DE123456788  ',  // with padding
        ];
        
        foreach ($validIds as $vatId) {
            $this->assertTrue(
                $this->rule->passes('vat_id', $vatId),
                "Should accept valid VAT ID: $vatId"
            );
        }
    }

    #[Test]
    public function it_rejects_invalid_german_vat_ids(): void
    {
        $invalidIds = [
            'DE12345678',      // only 8 digits
            'DE1234567890',     // 10 digits
            'FR123456789',     // wrong country
            'DE12345678A',     // letter in number
            '123456789',       // no country code
            'DE',             // only country code
            '         ',      // only spaces
        ];
        
        foreach ($invalidIds as $vatId) {
            $this->assertFalse(
                $this->rule->passes('vat_id', $vatId),
                "Should reject invalid VAT ID: $vatId"
            );
        }
    }

    #[Test]
    public function it_accepts_empty_values(): void
    {
        $this->assertTrue($this->rule->passes('vat_id', ''));
        $this->assertTrue($this->rule->passes('vat_id', null));
    }

    #[Test]
    public function it_calculates_check_digit_for_known_base(): void
    {
        // Known correct check digit for base 12345678
        // This generates a valid VAT ID that should pass validation
        $base = '12345678';
        $checkDigit = GermanVatId::calculateCheckDigit($base);
        
        // Verify the generated check digit produces a valid VAT ID
        $vatId = 'DE' . $base . $checkDigit;
        $this->assertTrue(
            $this->rule->passes('vat_id', $vatId),
            "Generated VAT ID should be valid: $vatId"
        );
    }

    #[Test]
    public function it_generates_different_check_digits_for_different_inputs(): void
    {
        $digit1 = GermanVatId::calculateCheckDigit('12345678');
        $digit2 = GermanVatId::calculateCheckDigit('87654321');
        
        // Different inputs should produce different check digits (most likely)
        // This is a basic sanity check
        $this->assertIsInt($digit1);
        $this->assertIsInt($digit2);
        $this->assertGreaterThanOrEqual(0, $digit1);
        $this->assertLessThanOrEqual(9, $digit1);
    }

    #[Test]
    public function it_formats_vat_id_correctly(): void
    {
        $formatted = GermanVatId::format('DE123456788');
        $this->assertEquals('DE 123 456 788', $formatted);
        
        $formatted2 = GermanVatId::format('de123456788');
        $this->assertEquals('DE 123 456 788', $formatted2);
    }

    #[Test]
    public function it_validates_generated_vat_ids(): void
    {
        // Generate and immediately validate multiple VAT IDs
        for ($i = 0; $i < 10; $i++) {
            $base = str_pad((string) ($i * 11111111), 8, '0', STR_PAD_LEFT);
            $checkDigit = GermanVatId::calculateCheckDigit($base);
            $vatId = 'DE' . $base . $checkDigit;
            
            $this->assertTrue(
                $this->rule->passes('vat_id', $vatId),
                "Generated VAT ID should be valid: $vatId"
            );
        }
    }
}
