<?php

namespace Modules\Core\Services;

use Illuminate\Support\Collection;
use Modules\Core\Models\StandardNotice;
use Modules\Invoices\Models\Invoice;

/**
 * Service für automatische Hinweistext-Verarbeitung bei Rechnungen.
 * 
 * Ermittelt basierend auf Rechnungs- und Kundendaten automatisch
 * passende Standard-Hinweistexte (z.B. für deutsche Steuerregelungen).
 */
class StandardNoticeService
{
    /**
     * Findet alle passenden Hinweistexte für eine Rechnung.
     * 
     * @param Invoice $invoice
     * @return Collection<StandardNotice>
     */
    public function findNoticesForInvoice(Invoice $invoice): Collection
    {
        $notices = collect();
        
        // Hole Steuersatz-Code der Rechnung
        $taxCode = $this->getInvoiceTaxCode($invoice);
        
        // Hole Ländercode des Kunden
        $customerCountry = $this->getCustomerCountry($invoice);
        
        // Prüfe Reverse Charge Bedingungen
        if ($this->isReverseChargeApplicable($invoice, $customerCountry)) {
            $rcNotices = $this->findReverseChargeNotices($invoice->company_id, $taxCode, $customerCountry);
            $notices = $notices->merge($rcNotices);
        }
        
        // Prüfe Kleinunternehmer
        if ($this->isKleinunternehmer($invoice)) {
            $kleinNotices = $this->findKleinunternehmerNotices($invoice->company_id);
            $notices = $notices->merge($kleinNotices);
        }
        
        // Prüfe EU-Innergemeinschaftliche Lieferung
        if ($this->isIntraCommunitySupply($invoice, $customerCountry)) {
            $euNotices = $this->findIntraCommunityNotices($invoice->company_id);
            $notices = $notices->merge($euNotices);
        }
        
        return $notices->unique('id');
    }
    
    /**
     * Generiert einen Text mit allen zutreffenden Hinweistexten.
     * 
     * @param Invoice $invoice
     * @return string|null
     */
    public function generateNoticeText(Invoice $invoice): ?string
    {
        $notices = $this->findNoticesForInvoice($invoice);
        
        if ($notices->isEmpty()) {
            return null;
        }
        
        return $notices
            ->pluck('body')
            ->filter()
            ->unique()
            ->map(fn ($body) => '- ' . $body)
            ->join("\n");
    }
    
    /**
     * Prüft ob Reverse Charge anwendbar ist.
     * 
     * Reverse Charge gilt für:
     * - B2B Lieferungen (Kunde hat USt-IdNr.)
     * - EU-Länder
     * - Bestimmte Waren/Dienstleistungen (§13b UStG)
     * 
     * @param Invoice $invoice
     * @param string|null $customerCountry
     * @return bool
     */
    protected function isReverseChargeApplicable(Invoice $invoice, ?string $customerCountry): bool
    {
        // Nur bei B2B (Kunde hat USt-IdNr.)
        $customer = $invoice->customer;
        if (!$customer || !$customer->vat_number) {
            return false;
        }
        
        // Nur bei EU-Ländern (außer Deutschland)
        if (!$customerCountry || $customerCountry === 'DE') {
            return false;
        }
        
        // EU-Länder (vereinfacht - ohne UK, das nicht mehr in der EU ist)
        $euCountries = ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 
                       'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 
                       'PT', 'RO', 'SK', 'SI', 'ES', 'SE'];
        
        if (!in_array($customerCountry, $euCountries)) {
            return false;
        }
        
        // Reverse Charge gilt für:
        // - DE-VAT-STD-19 (19% Regelbesteuerung)
        // - DE-VAT-RED-7 (7% Ermäßigte Steuer)
        // NICHT für:
        // - EU-VAT-ZERO (0% innergemeinschaftliche Lieferung)
        // - DE-KLEIN-0 (Kleinunternehmer)
        $taxCode = $this->getInvoiceTaxCode($invoice);
        $rcTaxCodes = ['DE-VAT-STD-19', 'DE-VAT-RED-7'];
        
