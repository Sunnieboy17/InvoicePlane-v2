<?php

namespace Modules\Invoices\Services;

use Carbon\Carbon;
use Modules\Invoices\Models\Invoice;

/**
 * GermanB2BTransitionService - RB-IMP-16
 *
 * Service for German B2B E-Invoice transition rules.
 *
 * Implements product logic / status logic / helper rules for
 * German B2B transition phases.
 *
 * NOT in scope:
 * - Legal advice
 * - Complete automatic legal decision making
 * - International rules outside Germany B2B
 *
 * Based on current German regulations (as of 2025):
 * - B2B E-Invoicing mandatory from Jan 1, 2025 for in-scope companies
 * - Transitional period until Dec 31, 2024 (with some extensions possible)
 * - Focus on domestic B2B transactions in Germany
 */
class GermanB2BTransitionService
{
    /**
     * Transition status values.
     */
    public const STATUS_NOT_APPLICABLE      = 'not_applicable';
    public const STATUS_REVIEW_REQUIRED     = 'review_required';
    public const STATUS_EINVOICE_RECOMMENDED = 'einvoice_recommended';
    public const STATUS_EINVOICE_EXPECTED    = 'einvoice_expected';

    /**
     * Company size thresholds for mandatory E-Invoicing.
     */
    public const MANDATORY_SIZE_THRESHOLD = 800000; // €800,000 annual revenue

    /**
     * Mandatory E-Invoicing start date for large companies.
     */
    public const MANDATORY_DATE_LARGE = '2025-01-01';

    /**
     * Mandatory E-Invoicing start date for all companies.
     */
    public const MANDATORY_DATE_ALL = '2027-01-01';

    /**
     * Get the transition status for an invoice.
     *
     * @param Invoice $invoice
     *
     * @return array{
     *     status: string,
     *     label: string,
     *     color: string,
     *     description: string,
     *     requires_action: bool,
     *     context: array<string, mixed>
     * }
     */
    public function getTransitionStatus(Invoice $invoice): array
    {
        $customer = $invoice->customer;
        $company = $invoice->company;

        // Check if B2B context
        $isB2B = $this->isB2BContext($invoice);
        if (!$isB2B) {
            return $this->buildStatus(
                self::STATUS_NOT_APPLICABLE,
                trans('ip.transition_not_applicable'),
                'gray',
                trans('ip.transition_not_applicable_desc'),
                false,
                ['context' => 'B2C or outside scope']
            );
        }

        // Check if domestic (Germany)
        $isDomestic = $this->isDomesticGermany($invoice);
        if (!$isDomestic) {
            return $this->buildStatus(
                self::STATUS_REVIEW_REQUIRED,
                trans('ip.transition_review_required'),
                'warning',
                trans('ip.transition_review_required_desc_cross_border'),
                true,
                ['context' => 'EU Cross-Border']
            );
        }

        // Check transaction date
        $transactionDate = $invoice->invoiced_at ?? Carbon::now();
        $isPast = $transactionDate->isPast();

        // For past transactions
        if ($isPast) {
            return $this->buildStatus(
                self::STATUS_NOT_APPLICABLE,
                trans('ip.transition_not_applicable'),
                'gray',
                trans('ip.transition_past_transaction_desc'),
                false,
                ['context' => 'Past transaction']
            );
        }

        // Check mandatory dates
        $today = Carbon::now();

        // Check if our company is in mandatory phase
        $isMandatoryPhase = $this->isInMandatoryPhase($company);

        // Check if customer might expect E-Invoice
        $customerExpectsEinvoice = $this->customerExpectsEinvoice($customer);

        // Determine status
        if ($isMandatoryPhase && $customerExpectsEinvoice) {
            return $this->buildStatus(
                self::STATUS_EINVOICE_EXPECTED,
                trans('ip.transition_einvoice_expected'),
                'danger',
                trans('ip.transition_einvoice_expected_desc'),
                true,
                [
                    'context' => 'Mandatory phase + B2B',
                    'mandatory_since' => $isMandatoryPhase ? self::MANDATORY_DATE_LARGE : self::MANDATORY_DATE_ALL,
                ]
            );
        }

        if ($customerExpectsEinvoice) {
            return $this->buildStatus(
                self::STATUS_EINVOICE_RECOMMENDED,
                trans('ip.transition_einvoice_recommended'),
                'warning',
                trans('ip.transition_einvoice_recommended_desc'),
                true,
                ['context' => 'Customer expects E-Invoice']
            );
        }

        return $this->buildStatus(
            self::STATUS_REVIEW_REQUIRED,
            trans('ip.transition_review_required'),
            'info',
            trans('ip.transition_review_required_desc_domestic'),
            false,
            ['context' => 'Domestic B2B']
        );
    }

