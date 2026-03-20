<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$germanRates = [
    ['company_id' => 22, 'code' => 'DE-VAT-STD-19', 'name' => 'DE Regelsteuersatz 19%', 'rate' => 19.00, 'tax_rate_type' => 'exclusive', 'is_compound' => false, 'calculate_vat' => true, 'is_active' => true],
    ['company_id' => 22, 'code' => 'DE-VAT-RED-7', 'name' => 'DE Ermäßigter Steuersatz 7%', 'rate' => 7.00, 'tax_rate_type' => 'exclusive', 'is_compound' => false, 'calculate_vat' => true, 'is_active' => true],
    ['company_id' => 22, 'code' => 'DE-KLEIN-0', 'name' => 'DE Kleinunternehmer (§19 UStG)', 'rate' => 0.00, 'tax_rate_type' => 'exclusive', 'is_compound' => false, 'calculate_vat' => false, 'is_active' => true],
];

foreach($germanRates as $r) {
    Modules\Core\Models\TaxRate::updateOrCreate(
        ['company_id' => $r['company_id'], 'code' => $r['code']], 
        $r
    );
}

echo "Inserted " . count($germanRates) . " German tax rates for Company 22\n";
