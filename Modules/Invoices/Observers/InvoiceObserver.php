<?php

namespace Modules\Invoices\Observers;

use Modules\Core\Models\StandardNotice;
use Modules\Core\Observers\AbstractObserver;
use Modules\Core\Services\StandardNoticeService;
use Modules\Invoices\Enums\InvoiceStatus;
use Modules\Invoices\Models\Invoice;
use Modules\Invoices\Services\GermanInvoiceValidator;

class InvoiceObserver extends AbstractObserver
{
    protected StandardNoticeService $standardNoticeService;
    protected GermanInvoiceValidator $germanValidator;

    public function __construct()
    {
        $this->standardNoticeService = new StandardNoticeService();
        $this->germanValidator = new GermanInvoiceValidator();
    }

    /**
     * Handle the Invoice "saving" event.
     * Prevent duplicate invoice numbers within the same company.
     * Allows multiple nulls (for draft invoices).
     */
    public function saving(Invoice $invoice): void
    {
        if ($invoice->invoice_number !== null) {
            $duplicate = Invoice::query()->where('company_id', $invoice->company_id)
                ->where('invoice_number', $invoice->invoice_number)
                ->where('id', '!=', $invoice->id ?? 0)
                ->exists();

            if ($duplicate) {
                throw new \RuntimeException("Duplicate invoice number '{$invoice->invoice_number}' for company ID {$invoice->company_id}");
            }
        }

        // RB-IMP-06: Validate German requirements before finalization
        $this->validateGermanRequirements($invoice);
    }

    /**
     * Validate German invoice requirements.
     * Only enforced when moving from DRAFT to non-DRAFT status.
     */
    protected function validateGermanRequirements(Invoice $invoice): void
    {
        // Skip if no status change
        if (!$invoice->wasChanged('invoice_status')) {
            return;
        }

        $newStatus = $invoice->invoice_status;

        // Skip if changing to DRAFT (drafts are always allowed)
        if ($newStatus === InvoiceStatus::DRAFT->value) {
            return;
        }

        // Validate if moving from DRAFT to non-DRAFT (finalization)
        $oldStatus = $invoice->getOriginal('invoice_status');
        if ($oldStatus === InvoiceStatus::DRAFT->value) {
            $validation = $this->germanValidator->validateForFinalization($invoice);

            if (!$validation['valid']) {
                $missingFields = $this->germanValidator->getMissingFields($invoice);
                $fieldLabels = $missingFields->pluck('label')->implode(', ');

                throw new \RuntimeException(
                    trans('ip.validation_cannot_finalize') . ' Missing: ' . $fieldLabels
                );
            }
        }
    }

    /**
     * Handle the Invoice "created" event.
     * Automatically apply standard notices based on triggers.
     */
    public function created(Invoice $invoice): void
    {
        $this->applyStandardNotices($invoice);
    }

    /**
     * Handle the Invoice "updated" event.
     * Re-apply notices if tax rates changed.
     */
    public function updated(Invoice $invoice): void
    {
        // Check if tax rates changed
        if ($invoice->wasChanged(['tax_rate_id']) || $invoice->wasChanged(['tax_rates'])) {
            // Clear existing notices and re-apply
            $this->applyStandardNotices($invoice);
        }
    }

    /**
     * Apply standard notices to the invoice.
     * 
     * Notices are only applied if:
     * - Invoice has no existing footer
     * - Notice is active and has matching triggers
     */
    protected function applyStandardNotices(Invoice $invoice): void
    {
        // Only apply if no custom footer exists
        if (!empty($invoice->footer)) {
            return;
        }

        $noticeText = $this->standardNoticeService->generateNoticeText($invoice);

        if ($noticeText !== null) {
            $invoice->footer = $noticeText;
            $invoice->saveQuietly(); // Avoid triggering observer again
        }
    }

    /**
     * Get Reverse Charge status for display.
     */
    public static function getReverseChargeStatus(Invoice $invoice): array
    {
        $service = new StandardNoticeService();
        $applicable = $service->isReverseChargeApplicable(
            $invoice,
            $invoice->customer?->addresses?->first()?->country
        );

        return [
            'applicable' => $applicable,
            'reason' => $applicable
                ? 'Reverse Charge gemäß §13b UStG anwendbar'
                : 'Reverse Charge nicht anwendbar',
        ];
    }
}