    /**
     * Check if this is a B2B context.
     */
    public function isB2BContext(Invoice $invoice): bool
    {
        $customer = $invoice->customer;

        // B2B if customer has a company
        if ($customer && !empty($customer->company_name)) {
            return true;
        }

        // B2B if customer has VAT ID
        if ($customer && !empty($customer->vat_number)) {
            return true;
        }

        return false;
    }

    /**
     * Check if this is a domestic Germany transaction.
     */
    public function isDomesticGermany(Invoice $invoice): bool
    {
        $customer = $invoice->customer;

        // Both parties in Germany = domestic
        if ($customer && strtoupper($customer->country_code ?? '') === 'DE') {
            return true;
        }

        return false;
    }

    /**
     * Check if customer expects E-Invoice.
     */
    public function customerExpectsEinvoice($customer): bool
    {
        if (!$customer) {
            return false;
        }

        // Customer has Peppol ID
        if (!empty($customer->peppol_id)) {
            return true;
        }

        // Customer has E-Invoice flag
        if (!empty($customer->enable_e_invoicing)) {
            return true;
        }

        // Customer is in mandatory phase (large company)
        // We would check customer's annual revenue here if available
        // For now, we assume large companies expect E-Invoice

        return false;
    }

    /**
     * Check if company is in mandatory E-Invoice phase.
     */
    public function isInMandatoryPhase($company): bool
    {
        if (!$company) {
            return false;
        }

        // Check if company has exceeded revenue threshold
        // This would typically come from company settings or historical data
        $annualRevenue = $company->annual_revenue ?? 0;

        if ($annualRevenue >= self::MANDATORY_SIZE_THRESHOLD) {
            return true;
        }

        // Check explicit setting
        if (!empty($company->e_invoice_mandatory)) {
            return true;
        }

        return false;
    }

    /**
     * Check if E-Invoice is required for a transaction.
     */
    public function isEinvoiceRequired(Invoice $invoice): bool
    {
        $status = $this->getTransitionStatus($invoice);

        return $status['status'] === self::STATUS_EINVOICE_EXPECTED;
    }

    /**
     * Check if E-Invoice is recommended for a transaction.
*/
    public function isEinvoiceRecommended(Invoice $invoice): bool
    {
        $status = $this->getTransitionStatus($invoice);

        return in_array($status['status'], [
            self::STATUS_EINVOICE_EXPECTED,
            self::STATUS_EINVOICE_RECOMMENDED,
        ]);
    }

    /**
     * Get the recommendation message for an invoice.
     */
    public function getRecommendation(Invoice $invoice): ?string
    {
        $status = $this->getTransitionStatus($invoice);

        if (!$status['requires_action']) {
            return null;
        }

        return $status['description'];
    }

    /**
     * Build status array.
     */
    protected function buildStatus(
        string $status,
        string $label,
        string $color,
        string $description,
        bool $requiresAction,
        array $context
    ): array {
        return [
            'status' => $status,
            'label' => $label,
            'color' => $color,
            'description' => $description,
            'requires_action' => $requiresAction,
            'context' => $context,
        ];
    }

    /**
     * Get transition phase information for display.
     */
    public function getPhaseInfo(): array
    {
        return [
            'current_date' => Carbon::now()->format('Y-m-d'),
            'mandatory_date_large' => self::MANDATORY_DATE_LARGE,
            'mandatory_date_all' => self::MANDATORY_DATE_ALL,
            'size_threshold' => self::MANDATORY_SIZE_THRESHOLD,
            'is_mandatory_now' => Carbon::now()->gte(Carbon::parse(self::MANDATORY_DATE_LARGE)),
            'is_all_companies' => Carbon::now()->gte(Carbon::parse(self::MANDATORY_DATE_ALL)),
            'info_text' => trans('ip.transition_phase_info_text'),
        ];
    }
}
