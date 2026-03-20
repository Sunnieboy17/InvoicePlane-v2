<?php

namespace Modules\Invoices\DTO;

/**
 * EInvoiceTaxTotal - RB-IMP-10
 *
 * Typed DTO for the tax total block of an E-Invoice document.
 */
class EInvoiceTaxTotal
{
    /**
     * @param EInvoiceTaxSubtotal[] $taxSubtotals
     */
    public function __construct(
        public readonly float $taxAmount,
        public readonly array $taxSubtotals,
    ) {}

    public function toArray(): array
    {
        return [
            'tax_amount'    => $this->taxAmount,
            'tax_subtotals' => array_map(
                fn (EInvoiceTaxSubtotal $s) => $s->toArray(),
                $this->taxSubtotals
            ),
        ];
    }
}
