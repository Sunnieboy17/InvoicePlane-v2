<?php

namespace Modules\Invoices\DTO;

/**
 * EInvoicePaymentTerms - RB-IMP-10
 *
 * Typed DTO for the payment terms block of an E-Invoice document.
 */
class EInvoicePaymentTerms
{
    public function __construct(
        public readonly ?string $note,
        public readonly ?string $dueDate = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'note'     => $this->note,
            'due_date' => $this->dueDate,
        ]);
    }
}
