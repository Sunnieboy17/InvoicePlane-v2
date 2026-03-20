<?php

namespace Modules\Invoices\DTO;

/**
 * EInvoiceTaxSubtotal - RB-IMP-10
 *
 * Typed DTO for a tax subtotal entry grouped by tax category.
 */
class EInvoiceTaxSubtotal
{
    public function __construct(
        public readonly float $taxableAmount,
        public readonly float $taxAmount,
        public readonly EInvoiceTaxCategory $taxCategory,
    ) {}

    public function toArray(): array
    {
        return [
            'taxable_amount' => $this->taxableAmount,
            'tax_amount'     => $this->taxAmount,
            'tax_category'   => $this->taxCategory->toArray(),
        ];
    }
}
