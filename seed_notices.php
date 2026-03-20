<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$notices = [
    ['company_id' => 22, 'code' => 'DE-KLEIN-001', 'title' => 'Kleinunternehmer (§19 UStG)', 'body' => 'Gemäß §19 UStG wird keine Umsatzsteuer ausgewiesen.', 'type' => 'tax_notice', 'trigger' => 'tax_code:DE-KLEIN-0', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ['company_id' => 22, 'code' => 'DE-RC-001', 'title' => 'Reverse Charge (§13b UStG)', 'body' => 'Steuerschuldnerschaft des Leistungsempfängers (§13b Abs. 2 Nr. 1 UStG).', 'type' => 'tax_notice', 'trigger' => 'tax_code:EU-VAT-ZERO,country:EU', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ['company_id' => 22, 'code' => 'DE-BAU-001', 'title' => 'Bauleistungen (§13b UStG)', 'body' => 'Die Umsatzsteuer wird nach §13b UStG aufgrund von Bauleistungen vom Leistungsempfänger geschuldet.', 'type' => 'tax_notice', 'trigger' => 'category:baukosten', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ['company_id' => 22, 'code' => 'GEN-PAY-001', 'title' => 'Zahlungsbedingungen Standard', 'body' => 'Zahlbar innerhalb von 30 Tagen nach Rechnungsdatum ohne Abzug.', 'type' => 'payment_notice', 'trigger' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
];

foreach($notices as $n) {
    Modules\Core\Models\StandardNotice::updateOrCreate(
        ['company_id' => $n['company_id'], 'code' => $n['code']], 
        $n
    );
}

echo "Inserted " . count($notices) . " notices for Company 22\n";
