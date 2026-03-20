<?php
require 'vendor/autoload.php';
use Modules\Core\Rules\GermanVatId;

$rule = new GermanVatId();

// Generate valid VAT ID
$base = '12345678';
$checkDigit = GermanVatId::calculateCheckDigit($base);
$validVat = 'DE' . $base . $checkDigit;

echo "Test: GermanVatId Validation\n";
echo "============================\n\n";

// Test 1: Valid VAT ID
echo "1. Valid VAT ID: $validVat\n";
echo "   Result: " . ($rule->passes('vat_id', $validVat) ? 'PASS' : 'FAIL') . "\n\n";

// Test 2: Invalid - too short
echo "2. Too short: DE12345678\n";
echo "   Result: " . ($rule->passes('vat_id', 'DE12345678') ? 'FAIL' : 'PASS') . "\n\n";

// Test 3: Invalid - wrong country
echo "3. Wrong country: FR123456789\n";
echo "   Result: " . ($rule->passes('vat_id', 'FR123456789') ? 'FAIL' : 'PASS') . "\n\n";

// Test 4: Empty value
echo "4. Empty value\n";
echo "   Result: " . ($rule->passes('vat_id', '') ? 'PASS' : 'FAIL') . "\n\n";

// Test 5: Check digit calculation
echo "5. Check digit for 12345678: $checkDigit\n";
$generatedVat = 'DE' . $base . $checkDigit;
echo "   Generated VAT: $generatedVat\n";
echo "   Validates: " . ($rule->passes('vat_id', $generatedVat) ? 'PASS' : 'FAIL') . "\n\n";

// Test 6: Format function
echo "6. Format function\n";
echo "   Input: DE123456788\n";
echo "   Output: " . GermanVatId::format('DE123456788') . "\n\n";

echo "All tests completed.\n";