        if (!$taxCode || !in_array($taxCode, $rcTaxCodes)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Prüft ob Kleinunternehmerregelung gilt.
     * 
     * @param Invoice $invoice
     * @return bool
     */
    protected function isKleinunternehmer(Invoice $invoice): bool
    {
        $taxCode = $this->getInvoiceTaxCode($invoice);
        return $taxCode === 'DE-KLEIN-0';
    }
    
    /**
     * Prüft ob es sich um eine innergemeinschaftliche Lieferung handelt.
     * 
     * @param Invoice $invoice
     * @param string|null $customerCountry
     * @return bool
     */
    protected function isIntraCommunitySupply(Invoice $invoice, ?string $customerCountry): bool
    {
        if (!$customerCountry || $customerCountry === 'DE') {
            return false;
        }
        
        $taxCode = $this->getInvoiceTaxCode($invoice);
        
        // Innergemeinschaftliche Lieferung = EU-Land + 0%-Steuersatz
        $zeroRateCodes = ['EU-VAT-ZERO', 'DE-KLEIN-0'];
        
        return in_array($taxCode, $zeroRateCodes);
    }
    
    /**
     * Findet Reverse Charge Hinweistexte.
     * 
     * Prüft:
     * - Trigger enthält "rc:" oder "reverse_charge"
     * - Tax-Code stimmt überein (DE-VAT-STD-19, DE-VAT-RED-7)
     */
    protected function findReverseChargeNotices(int $companyId, ?string $taxCode, string $country): Collection
    {
        // Gültige RC Tax-Codes
        $rcTaxCodes = ['DE-VAT-STD-19', 'DE-VAT-RED-7'];
        
        // Nur wenn Tax-Code RC-qualifiziert ist
        if (!$taxCode || !in_array($taxCode, $rcTaxCodes)) {
            return collect();
        }
        
        return StandardNotice::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('type', StandardNotice::TYPE_TAX_NOTICE)
            ->get()
            ->filter(function (StandardNotice $notice) use ($taxCode) {
                if (empty($notice->trigger)) {
                    return false;
                }
                
                $trigger = strtolower($notice->trigger);
                
                // Prüfe ob Trigger "rc:" oder "reverse" enthält
                if (!str_contains($trigger, 'rc:') && !str_contains($trigger, 'reverse')) {
                    return false;
                }
                
                // Prüfe Tax-Code Übereinstimmung
                $rcCodes = ['de-vat-std-19', 'de-vat-red-7'];
                $noticeCode = strtolower($taxCode);
                if (!in_array($noticeCode, $rcCodes)) {
                    return false;
                }
                
                // Extrahiere tax_code aus Trigger und prüfe
                preg_match('/tax_code:([^,]+)/', $notice->trigger, $matches);
                if (isset($matches[1])) {
                    $triggerCodes = explode('|', strtolower($matches[1]));
                    if (!in_array($noticeCode, $triggerCodes)) {
                        return false;
                    }
                }
                
                return true;
            });
    }
    
    /**
     * Findet Kleinunternehmer Hinweistexte.
     */
    protected function findKleinunternehmerNotices(int $companyId): Collection
    {
        return StandardNotice::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('type', StandardNotice::TYPE_TAX_NOTICE)
            ->where('trigger', 'LIKE', '%KLEIN%')
            ->orWhere('trigger', 'LIKE', '%klein%')
            ->get()
            ->filter(fn ($notice) => str_contains($notice->trigger ?? '', 'tax_code:DE-KLEIN-0'));
    }
    
    /**
     * Findet Hinweistexte für innergemeinschaftliche Lieferung.
     */
    protected function findIntraCommunityNotices(int $companyId): Collection
    {
        return StandardNotice::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('type', StandardNotice::TYPE_TAX_NOTICE)
            ->where('trigger', 'LIKE', '%EU%')
            ->get()
            ->filter(fn ($notice) => str_contains($notice->trigger ?? '', 'tax_code:EU-VAT-ZERO'));
    }
    
    /**
     * Holt den Steuersatz-Code der Rechnung.
     */
    protected function getInvoiceTaxCode(Invoice $invoice): ?string
    {
        // Hole den ersten (Haupt-)Steuersatz der Rechnung
        $taxRate = $invoice->taxRates()->first();
        
        return $taxRate?->code;
    }
    
    /**
     * Holt den Ländercode des Kunden.
     */
    protected function getCustomerCountry(Invoice $invoice): ?string
    {
        $customer = $invoice->customer;
        
        if (!$customer) {
            return null;
        }
        
        // Versuche Ländercode aus der Adresse zu holen
        $address = $customer->addresses()->where('is_primary', true)->first();
        
        return $address?->country;
    }
}
