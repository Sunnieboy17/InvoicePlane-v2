<?php

namespace Modules\Invoices\Peppol\FormatHandlers;

use DOMDocument;
use DOMElement;
use Modules\Invoices\Models\Invoice;

/**
 * CiiHandler - Cross Industry Invoice (CII) format handler.
 *
 * Implements UN/CEFACT Cross Industry Invoice standard.
 * Common in Germany, France, and Austria.
 *
 * RB-IMP-11: XRechnung Export MVP
 * 
 * Based on UN/CEFACT XML Schema:
 * urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100
 */
class CiiHandler extends BaseFormatHandler
{
    /**
     * @inheritDoc
     */
    public function transform(Invoice $invoice, array $options = []): array
    {
        $customer = $invoice->customer;
        $company  = $invoice->company;

        return [
            'ExchangedDocumentContext'    => $this->buildDocumentContext(),
            'ExchangedDocument'           => $this->buildExchangedDocument($invoice),
            'SupplyChainTradeTransaction' => [
                'ApplicableHeaderTradeAgreement'  => $this->buildHeaderTradeAgreement($invoice, $customer),
                'ApplicableHeaderTradeDelivery'   => $this->buildHeaderTradeDelivery($invoice),
                'ApplicableHeaderTradeSettlement' => $this->buildHeaderTradeSettlement($invoice, $customer, $company),
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    public function validate(Invoice $invoice): array
    {
        $errors   = [];
        $customer = $invoice->customer;

        // Required fields validation
        if (empty($invoice->invoice_number)) {
            $errors[] = 'Invoice number is required for CII format';
        }
        if ( ! $invoice->invoiced_at) {
            $errors[] = 'Invoice date is required for CII format';
        }
        if ( ! $invoice->invoice_due_at) {
            $errors[] = 'Invoice due date is required for CII format';
        }
        if (empty($customer?->company_name)) {
            $errors[] = 'Customer company name is required for CII format';
        }
        if (empty($customer?->addresses?->first()?->country)) {
            $errors[] = 'Customer country code is required for CII format';
        }
        if ($invoice->invoiceItems->isEmpty()) {
            $errors[] = 'At least one invoice item is required for CII format';
        }
        // Validate amounts
        if ($invoice->invoice_total <= 0) {
            $errors[] = 'Invoice total must be greater than zero for CII format';
        }

        return $errors;
    }

    /**
     * RB-IMP-11: Generate XRechnung XML document.
     * 
     * @inheritDoc
     */
    public function generateXml(Invoice $invoice, array $options = []): string
    {
        $data = $this->transform($invoice, $options);
        
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        
        // Root element
        $root = $dom->createElementNS(
            'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
            'rsm:CrossIndustryInvoice'
        );
        $root->setAttribute('xmlns:rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $root->setAttribute('xmlns:ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $root->setAttribute('xmlns:udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');
        $dom->appendChild($root);
        
        // ExchangedDocumentContext
        $context = $dom->createElement('rsm:ExchangedDocumentContext');
        $guideline = $dom->createElement('ram:GuidelineSpecifiedDocumentContextParameter');
        $guidelineId = $dom->createElement('ram:ID', 'urn:cen.eu:en16931:2017#compliant#urn:xoev-de:kosit:standard:xrechnung_2.0');
        $guideline->appendChild($guidelineId);
        $context->appendChild($guideline);
        $root->appendChild($context);
        
        // ExchangedDocument
        $doc = $dom->createElement('rsm:ExchangedDocument');
        $docId = $dom->createElement('ram:ID', htmlspecialchars($invoice->invoice_number ?? ''));
        $docType = $dom->createElement('ram:TypeCode', '380');
        $docDate = $dom->createElement('ram:IssueDateTime');
        $docDateStr = $dom->createElement('udt:DateTimeString');
        $docDateStr->setAttribute('format', '102');
        $docDateStr->appendChild($dom->createTextNode($invoice->invoiced_at?->format('Ymd') ?? ''));
        $docDate->appendChild($docDateStr);
        $doc->appendChild($docId);
        $doc->appendChild($docType);
        $doc->appendChild($docDate);
        $root->appendChild($doc);
        
        // SupplyChainTradeTransaction
        $transaction = $dom->createElement('rsm:SupplyChainTradeTransaction');
        
        // HeaderTradeAgreement
        $agreement = $dom->createElement('ram:ApplicableHeaderTradeAgreement');
        $this->buildSellerPartyXml($dom, $agreement, $invoice);
        $this->buildBuyerPartyXml($dom, $agreement, $invoice);
        $transaction->appendChild($agreement);
        
        // HeaderTradeDelivery
        $delivery = $dom->createElement('ram:ApplicableHeaderTradeDelivery');
        $deliveryEvent = $dom->createElement('ram:ActualDeliverySupplyChainEvent');
        $deliveryDate = $dom->createElement('ram:OccurrenceDateTime');
        $deliveryDateStr = $dom->createElement('udt:DateTimeString');
        $deliveryDateStr->setAttribute('format', '102');
        $deliveryDateStr->appendChild($dom->createTextNode($invoice->invoiced_at?->format('Ymd') ?? ''));
        $deliveryDate->appendChild($deliveryDateStr);
        $deliveryEvent->appendChild($deliveryDate);
        $delivery->appendChild($deliveryEvent);
        $transaction->appendChild($delivery);
        
        // HeaderTradeSettlement
        $settlement = $dom->createElement('ram:ApplicableHeaderTradeSettlement');
        $this->buildHeaderTradeSettlementXml($dom, $settlement, $invoice);
        $transaction->appendChild($settlement);
        
        $root->appendChild($transaction);
        
        return $dom->saveXML();
    }

    /**
     * Build seller party XML elements.
     */
    protected function buildSellerPartyXml(DOMDocument $dom, DOMElement $parent, Invoice $invoice): void
    {
        $seller = $dom->createElement('ram:SellerTradeParty');
        
        // Company name
        $name = $dom->createElement('ram:Name', htmlspecialchars($invoice->company?->name ?? config('invoices.peppol.supplier.company_name', '')));
        $seller->appendChild($name);
        
        // Postal address
        $address = $invoice->company?->addresses?->first();
        $postal = $dom->createElement('ram:PostalTradeAddress');
        $postal->appendChild($dom->createElement('ram:PostcodeCode', htmlspecialchars($address?->zip ?? '')));
        $postal->appendChild($dom->createElement('ram:LineOne', htmlspecialchars($address?->street ?? '')));
        $postal->appendChild($dom->createElement('ram:CityName', htmlspecialchars($address?->city ?? '')));
        $postal->appendChild($dom->createElement('ram:CountryID', htmlspecialchars($address?->country ?? config('invoices.peppol.supplier.country_code', 'DE'))));
        $seller->appendChild($postal);
        
        // VAT number
        $vatNumber = $invoice->company?->vat_number ?? config('invoices.peppol.supplier.vat_number');
        if ($vatNumber) {
            $taxReg = $dom->createElement('ram:SpecifiedTaxRegistration');
            $taxId = $dom->createElement('ram:ID');
            $taxId->setAttribute('schemeID', 'VA');
            $taxId->appendChild($dom->createTextNode($vatNumber));
            $taxReg->appendChild($taxId);
            $seller->appendChild($taxReg);
        }
        
        $parent->appendChild($seller);
    }

    /**
     * Build buyer party XML elements.
     */
    protected function buildBuyerPartyXml(DOMDocument $dom, DOMElement $parent, Invoice $invoice): void
    {
        $customer = $invoice->customer;
        $buyer = $dom->createElement('ram:BuyerTradeParty');
        
        // Customer name
        $name = $dom->createElement('ram:Name', htmlspecialchars($customer?->company_name ?? ''));
        $buyer->appendChild($name);
        
        // Postal address
        $address = $customer?->addresses?->first();
        $postal = $dom->createElement('ram:PostalTradeAddress');
        $postal->appendChild($dom->createElement('ram:PostcodeCode', htmlspecialchars($address?->zip ?? '')));
        $postal->appendChild($dom->createElement('ram:LineOne', htmlspecialchars($address?->street ?? '')));
        $postal->appendChild($dom->createElement('ram:CityName', htmlspecialchars($address?->city ?? '')));
        $postal->appendChild($dom->createElement('ram:CountryID', htmlspecialchars($address?->country ?? '')));
        $buyer->appendChild($postal);
        
        $parent->appendChild($buyer);
    }

    /**
     * Build header trade settlement XML elements.
     */
    protected function buildHeaderTradeSettlementXml(DOMDocument $dom, DOMElement $parent, Invoice $invoice): void
    {
        $currencyCode = $this->getCurrencyCode($invoice);
        
        // Currency
        $currency = $dom->createElement('ram:InvoiceCurrencyCode', $currencyCode);
        $parent->appendChild($currency);
        
        // Payment means
        $paymentMeans = $dom->createElement('ram:SpecifiedTradeSettlementPaymentMeans');
        $paymentMeans->appendChild($dom->createElement('ram:TypeCode', '30')); // SEPA credit transfer
        $parent->appendChild($paymentMeans);
        
        // Tax totals
        $this->buildTaxTotalsXml($dom, $parent, $invoice, $currencyCode);
        
        // Payment terms
        $paymentTerms = $dom->createElement('ram:SpecifiedTradePaymentTerms');
        $dueDate = $dom->createElement('ram:DueDateTime');
        $dueDateStr = $dom->createElement('udt:DateTimeString');
        $dueDateStr->setAttribute('format', '102');
        $dueDateStr->appendChild($dom->createTextNode($invoice->invoice_due_at?->format('Ymd') ?? ''));
        $dueDate->appendChild($dueDateStr);
        $paymentTerms->appendChild($dueDate);
        $parent->appendChild($paymentTerms);
        
        // Monetary summation
        $summation = $dom->createElement('ram:SpecifiedTradeSettlementHeaderMonetarySummation');
        $summation->appendChild($dom->createElement('ram:LineTotalAmount', number_format($invoice->invoice_item_subtotal ?? 0, 2, '.', '')));
        $summation->appendChild($dom->createElement('ram:TaxBasisTotalAmount', number_format($invoice->invoice_item_subtotal ?? 0, 2, '.', '')));
        
        $taxTotal = $dom->createElement('ram:TaxTotalAmount', number_format($invoice->invoice_tax_total ?? 0, 2, '.', ''));
        $taxTotal->setAttribute('currencyID', $currencyCode);
        $summation->appendChild($taxTotal);
        
        $summation->appendChild($dom->createElement('ram:GrandTotalAmount', number_format($invoice->invoice_total ?? 0, 2, '.', '')));
        $summation->appendChild($dom->createElement('ram:DuePayableAmount', number_format($invoice->invoice_total ?? 0, 2, '.', '')));
        $parent->appendChild($summation);
        
        // Line items
        $this->buildLineItemsXml($dom, $parent, $invoice, $currencyCode);
    }

    /**
     * Build tax totals XML elements.
     */
    protected function buildTaxTotalsXml(DOMDocument $dom, DOMElement $parent, Invoice $invoice, string $currencyCode): void
    {
        $taxGroups = [];
        
        foreach ($invoice->invoiceItems as $item) {
            $rate = $item->tax_rate ?? 0;
            $rateKey = (string) $rate;
            
            if (!isset($taxGroups[$rateKey])) {
                $taxGroups[$rateKey] = ['basis' => 0, 'amount' => 0];
            }
            
            $taxGroups[$rateKey]['basis'] += (float) ($item->subtotal ?? 0);
            $taxGroups[$rateKey]['amount'] += (float) ($item->tax_total ?? 0);
        }
        
        foreach ($taxGroups as $rateKey => $group) {
            $rate = (float) $rateKey;
            
            $tax = $dom->createElement('ram:ApplicableTradeTax');
            $tax->appendChild($dom->createElement('ram:CalculatedAmount', number_format($group['amount'], 2, '.', '')));
            $tax->appendChild($dom->createElement('ram:TypeCode', 'VAT'));
            $tax->appendChild($dom->createElement('ram:BasisAmount', number_format($group['basis'], 2, '.', '')));
            $tax->appendChild($dom->createElement('ram:CategoryCode', $rate > 0 ? 'S' : 'Z'));
            $tax->appendChild($dom->createElement('ram:RateApplicablePercent', number_format($rate, 2, '.', '')));
            
            $parent->appendChild($tax);
        }
    }

    /**
     * Build line items XML elements.
     */
    protected function buildLineItemsXml(DOMDocument $dom, DOMElement $parent, Invoice $invoice, string $currencyCode): void
    {
        foreach ($invoice->invoiceItems as $index => $item) {
            $lineItem = $dom->createElement('ram:IncludedSupplyChainTradeLineItem');
            
            // Line ID
            $lineDoc = $dom->createElement('ram:AssociatedDocumentLineDocument');
            $lineDoc->appendChild($dom->createElement('ram:LineID', (string) ($index + 1)));
            $lineItem->appendChild($lineDoc);
            
            // Product
            $product = $dom->createElement('ram:SpecifiedTradeProduct');
            $product->appendChild($dom->createElement('ram:Name', htmlspecialchars($item->item_name ?? '')));
            if ($item->description) {
                $product->appendChild($dom->createElement('ram:Description', htmlspecialchars($item->description)));
            }
            $lineItem->appendChild($product);
            
            // Price
            $agreement = $dom->createElement('ram:SpecifiedLineTradeAgreement');
            $price = $dom->createElement('ram:NetPriceProductTradePrice');
            $price->appendChild($dom->createElement('ram:ChargeAmount', number_format($item->price ?? 0, 2, '.', '')));
            $agreement->appendChild($price);
            $lineItem->appendChild($agreement);
            
            // Delivery
            $delivery = $dom->createElement('ram:SpecifiedLineTradeDelivery');
            $quantity = $dom->createElement('ram:BilledQuantity');
            $quantity->setAttribute('unitCode', $item->productUnit?->name ?? 'C62');
            $quantity->appendChild($dom->createTextNode(number_format($item->quantity ?? 1, 2, '.', '')));
            $delivery->appendChild($quantity);
            $lineItem->appendChild($delivery);
            
            // Settlement
            $settlement = $dom->createElement('ram:SpecifiedLineTradeSettlement');
            $rate = $item->tax_rate ?? 0;
            $tradeTax = $dom->createElement('ram:ApplicableTradeTax');
            $tradeTax->appendChild($dom->createElement('ram:TypeCode', 'VAT'));
            $tradeTax->appendChild($dom->createElement('ram:CategoryCode', $rate > 0 ? 'S' : 'Z'));
            $tradeTax->appendChild($dom->createElement('ram:RateApplicablePercent', number_format($rate, 2, '.', '')));
            $settlement->appendChild($tradeTax);
            
            $lineSum = $dom->createElement('ram:SpecifiedTradeSettlementLineMonetarySummation');
            $lineSum->appendChild($dom->createElement('ram:LineTotalAmount', number_format($item->subtotal ?? 0, 2, '.', '')));
            $settlement->appendChild($lineSum);
            $lineItem->appendChild($settlement);
            
            $parent->appendChild($lineItem);
        }
    }

    /**
     * @inheritDoc
     */
    protected function validateFormatSpecific(Invoice $invoice): array
    {
        // Implement format-specific validation
        return [];
    }

    /**
     * Build the document context section.
     *
     * @return array
     */
    protected function buildDocumentContext(): array
    {
        return [
            'GuidelineSpecifiedDocumentContextParameter' => [
                'ID' => 'urn:cen.eu:en16931:2017#compliant#urn:xoev-de:kosit:standard:xrechnung_2.0',
            ],
        ];
    }

    /**
     * Build the exchanged document section.
     *
     * @param Invoice $invoice
     *
     * @return array
     */
    protected function buildExchangedDocument(Invoice $invoice): array
    {
        return [
            'ID'            => $invoice->invoice_number,
            'TypeCode'      => '380', // Commercial invoice
            'IssueDateTime' => [
                'DateTimeString' => [
                    '@format' => '102',
                    '@value'  => $invoice->invoice_date->format('Ymd'),
                ],
            ],
            'IncludedNote' => $invoice->notes ? [
                [
                    'Content' => $invoice->notes,
                ],
            ] : null,
        ];
    }

    /**
     * Build the header trade agreement section.
     *
     * @param Invoice $invoice
     * @param mixed   $customer
     *
     * @return array
     */
    protected function buildHeaderTradeAgreement(Invoice $invoice, $customer): array
    {
        return [
            'BuyerReference'   => $customer->reference ?? '',
            'SellerTradeParty' => $this->buildSellerParty($invoice->company),
            'BuyerTradeParty'  => $this->buildBuyerParty($customer),
        ];
    }

    /**
     * Build seller party details.
     *
     * @param mixed $company
     *
     * @return array
     */
    protected function buildSellerParty($company): array
    {
        return [
            'Name'                => $company->name ?? config('invoices.peppol.supplier.company_name'),
            'DefinedTradeContact' => [
                'PersonName'                      => config('invoices.peppol.supplier.contact_name'),
                'TelephoneUniversalCommunication' => [
                    'CompleteNumber' => config('invoices.peppol.supplier.contact_phone'),
                ],
                'EmailURIUniversalCommunication' => [
                    'URIID' => config('invoices.peppol.supplier.contact_email'),
                ],
            ],
            'PostalTradeAddress' => [
                'PostcodeCode' => $company->postal_code ?? config('invoices.peppol.supplier.postal_zone'),
                'LineOne'      => $company->address ?? config('invoices.peppol.supplier.street_name'),
                'CityName'     => $company->city ?? config('invoices.peppol.supplier.city_name'),
                'CountryID'    => $company->country_code ?? config('invoices.peppol.supplier.country_code'),
            ],
            'SpecifiedTaxRegistration' => [
                [
                    'ID' => [
                        '@schemeID' => 'VA',
                        '@value'    => $company->vat_number ?? config('invoices.peppol.supplier.vat_number'),
                    ],
                ],
            ],
        ];
    }

    /**
     * Build buyer party details.
     *
     * @param mixed $customer
     *
     * @return array
     */
    protected function buildBuyerParty($customer): array
    {
        return [
            'Name'               => $customer->name,
            'PostalTradeAddress' => [
                'PostcodeCode' => $customer->postal_code ?? '',
                'LineOne'      => $customer->address ?? '',
                'CityName'     => $customer->city ?? '',
                'CountryID'    => $customer->country_code ?? '',
            ],
        ];
    }

    /**
     * Build header trade delivery section.
     *
     * @param Invoice $invoice
     *
     * @return array
     */
    protected function buildHeaderTradeDelivery(Invoice $invoice): array
    {
        return [
            'ActualDeliverySupplyChainEvent' => [
                'OccurrenceDateTime' => [
                    'DateTimeString' => [
                        '@format' => '102',
                        '@value'  => ($invoice->delivery_date ?? $invoice->invoice_date)->format('Ymd'),
                    ],
                ],
            ],
        ];
    }

    /**
     * Build header trade settlement section.
     *
     * @param Invoice $invoice
     * @param mixed   $customer
     * @param mixed   $company
     *
     * @return array
     */
    protected function buildHeaderTradeSettlement(Invoice $invoice, $customer, $company): array
    {
        $currencyCode = $this->getCurrencyCode($invoice, $customer, $company);

        return [
            'InvoiceCurrencyCode'                  => $currencyCode,
            'SpecifiedTradeSettlementPaymentMeans' => [
                [
                    'TypeCode'    => $this->getPaymentMeansCode($invoice),
                    'Information' => $invoice->payment_terms ?? '',
                ],
            ],
            'ApplicableTradeTax'         => $this->buildTaxTotals($invoice, $currencyCode),
            'SpecifiedTradePaymentTerms' => [
                'DueDateTime' => [
                    'DateTimeString' => [
                        '@format' => '102',
                        '@value'  => $invoice->invoice_due_at->format('Ymd'),
                    ],
                ],
            ],
            'SpecifiedTradeSettlementHeaderMonetarySummation' => [
                'LineTotalAmount'     => number_format($invoice->subtotal, 2, '.', ''),
                'TaxBasisTotalAmount' => number_format($invoice->subtotal, 2, '.', ''),
                'TaxTotalAmount'      => [
                    '@currencyID' => $currencyCode,
                    '@value'      => number_format($invoice->total_tax, 2, '.', ''),
                ],
                'GrandTotalAmount' => number_format($invoice->total, 2, '.', ''),
                'DuePayableAmount' => number_format($invoice->balance_due, 2, '.', ''),
            ],
            'IncludedSupplyChainTradeLineItem' => $this->buildLineItems($invoice->items, $currencyCode),
        ];
    }

    /**
     * Build tax totals for the invoice.
     *
     * @param Invoice $invoice
     * @param string  $currencyCode
     *
     * @return array
     */
    protected function buildTaxTotals(Invoice $invoice, string $currencyCode): array
    {
        $taxTotals = [];

        // Group taxes by rate
        $taxGroups = [];
        foreach ($invoice->items as $item) {
            $rate    = $item->tax_rate ?? 0;
            $rateKey = (string) $rate;
            if ( ! isset($taxGroups[$rateKey])) {
                $taxGroups[$rateKey] = [
                    'basis'  => 0,
                    'amount' => 0,
                ];
            }
            $taxGroups[$rateKey]['basis'] += $item->subtotal;
            $taxGroups[$rateKey]['amount'] += $item->tax_total;
        }

        foreach ($taxGroups as $rateKey => $group) {
            $rate        = (float) $rateKey;
            $taxTotals[] = [
                'CalculatedAmount'      => number_format($group['amount'], 2, '.', ''),
                'TypeCode'              => 'VAT',
                'BasisAmount'           => number_format($group['basis'], 2, '.', ''),
                'CategoryCode'          => $this->getTaxCategoryCode($rate),
                'RateApplicablePercent' => number_format($rate, 2, '.', ''),
            ];
        }

        return $taxTotals;
    }

    /**
     * Build line items for the invoice.
     *
     * @param mixed  $items
     * @param string $currencyCode
     *
     * @return array
     */
    protected function buildLineItems($items, string $currencyCode): array
    {
        $lineItems = [];

        foreach ($items as $index => $item) {
            $lineItems[] = [
                'AssociatedDocumentLineDocument' => [
                    'LineID' => (string) ($index + 1),
                ],
                'SpecifiedTradeProduct' => [
                    'Name'        => $item->name,
                    'Description' => $item->description ?? '',
                ],
                'SpecifiedLineTradeAgreement' => [
                    'NetPriceProductTradePrice' => [
                        'ChargeAmount' => number_format($item->price, 2, '.', ''),
                    ],
                ],
                'SpecifiedLineTradeDelivery' => [
                    'BilledQuantity' => [
                        '@unitCode' => $item->unit_code ?? config('invoices.peppol.document.default_unit_code'),
                        '@value'    => number_format($item->quantity, 2, '.', ''),
                    ],
                ],
                'SpecifiedLineTradeSettlement' => [
                    'ApplicableTradeTax' => [
                        'TypeCode'              => 'VAT',
                        'CategoryCode'          => $this->getTaxCategoryCode($item->tax_rate ?? 0),
                        'RateApplicablePercent' => number_format($item->tax_rate ?? 0, 2, '.', ''),
                    ],
                    'SpecifiedTradeSettlementLineMonetarySummation' => [
                        'LineTotalAmount' => number_format($item->subtotal, 2, '.', ''),
                    ],
                ],
            ];
        }

        return $lineItems;
    }

    /**
     * Get payment means code based on invoice payment method.
     *
     * @param Invoice $invoice
     *
     * @return string
     */
    protected function getPaymentMeansCode(Invoice $invoice): string
    {
        // 30 = Credit transfer, 48 = Bank card, 49 = Direct debit
        return '30'; // Default to credit transfer
    }

    /**
     * Get tax category code based on tax rate.
     *
     * @param float $taxRate
     *
     * @return string
     */
    protected function getTaxCategoryCode(float $taxRate): string
    {
        if ($taxRate === 0.0) {
            return 'Z'; // Zero rated
        }

        return 'S'; // Standard rate
    }
}
