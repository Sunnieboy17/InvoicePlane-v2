<?php

namespace Modules\Invoices\DTO;

/**
 * EInvoiceParty - RB-IMP-10
 *
 * Typed DTO for a trade party (seller or buyer) in the E-Invoice domain model.
 */
class EInvoiceParty
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $vatNumber,
        public readonly ?string $endpointId,
        public readonly ?string $endpointScheme,
        public readonly ?EInvoiceAddress $address,
    ) {}

    public function toArray(): array
    {
        return [
            'name'            => $this->name,
            'vat_number'      => $this->vatNumber,
            'endpoint_id'     => $this->endpointId,
            'endpoint_scheme' => $this->endpointScheme,
            'address'         => $this->address?->toArray(),
        ];
    }
}
