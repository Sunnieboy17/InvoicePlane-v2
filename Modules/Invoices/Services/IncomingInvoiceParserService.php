<?php

namespace Modules\Invoices\Services;

use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Clients\Models\Relation;
use Modules\Invoices\Enums\InvoiceStatus;
use Modules\Invoices\Models\Invoice;
use Modules\Invoices\Models\InvoiceItem;
use Modules\Invoices\Peppol\FormatHandlers\CiiHandler;
use Modules\Invoices\Peppol\FormatHandlers\ZugferdHandler;

/**
 * IncomingInvoiceParserService - RB-IMP-14
 *
 * MVP for parsing incoming XML invoice files (XRechnung, ZUGFeRD).
 *
 * Scope:
 * - Upload entry point for XML invoice files
 * - Technical detection and parsing of structured XML files
 * - Parse logic into internal representation
 * - Visible feedback on parse success/parse error/missing fields
 * - Minimal persistence (only if meaningful location exists)
 *
 * NOT in scope:
 * - Complete accounts payable workflow
 * - Automatic booking
 * - Peppol incoming
 * - Full archiving logic
 */
class IncomingInvoiceParserService
{
    /**
     * Supported XML formats.
     */
    public const FORMAT_XRECHNUNG = 'xrechnung';
    public const FORMAT_ZUGFERD = 'zugferd';
    public const FORMAT_UNKNOWN = 'unknown';

    /**
     * Parse result status.
     */
    public const STATUS_SUCCESS = 'success';
    public const STATUS_WARNING = 'warning';
    public const STATUS_ERROR = 'error';

