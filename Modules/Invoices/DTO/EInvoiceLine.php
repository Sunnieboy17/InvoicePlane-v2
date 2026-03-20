<?php

namespace Modules\Invoices\DTO;

/**
 * EInvoiceLine - RB-IMP-10
 *
 * Typed DTO for a single invoice line item in the E-Invoice domain model.
 */
class EInvoiceLine
{
    public function __construct(
        public readonly int $id,
        public readonly float $quantity,
        public readonly string $unitCode,
        public readonly float $lineExtensionAmount,
        public readonly float $priceAmount,
        public readonly string $itemName,
        public readonly ?string $itemDescription,
        public readonly string $taxCategoryCode,
        public readonly float $taxPercent,
        public readonly float $taxAmount,
    ) {}

    public function toArray(): array
    {
        return [
            'id'                    => $this->id,
            'quantity'              => $this->quantity,
            'unit_code'             => $this->unitCode,
            'line_extension_amount' => $this->lineExtensionAmount,
            'price_amount'          => $this->priceAmount,
            'item'                  => [
                'name'        => $this->itemName,
                'description' => $this->itemDescription,
            ],
            'tax' => [
                'category_code' => $this->taxCategoryCode,
                'percent'       => $this->taxPercent,
                'amount'        => $this->taxAmount,
            ],
        ];
    }
}
