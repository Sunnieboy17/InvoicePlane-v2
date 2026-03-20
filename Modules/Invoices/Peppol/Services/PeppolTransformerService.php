<?php

namespace Modules\Invoices\Peppol\Services;

use Modules\Invoices\DTO\EInvoiceAddress;
use Modules\Invoices\DTO\EInvoiceDocument;
use Modules\Invoices\DTO\EInvoiceLine;
use Modules\Invoices\DTO\EInvoiceMonetaryTotals;
use Modules\Invoices\DTO\EInvoiceParty;
use Modules\Invoices\DTO\EInvoicePaymentTerms;
use Modules\Invoices\DTO\EInvoiceTaxCategory;
use Modules\Invoices\DTO\EInvoiceTaxSubtotal;
use Modules\Invoices\DTO\EInvoiceTaxTotal;
use Modules\Invoices\Models\Invoice;

/**
 * PeppolTransformerService - RB-IMP-10
 *
 * Normalizes an InvoicePlane Invoice model into a typed EInvoiceDocument DTO.
 * This is the central mapping layer between the domain model and all
 * structured export formats (XRechnung / CII, ZUGFeRD, Peppol BIS).
 *
 * Format handlers should call transform() and use the returned EInvoiceDocument
 * (or its toArray() representation for backwards compatibility).
 */
class PeppolTransformerService
{
    /**
     * Transform an invoice into a typed EInvoiceDocument DTO.
     *
     * @param Invoice $invoice
     * @param string  $format  Target format identifier (for metadata)
     *
     * @return EInvoiceDocument
     */
    public function transform(Invoice $invoice, string $format): EInvoiceDocument
    {
        return new EInvoiceDocument(
            invoiceTypeCode: $this->getInvoiceTypeCode($invoice),
            invoiceNumber:   $invoice->invoice_number ?? $invoice->number ?? '',
            issueDate:       $invoice->invoiced_at?->format('Y-m-d') ?? now()->format('Y-m-d'),
            dueDate:         $invoice->invoice_due_at?->format('Y-m-d'),
            currencyCode:    $invoice->currency_code ?? config('invoices.peppol.currency_code', 'EUR'),
            supplier:        $this->transformSupplier($invoice),
            buyer:           $this->transformBuyer($invoice),
            lines:           $this->transformInvoiceLines($invoice),
            taxTotals:       $this->transformTaxTotals($invoice),
            monetaryTotals:  $this->transformMonetaryTotals($invoice),
            paymentTerms:    $this->transformPaymentTerms($invoice),
            format:          $format,
            invoiceId:       $invoice->id,
        );
    }

    /**
     * Determine the Peppol invoice type code.
     * '380' = standard commercial invoice, '381' = credit note.
     */
    protected function getInvoiceTypeCode(Invoice $invoice): string
    {
        if ($invoice->invoice_sign === '-1' || $invoice->credit_note_type !== null) {
            return '381';
        }

        return '380';
    }

    /**
     * Build the supplier (company/sender) party from the invoice's company.
     */
    protected function transformSupplier(Invoice $invoice): EInvoiceParty
    {
        $company = $invoice->company;
        $address = null;

        if ($company) {
            $address = new EInvoiceAddress(
                street:      $company->address ?? null,
                city:        $company->city ?? null,
                postalCode:  $company->zip ?? null,
                countryCode: $company->country ?? null,
            );
        }

        return new EInvoiceParty(
            name:           $company?->name ?? config('invoices.peppol.supplier.name', ''),
            vatNumber:      $company?->vat_number ?? config('invoices.peppol.supplier.vat'),
            endpointId:     null,
            endpointScheme: null,
            address:        $address,
        );
    }

    /**
     * Build the buyer (customer/recipient) party from the invoice's customer.
     */
    protected function transformBuyer(Invoice $invoice): EInvoiceParty
    {
        $customer = $invoice->customer;
        $address  = null;

        if ($customer) {
            $raw = $customer->primaryAddress ?? $customer->billingAddress ?? null;
            if ($raw) {
                $address = new EInvoiceAddress(
                    street:      $raw->address_1 ?? null,
                    city:        $raw->city ?? null,
                    postalCode:  $raw->zip ?? null,
                    countryCode: $raw->country ?? null,
                );
            }
        }

        return new EInvoiceParty(
            name:           $customer?->company_name ?? '',
            vatNumber:      $customer?->vat_number,
            endpointId:     $customer?->peppol_id,
            endpointScheme: $customer?->peppol_scheme,
            address:        $address,
        );
    }

    /**
     * Transform invoice line items into typed EInvoiceLine DTOs.
     *
     * @return EInvoiceLine[]
     */
    protected function transformInvoiceLines(Invoice $invoice): array
    {
        return $invoice->invoiceItems->map(function ($item, int $index): EInvoiceLine {
            return new EInvoiceLine(
                id:                  $index + 1,
                quantity:            (float) ($item->quantity ?? 1),
                unitCode:            config('invoices.peppol.unit_code', 'C62'),
                lineExtensionAmount: (float) ($item->subtotal ?? 0),
                priceAmount:         (float) ($item->price ?? 0),
                itemName:            $item->item_name ?? $item->name ?? '',
                itemDescription:     $item->description ?? null,
                taxCategoryCode:     'S',
                taxPercent:          (float) ($item->tax_rate ?? 0),
                taxAmount:           (float) ($item->tax_1 ?? $item->tax ?? 0),
            );
        })->values()->all();
    }

    /**
     * Build tax total DTOs, grouped by tax category.
     *
     * @return EInvoiceTaxTotal[]
     */
    protected function transformTaxTotals(Invoice $invoice): array
    {
        $taxTotal    = (float) ($invoice->invoice_tax_total ?? $invoice->tax ?? 0);
        $subtotal    = (float) ($invoice->invoice_item_subtotal ?? $invoice->subtotal ?? 0);

        // Calculate effective tax rate from items when possible
        $taxPercent = 0.0;
        if ($subtotal > 0 && $taxTotal > 0) {
            $taxPercent = round(($taxTotal / $subtotal) * 100, 2);
        }

        $subtotalDto = new EInvoiceTaxSubtotal(
            taxableAmount: $subtotal,
            taxAmount:     $taxTotal,
            taxCategory:   new EInvoiceTaxCategory(code: 'S', percent: $taxPercent),
        );

        return [
            new EInvoiceTaxTotal(
                taxAmount:    $taxTotal,
                taxSubtotals: [$subtotalDto],
            ),
        ];
    }

    /**
     * Build the monetary totals DTO.
     */
    protected function transformMonetaryTotals(Invoice $invoice): EInvoiceMonetaryTotals
    {
        $subtotal = (float) ($invoice->invoice_item_subtotal ?? $invoice->subtotal ?? 0);
        $total    = (float) ($invoice->invoice_total ?? $invoice->total ?? 0);
        $balance  = (float) ($invoice->balance ?? $total);

        return new EInvoiceMonetaryTotals(
            lineExtensionAmount: $subtotal,
            taxExclusiveAmount:  $subtotal,
            taxInclusiveAmount:  $total,
            payableAmount:       $balance,
        );
    }

    /**
     * Build payment terms DTO when a due date is present.
     */
    protected function transformPaymentTerms(Invoice $invoice): ?EInvoicePaymentTerms
    {
        if (! $invoice->invoice_due_at) {
            return null;
        }

        $dueDate = $invoice->invoice_due_at->format('Y-m-d');

        return new EInvoicePaymentTerms(
            note:    "Payment due by {$dueDate}",
            dueDate: $dueDate,
        );
    }
}
