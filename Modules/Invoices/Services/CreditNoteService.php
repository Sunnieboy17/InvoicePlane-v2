<?php

namespace Modules\Invoices\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Services\BaseService;
use Modules\Invoices\Enums\CreditNoteType;
use Modules\Invoices\Enums\InvoiceStatus;
use Modules\Invoices\Models\Invoice;

/**
 * CreditNoteService - RB-IMP-15
 *
 * Service for creating and managing credit notes / cancellations.
 *
 * Provides:
 * - Create credit note from original invoice
 * - Create cancellation from original invoice
 * - Create correction (replacement) invoice
 * - Link to original invoice
 * - Basic validation
 */
class CreditNoteService extends BaseService
{
    public function model(): string
    {
        return Invoice::class;
    }

    /**
     * Create a credit note from an existing invoice.
     *
     * @param Invoice $originalInvoice The original invoice to credit
     * @param CreditNoteType $type The type of credit note
     * @param array $adjustments Optional adjustments (e.g., partial amounts)
     *
     * @return Invoice The created credit note
     */
    public function createFromInvoice(
        Invoice $originalInvoice,
        CreditNoteType $type = CreditNoteType::CREDIT,
        array $adjustments = []
    ): Invoice {
        DB::beginTransaction();

        try {
            // Determine sign based on type
            $sign = match ($type) {
                CreditNoteType::CANCELLATION => '-1',
                default => '-1',
            };

            // Create credit note data
            $creditNote = Invoice::query()->create([
                'customer_id'             => $originalInvoice->customer_id,
                'numbering_id'            => $originalInvoice->numbering_id,
                'creditinvoice_parent_id' => $originalInvoice->getKey(),
                'credit_note_type'        => $type->value,
                'user_id'                 => auth()->id(),
                'invoice_number'          => $this->generateCreditNoteNumber($originalInvoice),
                'invoice_status'          => InvoiceStatus::DRAFT,
                'invoice_sign'            => $sign,
                'invoiced_at'            => Carbon::now(),
                'invoice_due_at'         => Carbon::now(),
                'invoice_discount_amount' => 0,
                'invoice_discount_percent' => 0,
                'item_tax_total'          => $this->calculateCreditItemTax($originalInvoice, $adjustments),
                'invoice_item_subtotal'   => $this->calculateCreditSubtotal($originalInvoice, $adjustments),
                'invoice_tax_total'       => $this->calculateCreditTax($originalInvoice, $adjustments),
                'invoice_total'           => $this->calculateCreditTotal($originalInvoice, $adjustments),
                'invoice_password'        => null,
                'url_key'                => Str::random(32),
                'is_read_only'           => false,
                'template'               => $originalInvoice->template,
                'summary'                => $this->generateCreditNoteSummary($originalInvoice, $type, $adjustments),
                'terms'                  => $originalInvoice->terms,
                'footer'                 => $originalInvoice->footer,
                // Business references
                'buyer_reference'        => $originalInvoice->buyer_reference,
                'order_reference'        => $originalInvoice->order_reference,
                'project_reference'       => $originalInvoice->project_reference,
            ]);

            // Copy invoice items with reversed amounts
            $this->copyInvoiceItems($originalInvoice, $creditNote, $adjustments);

            DB::commit();

            return $creditNote;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate credit note number based on original invoice.
     */
    protected function generateCreditNoteNumber(Invoice $originalInvoice): string
    {
        $prefix = config('invoices.credit_note_prefix', 'CN');
        $originalNumber = $originalInvoice->invoice_number ?? 'UNKNOWN';

        return "{$prefix}-{$originalNumber}";
    }

    /**
     * Generate credit note summary text.
     */
    protected function generateCreditNoteSummary(
        Invoice $originalInvoice,
        CreditNoteType $type,
        array $adjustments
    ): string {
        $originalNumber = $originalInvoice->invoice_number ?? 'UNKNOWN';
        $originalDate = $originalInvoice->invoiced_at?->format('d.m.Y') ?? 'N/A';

        return match ($type) {
            CreditNoteType::CREDIT => trans('ip.credit_note_summary_credit', [
                'original_number' => $originalNumber,
                'original_date' => $originalDate,
            ]),
            CreditNoteType::CANCELLATION => trans('ip.credit_note_summary_cancellation', [
                'original_number' => $originalNumber,
                'original_date' => $originalDate,
            ]),
            CreditNoteType::CORRECTION => trans('ip.credit_note_summary_correction', [
                'original_number' => $originalNumber,
                'original_date' => $originalDate,
            ]),
        };
    }

    /**
     * Copy invoice items to credit note with adjusted amounts.
     */
    protected function copyInvoiceItems(
        Invoice $originalInvoice,
        Invoice $creditNote,
        array $adjustments
    ): void {
        $items = $originalInvoice->invoiceItems;

        // If partial credit, filter items
        $itemIds = $adjustments['item_ids'] ?? $items->pluck('id')->toArray();

        foreach ($items as $item) {
            if (!in_array($item->id, $itemIds)) {
                continue;
            }

            $creditNote->invoiceItems()->create([
                'product_id'      => $item->product_id,
                'product_unit_id' => $item->product_unit_id,
                'item_name'       => $item->item_name,
                'quantity'        => $item->quantity,
                'price'           => $item->price,
                'discount'        => $item->discount,
                'subtotal'        => $item->subtotal * -1, // Reverse
                'tax_1'           => $item->tax_1,
                'tax_2'           => $item->tax_2,
                'tax_total'       => $item->tax_total * -1, // Reverse
                'total'           => $item->total * -1, // Reverse
                'description'     => $item->description,
                'tax_rate_id'     => $item->tax_rate_id,
                'tax_rate_2_id'   => $item->tax_rate_2_id,
                'display_order'   => $item->display_order,
            ]);
        }
    }

    /**
     * Calculate credit item tax total.
     */
    protected function calculateCreditItemTax(Invoice $originalInvoice, array $adjustments): float
    {
        if (empty($adjustments['item_ids'])) {
            return ($originalInvoice->item_tax_total ?? 0) * -1;
        }

        $tax = $originalInvoice->invoiceItems
            ->whereIn('id', $adjustments['item_ids'])
            ->sum('tax_total');

        return $tax * -1;
    }

    /**
     * Calculate credit subtotal.
     */
    protected function calculateCreditSubtotal(Invoice $originalInvoice, array $adjustments): float
    {
        if (empty($adjustments['item_ids'])) {
            return ($originalInvoice->invoice_item_subtotal ?? 0) * -1;
        }

        $subtotal = $originalInvoice->invoiceItems
            ->whereIn('id', $adjustments['item_ids'])
            ->sum('subtotal');

        return $subtotal * -1;
    }

    /**
     * Calculate credit tax total.
     */
    protected function calculateCreditTax(Invoice $originalInvoice, array $adjustments): float
    {
        if (empty($adjustments['item_ids'])) {
            return ($originalInvoice->invoice_tax_total ?? 0) * -1;
        }

        $tax = $originalInvoice->invoiceItems
            ->whereIn('id', $adjustments['item_ids'])
            ->sum(fn ($item) => ($item->tax_1 ?? 0) + ($item->tax_2 ?? 0));

        return $tax * -1;
    }

    /**
     * Calculate credit total.
     */
    protected function calculateCreditTotal(Invoice $originalInvoice, array $adjustments): float
    {
        $subtotal = abs($this->calculateCreditSubtotal($originalInvoice, $adjustments));
        $tax = abs($this->calculateCreditTax($originalInvoice, $adjustments));

        return ($subtotal + $tax) * -1;
    }

    /**
     * Get credit notes for an invoice.
     */
    public function getCreditNotes(Invoice $invoice): \Illuminate\Database\Eloquent\Collection
    {
        return Invoice::query()
            ->where('creditinvoice_parent_id', $invoice->getKey())
            ->where('invoice_sign', '-1')
            ->orderBy('invoiced_at', 'desc')
            ->get();
    }

    /**
     * Get the original invoice for a credit note.
     */
    public function getOriginalInvoice(Invoice $creditNote): ?Invoice
    {
        if (!$creditNote->creditinvoice_parent_id) {
            return null;
        }

        return Invoice::find($creditNote->creditinvoice_parent_id);
    }

    /**
     * Check if an invoice has credit notes.
     */
    public function hasCreditNotes(Invoice $invoice): bool
    {
        return Invoice::query()
            ->where('creditinvoice_parent_id', $invoice->getKey())
            ->where('invoice_sign', '-1')
            ->exists();
    }

    /**
     * Check if a credit note is fully applied.
     */
    public function isFullyApplied(Invoice $creditNote): bool
    {
        return abs($creditNote->paid) >= abs($creditNote->invoice_total);
    }

    /**
     * Get the remaining balance of a credit note.
     */
    public function getRemainingBalance(Invoice $creditNote): float
    {
        if ($creditNote->invoice_sign !== '-1') {
            return 0;
        }

        return abs($creditNote->invoice_total) - abs($creditNote->paid);
    }
}
