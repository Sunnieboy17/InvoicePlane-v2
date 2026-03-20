<?php

namespace Modules\Invoices\DTO;

/**
 * EInvoiceTaxCategory - RB-IMP-10
 *
 * Typed DTO for a tax category (e.g. 'S' = standard, 'Z' = zero, 'AE' = reverse charge).
 */
class EInvoiceTaxCategory
{
    public function __construct(
        public readonly string $code,
        public readonly float $percent,
    ) {}

    public function toArray(): array
    {
        return [
            'code'    => $this->code,
            'percent' => $this->percent,
        ];
    }
}
