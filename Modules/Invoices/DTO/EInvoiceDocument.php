<?php

namespace Modules\Invoices\DTO;

/**
 * EInvoiceDocument - RB-IMP-10
 *
 * Central typed DTO representing a normalized E-Invoice document.
 *
 * This is the single internal domain model used as the normalization
 * layer between the InvoicePlane Invoice model and all structured
 * export formats (XRechnung, ZUGFeRD, Peppol BIS).
 *
 * Format handlers receive this DTO (or its array representation)
 * instead of mapping the Invoice model directly.
 */
class EInvoiceDocument
{
    /**
     * @param EInvoiceLine[]     $lines
     * @param EInvoiceTaxTotal[] $taxTotals
     */
    public function __construct(
        public readonly string $invoiceTypeCode,
        public readonly string $invoiceNumber,
        public readonly string $issueDate,
        public readonly ?string $dueDate,
        public readonly string $currencyCode,
        public readonly EInvoiceParty $supplier,
        public readonly EInvoiceParty $buyer,
        public readonly array $lines,
        public readonly array $taxTotals,
        public readonly EInvoiceMonetaryTotals $monetaryTotals,
        public readonly ?EInvoicePaymentTerms $paymentTerms,
        public readonly string $format,
        public readonly int $invoiceId,
    ) {}

    /**
     * Return a plain array compatible with the previous array-based contract.
     * Format handlers that consume the transformer output can call toArray()
     * to remain backwards compatible.
     */
    public function toArray(): array
    {
        return [
            'invoice_type_code' => $this->invoiceTypeCode,
            'invoice_number'    => $this->invoiceNumber,
            'issue_date'        => $this->issueDate,
            'due_date'          => $this->dueDate,
            'currency_code'     => $this->currencyCode,
            'supplier'          => $this->supplier->toArray(),
            'customer'          => $this->buyer->toArray(),
            'invoice_lines'     => array_map(
                fn (EInvoiceLine $l) => $l->toArray(),
                $this->lines
            ),
            'tax_totals'        => array_map(
                fn (EInvoiceTaxTotal $t) => $t->toArray(),
                $this->taxTotals
            ),
            'monetary_totals'   => $this->monetaryTotals->toArray(),
            'payment_terms'     => $this->paymentTerms?->toArray(),
            'format'            => $this->format,
            'invoice_id'        => $this->invoiceId,
        ];
    }
}
