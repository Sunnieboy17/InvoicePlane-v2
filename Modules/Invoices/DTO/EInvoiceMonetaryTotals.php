<?php

namespace Modules\Invoices\DTO;

/**
 * EInvoiceMonetaryTotals - RB-IMP-10
 *
 * Typed DTO for the monetary summary totals of an E-Invoice document.
 */
class EInvoiceMonetaryTotals
{
    public function __construct(
        public readonly float $lineExtensionAmount,
        public readonly float $taxExclusiveAmount,
        public readonly float $taxInclusiveAmount,
        public readonly float $payableAmount,
    ) {}

    public function toArray(): array
    {
        return [
            'line_extension_amount' => $this->lineExtensionAmount,
            'tax_exclusive_amount'  => $this->taxExclusiveAmount,
            'tax_inclusive_amount'  => $this->taxInclusiveAmount,
            'payable_amount'        => $this->payableAmount,
        ];
    }
}
