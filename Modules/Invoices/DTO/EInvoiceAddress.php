<?php

namespace Modules\Invoices\DTO;

/**
 * EInvoiceAddress - RB-IMP-10
 *
 * Typed DTO for a postal address in the E-Invoice domain model.
 */
class EInvoiceAddress
{
    public function __construct(
        public readonly ?string $street,
        public readonly ?string $city,
        public readonly ?string $postalCode,
        public readonly ?string $countryCode,
    ) {}

    public function toArray(): array
    {
        return [
            'street'       => $this->street,
            'city'         => $this->city,
            'postal_code'  => $this->postalCode,
            'country_code' => $this->countryCode,
        ];
    }
}
