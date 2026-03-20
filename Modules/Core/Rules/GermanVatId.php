<?php

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validator for German VAT Identification Numbers (USt-IdNr.)
 * 
 * Format: DE + 9 digits (since 2009)
 * Valid examples: DE123456789, DE 123 456 789
 * 
 * @see https://www.bzst.de/DE/Unternehmen/Umsatzsteuer/USt-IdNrverzeichnis/USt-IdNrverzeichnis_node.html
 */
class GermanVatId implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->passes($attribute, $value)) {
            $fail('The :attribute is not a valid German VAT Identification Number (USt-IdNr.).');
        }
    }

    /**
     * Determine if the validation rule passes.
     */
    public function passes(string $attribute, mixed $value): bool
    {
        if (empty($value)) {
            return true; // Empty is handled by 'required' rule
        }

        // Remove spaces and convert to uppercase
        $value = strtoupper(trim($value));

        // Check minimum length
        if (strlen($value) < 10) {
            return false;
        }

        // Must start with country code
        if (!str_starts_with($value, 'DE')) {
            return false;
        }

        // Extract the number part (after 'DE')
        $number = substr($value, 2);

        // Remove any spaces from the number
        $number = str_replace(' ', '', $number);

        // Must be exactly 9 digits
        if (!preg_match('/^\d{9}$/', $number)) {
            return false;
        }

        // Mod 11 check digit validation
        return $this->validateCheckDigit($number);
    }

    /**
     * Validate the check digit using the Mod 11 algorithm.
     * 
     * The German VAT ID uses a Mod 11 check digit calculation.
     */
    protected function validateCheckDigit(string $number): bool
    {
        $product = 10;

        for ($i = 0; $i < 8; $i++) {
            $sum = ($product + (int) $number[$i]) % 10;
            if ($sum === 0) {
                $sum = 10;
            }
            $product = (2 * $sum) % 11;
        }

        $checkDigit = 11 - $product;
        if ($checkDigit === 10) {
            $checkDigit = 0;
        }

        return $checkDigit === (int) $number[8];
    }

    /**
     * Get the check digit for a given 8-digit prefix.
     */
    public static function calculateCheckDigit(string $eightDigits): int
    {
        if (strlen($eightDigits) !== 8 || !ctype_digit($eightDigits)) {
            throw new \InvalidArgumentException('Input must be exactly 8 digits.');
        }

        $product = 10;

        for ($i = 0; $i < 8; $i++) {
            $sum = ($product + (int) $eightDigits[$i]) % 10;
            if ($sum === 0) {
                $sum = 10;
            }
            $product = (2 * $sum) % 11;
        }

        $checkDigit = 11 - $product;
        if ($checkDigit === 10) {
            $checkDigit = 0;
        }

        return $checkDigit;
    }

    /**
     * Format a VAT ID for display (with spaces every 3 digits).
     */
    public static function format(string $vatId): string
    {
        $vatId = strtoupper(trim($vatId));
        $number = substr($vatId, 2);
        $number = str_replace(' ', '', $number);

        // Format as "DE 123 456 789"
        return 'DE ' . implode(' ', str_split($number, 3));
    }
}