    /**
     * Parse an uploaded XML file.
     *
     * @param string $xmlContent The raw XML content
     * @param string $filename Original filename (for format detection hint)
     *
     * @return array{
     *     status: string,
     *     format: string,
     *     data: array|null,
     *     errors: array<string>,
     *     warnings: array<string>,
     *     raw_preview: array<string, mixed>
     * }
     */
    public function parse(string $xmlContent, string $filename = 'invoice.xml'): array
    {
        $result = [
            'status' => self::STATUS_SUCCESS,
            'format' => self::FORMAT_UNKNOWN,
            'data' => null,
            'errors' => [],
            'warnings' => [],
            'raw_preview' => [],
        ];

        // Detect format
        $format = $this->detectFormat($xmlContent, $filename);
        $result['format'] = $format;

        try {
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;

            libxml_use_internal_errors(true);
            $loaded = $dom->loadXML($xmlContent);
            $xmlErrors = libxml_get_errors();
            libxml_clear_errors();

            if (!$loaded || !empty($xmlErrors)) {
                $errorMsg = 'Invalid XML: ';
                foreach ($xmlErrors as $error) {
                    $errorMsg .= trim($error->message) . '; ';
                }
                $result['status'] = self::STATUS_ERROR;
                $result['errors'][] = trim($errorMsg);
                return $result;
            }

            $xpath = new DOMXPath($dom);

            // Register namespaces
            $this->registerNamespaces($xpath, $dom);

            // Parse based on detected format
            $data = match ($format) {
                self::FORMAT_XRECHNUNG => $this->parseXRechnung($xpath, $dom),
                self::FORMAT_ZUGFERD => $this->parseZugferd($xpath, $dom),
                default => $this->parseGeneric($xpath, $dom),
            };

            $result['data'] = $data['data'];
            $result['raw_preview'] = $data['preview'];
            $result['warnings'] = array_merge($result['warnings'], $data['warnings']);

            // Validate extracted data
            $this->validateExtractedData($result);

        } catch (Exception $e) {
            Log::error('IncomingInvoiceParser: Parse error', [
                'format' => $format,
                'error' => $e->getMessage(),
            ]);
            $result['status'] = self::STATUS_ERROR;
            $result['errors'][] = 'Parse error: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Detect the XML format from content and filename.
     */
    public function detectFormat(string $xmlContent, string $filename): string
    {
        // Check filename hints
        $filenameLower = strtolower($filename);
        if (str_contains($filenameLower, 'xrechnung') || str_contains($filenameLower, 'cii')) {
            return self::FORMAT_XRECHNUNG;
        }
        if (str_contains($filenameLower, 'zugferd') || str_contains($filenameLower, 'factur-x')) {
            return self::FORMAT_ZUGFERD;
        }

        // Check XML content for namespace hints
        if (str_contains($xmlContent, 'CrossIndustryInvoice')) {
            return self::FORMAT_XRECHNUNG;
        }
        if (str_contains($xmlContent, 'urn:ferd:CrossIndustryDocument')) {
            return self::FORMAT_ZUGFERD;
        }
        if (str_contains($xmlContent, 'urn:cen.eu:en16931:2017')) {
            return self::FORMAT_XRECHNUNG;
        }

        return self::FORMAT_UNKNOWN;
    }

    /**
     * Register XML namespaces for XPath queries.
     */
    protected function registerNamespaces(DOMXPath $xpath, DOMDocument $dom): void
    {
        // CII namespaces
        $xpath->registerNamespace('cii', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $xpath->registerNamespace('udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');

        // ZUGFeRD namespaces
        $xpath->registerNamespace('rsm', 'urn:ferd:CrossIndustryDocument:1p0');
        $xpath->registerNamespace('qdt', 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100');
        $xpath->registerNamespace('udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');
    }

    /**
     * Parse XRechnung / CII format.
     */
    protected function parseXRechnung(DOMXPath $xpath, DOMDocument $dom): array
    {
        $result = [
            'data' => [],
            'warnings' => [],
            'preview' => [],
        ];

        // Document ID and Type
        $result['data']['invoice_number'] = $this->getNodeText($xpath, '//cii:ExchangedDocument/ram:ID');
        $result['data']['invoice_type_code'] = $this->getNodeText($xpath, '//cii:ExchangedDocument/ram:TypeCode');
        $result['data']['invoice_date'] = $this->formatDate($this->getNodeText($xpath, '//cii:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString/@format'),
            $this->getNodeText($xpath, '//cii:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString'));

        // Seller Party
        $result['data']['seller'] = [
            'name' => $this->getNodeText($xpath, '//cii:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:Name'),
            'vat_id' => $this->getNodeText($xpath, '//cii:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:SpecifiedTaxRegistration[ram:ID/@schemeID="VA"]/ram:ID'),
            'street' => $this->getNodeText($xpath, '//cii:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:PostalTradeAddress/ram:LineOne'),
            'city' => $this->getNodeText($xpath, '//cii:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:PostalTradeAddress/ram:CityName'),
            'postal_code' => $this->getNodeText($xpath, '//cii:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:PostalTradeAddress/ram:PostcodeCode'),
            'country_code' => $this->getNodeText($xpath, '//cii:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:PostalTradeAddress/ram:CountryID'),
        ];

        // Buyer Party
        $result['data']['buyer'] = [
            'name' => $this->getNodeText($xpath, '//cii:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:Name'),
            'vat_id' => $this->getNodeText($xpath, '//cii:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:SpecifiedTaxRegistration[ram:ID/@schemeID="VA"]/ram:ID'),
            'street' => $this->getNodeText($xpath, '//cii:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:PostalTradeAddress/ram:LineOne'),
            'city' => $this->getNodeText($xpath, '//cii:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:PostalTradeAddress/ram:CityName'),
            'postal_code' => $this->getNodeText($xpath, '//cii:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:PostalTradeAddress/ram:PostcodeCode'),
            'country_code' => $this->getNodeText($xpath, '//cii:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:PostalTradeAddress/ram:CountryID'),
        ];

        // Monetary totals
        $result['data']['totals'] = [
            'line_total' => $this->getNodeText($xpath, '//cii:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:LineTotalAmount'),
            'tax_total' => $this->getNodeText($xpath, '//cii:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount'),
            'grand_total' => $this->getNodeText($xpath, '//cii:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:GrandTotalAmount'),
            'due_total' => $this->getNodeText($xpath, '//cii:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:DuePayableAmount'),
            'currency' => $this->getNodeText($xpath, '//cii:ApplicableHeaderTradeSettlement/ram:InvoiceCurrencyCode'),
        ];

        // Due date
        $result['data']['due_date'] = $this->formatDate(
            $this->getNodeText($xpath, '//cii:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:DueDateTime/udt:DateTimeString/@format'),
            $this->getNodeText($xpath, '//cii:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:DueDateTime/udt:DateTimeString')
        );

        // Line items
        $result['data']['items'] = [];
        $lineNodes = $xpath->query('//cii:IncludedSupplyChainTradeLineItem');
        foreach ($lineNodes as $index => $lineNode) {
            $lineXPath = new DOMXPath($lineNode->ownerDocument);
            $lineXPath->registerNamespace('cii', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
            $lineXPath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
            $lineXPath->registerNamespace('udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');

            $item = [
                'line_id' => $this->getNodeText($lineXPath, './/ram:AssociatedDocumentLineDocument/ram:LineID'),
                'name' => $this->getNodeText($lineXPath, './/ram:SpecifiedTradeProduct/ram:Name'),
                'description' => $this->getNodeText($lineXPath, './/ram:SpecifiedTradeProduct/ram:Description'),
                'quantity' => $this->getNodeText($lineXPath, './/ram:BilledQuantity'),
                'unit_code' => $this->getNodeText($lineXPath, './/ram:BilledQuantity/@unitCode'),
                'price' => $this->getNodeText($lineXPath, './/ram:NetPriceProductTradePrice/ram:ChargeAmount'),
                'line_total' => $this->getNodeText($lineXPath, './/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount'),
                'tax_rate' => $this->getNodeText($lineXPath, './/ram:ApplicableTradeTax/ram:RateApplicablePercent'),
            ];
            $result['data']['items'][] = $item;
        }

        // Tax breakdown
        $result['data']['taxes'] = [];
        $taxNodes = $xpath->query('//cii:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax');
        foreach ($taxNodes as $taxNode) {
            $taxXPath = new DOMXPath($taxNode->ownerDocument);
            $taxXPath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

            $result['data']['taxes'][] = [
                'rate' => $this->getNodeText($taxXPath, './ram:RateApplicablePercent'),
                'category' => $this->getNodeText($taxXPath, './ram:CategoryCode'),
                'basis' => $this->getNodeText($taxXPath, './ram:BasisAmount'),
                'amount' => $this->getNodeText($taxXPath, './ram:CalculatedAmount'),
            ];
        }

        // Build preview
        $result['preview'] = [
            'document_type' => 'XRechnung (CII)',
            'invoice_number' => $result['data']['invoice_number'],
            'invoice_date' => $result['data']['invoice_date'],
            'seller_name' => $result['data']['seller']['name'],
            'buyer_name' => $result['data']['buyer']['name'],
            'total' => $result['data']['totals']['grand_total'],
            'currency' => $result['data']['totals']['currency'],
            'line_items_count' => count($result['data']['items']),
        ];

        return $result;
    }

    /**
     * Parse ZUGFeRD format (similar to CII with different namespace).
     */
    protected function parseZugferd(DOMXPath $xpath, DOMDocument $dom): array
    {
        // ZUGFeRD 1.0 uses different namespace, try to detect
        $result = [
            'data' => [],
            'warnings' => [],
            'preview' => [],
        ];

        // Try ZUGFeRD namespace first
        $xpath->registerNamespace('rsm', 'urn:ferd:CrossIndustryDocument:1p0');
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $xpath->registerNamespace('udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');

        $result['data']['invoice_number'] = $this->getNodeText($xpath, '//rsm:ExchangedDocument/ram:ID');
        $result['data']['invoice_type_code'] = $this->getNodeText($xpath, '//rsm:ExchangedDocument/ram:TypeCode');

        // Seller
        $result['data']['seller'] = [
            'name' => $this->getNodeText($xpath, '//ram:SellerTradeParty/ram:Name'),
            'vat_id' => $this->getNodeText($xpath, '//ram:SellerTradeParty/ram:SpecifiedTaxRegistration[ram:ID/@schemeID="VA"]/ram:ID'),
            'country_code' => $this->getNodeText($xpath, '//ram:SellerTradeParty/ram:PostalTradeAddress/ram:CountryID'),
        ];

        // Buyer
        $result['data']['buyer'] = [
            'name' => $this->getNodeText($xpath, '//ram:BuyerTradeParty/ram:Name'),
            'vat_id' => $this->getNodeText($xpath, '//ram:BuyerTradeParty/ram:SpecifiedTaxRegistration[ram:ID/@schemeID="VA"]/ram:ID'),
        ];

        // Totals
        $result['data']['totals'] = [
            'grand_total' => $this->getNodeText($xpath, '//ram:GrandTotalAmount'),
            'currency' => $this->getNodeText($xpath, '//ram:InvoiceCurrencyCode'),
        ];

        // Build preview
        $result['preview'] = [
            'document_type' => 'ZUGFeRD',
            'invoice_number' => $result['data']['invoice_number'],
            'seller_name' => $result['data']['seller']['name'] ?? 'N/A',
            'buyer_name' => $result['data']['buyer']['name'] ?? 'N/A',
            'total' => $result['data']['totals']['grand_total'] ?? 'N/A',
        ];

        $result['warnings'][] = 'ZUGFeRD format detected - some fields may require conversion';

        return $result;
    }

    /**
     * Parse generic XML (fallback for unknown formats).
     */
    protected function parseGeneric(DOMXPath $xpath, DOMDocument $dom): array
    {
        $result = [
            'data' => [],
            'warnings' => ['Unknown XML format - attempting generic parse'],
            'preview' => [],
        ];

        // Try to find common invoice elements
        $result['data']['invoice_number'] = $this->getNodeText($xpath, '//*[local-name()="ID"]');
        $result['data']['invoice_date'] = $this->getNodeText($xpath, '//*[local-name()="Date"]');

        // Try to extract text content as preview
        $result['preview'] = [
            'document_type' => 'Generic XML',
            'invoice_number' => $result['data']['invoice_number'] ?? 'Not detected',
            'raw_content_preview' => substr($dom->saveXML(), 0, 500) . '...',
        ];

        $result['warnings'][] = 'Could not detect standard e-invoice format. Data may be incomplete.';

        return $result;
    }

    /**
     * Validate extracted data and add errors/warnings.
     */
    protected function validateExtractedData(array &$result): void
    {
        $data = $result['data'];

        // Required fields check
        if (empty($data['invoice_number'])) {
            $result['warnings'][] = 'Invoice number not found';
        }
        if (empty($data['invoice_date'])) {
            $result['warnings'][] = 'Invoice date not found';
        }
        if (empty($data['totals']['grand_total'])) {
            $result['warnings'][] = 'Invoice total not found';
        }
        if (empty($data['seller']['name'])) {
            $result['warnings'][] = 'Seller name not found';
        }
        if (empty($data['buyer']['name'])) {
            $result['warnings'][] = 'Buyer name not found';
        }

        // Set warning status if any warnings
        if (!empty($result['warnings']) && $result['status'] !== self::STATUS_ERROR) {
            $result['status'] = self::STATUS_WARNING;
        }
    }

    /**
     * Get text content from an XPath expression.
     */
    protected function getNodeText(DOMXPath $xpath, string $query): ?string
    {
        $node = $xpath->query($query);
        if ($node && $node->length > 0) {
            $text = $node->item(0)->textContent;
            return $text !== '' ? trim($text) : null;
        }
        return null;
    }

    /**
     * Create a draft invoice from previously parsed XML data.
     *
     * RB-IMP-14: Import Flow — persists parsed data as a DRAFT invoice.
     *
     * The caller is responsible for passing a valid $companyId.
     * customer_id is resolved by VAT-ID or company name match;
     * if no match is found it remains null (allowed for drafts).
     *
     * @param array $parsedData The 'data' sub-array from parse() output
     * @param int   $companyId  Company (tenant) that owns the new draft
     *
     * @return Invoice  The persisted draft invoice
     *
     * @throws \Throwable on DB error
     */
    public function createDraftFromParsedData(array $parsedData, int $companyId): Invoice
    {
        return DB::transaction(function () use ($parsedData, $companyId): Invoice {
            // --- Header ---
            $invoiceDate = isset($parsedData['invoice_date'])
                ? Carbon::parse($parsedData['invoice_date'])
                : Carbon::now();

            $dueDate = isset($parsedData['due_date'])
                ? Carbon::parse($parsedData['due_date'])
                : null;

            // --- Customer resolution ---
            $customerId = $this->resolveCustomerId(
                $parsedData['buyer'] ?? [],
                $companyId
            );

            // --- Summary: preserve seller info for review ---
            $sellerName = $parsedData['seller']['name'] ?? null;
            $summary    = $sellerName
                ? "Imported from XML — Seller: {$sellerName}"
                : 'Imported from XML';

            // --- Create invoice record ---
            $invoice = Invoice::create([
                'company_id'      => $companyId,
                'customer_id'     => $customerId,
                'invoice_number'  => $parsedData['invoice_number'] ?? null,
                'invoiced_at'     => $invoiceDate,
                'invoice_due_at'  => $dueDate,
                'invoice_status'  => InvoiceStatus::DRAFT,
                'summary'         => $summary,
                'invoice_sign'    => '1',
            ]);

            // --- Create line items ---
            $items = $parsedData['items'] ?? [];
            foreach ($items as $index => $item) {
                $quantity = (float) ($item['quantity'] ?? 1);
                $price    = (float) ($item['price'] ?? 0);
                $subtotal = (float) ($item['line_total'] ?? ($quantity * $price));

                InvoiceItem::create([
                    'invoice_id'    => $invoice->id,
                    'item_name'     => $item['name'] ?? ('Item ' . ($index + 1)),
                    'description'   => $item['description'] ?? '',
                    'quantity'      => $quantity,
                    'price'         => $price,
                    'subtotal'      => $subtotal,
                    'display_order' => $index + 1,
                ]);
            }

            Log::info('IncomingInvoiceParser: Draft created from XML', [
                'invoice_id'     => $invoice->id,
                'company_id'     => $companyId,
                'invoice_number' => $invoice->invoice_number,
                'items_count'    => count($items),
            ]);

            return $invoice->fresh();
        });
    }

    /**
     * Attempt to resolve an existing customer by VAT-ID or company name.
     * Returns null when no match is found (draft stays without customer).
     */
    protected function resolveCustomerId(array $buyerData, int $companyId): ?int
    {
        $vatId       = $buyerData['vat_id']  ?? null;
        $companyName = $buyerData['name']    ?? null;

        if ($vatId) {
            $relation = Relation::where('company_id', $companyId)
                ->where('vat_number', $vatId)
                ->first();
            if ($relation) {
                return $relation->id;
            }
        }

        if ($companyName) {
            $relation = Relation::where('company_id', $companyId)
                ->where('company_name', $companyName)
                ->first();
            if ($relation) {
                return $relation->id;
            }
        }

        return null;
    }

    /**
     * Format date based on format code.
     */
    protected function formatDate(?string $format, ?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        // Format 102 = YYYYMMDD
        if ($format === '102' && strlen($value) === 8) {
            return substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2);
        }

        // Try to parse as-is
        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return $value;
        }
    }
}
