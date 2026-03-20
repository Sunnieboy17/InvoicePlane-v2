<?php

namespace Modules\Invoices\Filament\Company\Resources\Invoices\Pages;

use Filament\Actions\Action;
use Filament\Actions\UploadAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\HtmlString;
use Modules\Invoices\Filament\Company\Resources\Invoices\InvoiceResource;
use Modules\Invoices\Services\IncomingInvoiceParserService;

/**
 * IncomingXmlUpload - RB-IMP-14
 *
 * XML Invoice Upload & Parse MVP Page.
 *
 * Provides:
 * - Upload entry point for XML invoice files
 * - Technical parsing of structured XML files
 * - Visible feedback on parse success/parse error/missing fields
 * - Preview of extracted data
 */
class IncomingXmlUpload extends Page
{
    protected static string $resource = null; // No resource association

    protected static string $view = 'invoices::filament.pages.incoming-xml-upload';

    protected static ?string $title = 'Incoming E-Invoice';

    protected static ?string $navigationLabel = 'Incoming XML';

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static ?string $slug = 'incoming-xml';

    public ?array $parseResult = null;

    public ?string $uploadedFilename = null;

    public function mount(): void
    {
        $this->parseResult = null;
    }

    /**
     * Get header actions.
     */
    protected function getHeaderActions(): array
    {
        return [
            UploadAction::make('uploadXml')
                ->label(trans('ip.upload_xml_invoice') ?? 'Upload XML Invoice')
                ->acceptedFileTypes(['application/xml', 'text/xml', '.xml', '.xrechnung', '.zugferd'])
                ->maxSize(10 * 1024) // 10MB
                ->afterStateUpdated(function (UploadedFile $file, callable $set) {
                    $this->handleUpload($file, $set);
                }),

            // RB-IMP-14: Import Flow — only visible after a successful/warning parse
            Action::make('importAsDraft')
                ->label(trans('ip.import_as_draft') ?? 'Import as Draft')
                ->icon('heroicon-o-document-plus')
                ->color('success')
                ->visible(fn (): bool => $this->parseResult !== null
                    && ($this->parseResult['status'] === IncomingInvoiceParserService::STATUS_SUCCESS
                        || $this->parseResult['status'] === IncomingInvoiceParserService::STATUS_WARNING))
                ->requiresConfirmation()
                ->modalHeading(trans('ip.import_as_draft_confirm_title') ?? 'Import Invoice as Draft?')
                ->modalDescription(
                    trans('ip.import_as_draft_confirm_desc')
                    ?? 'A new draft invoice will be created from the parsed XML data. You can review and complete it before finalising.'
                )
                ->action(function (): void {
                    $this->handleImportAsDraft();
                }),
        ];
    }

    /**
     * Handle XML file upload and parse.
     */
    protected function handleUpload(UploadedFile $file, callable $set): void
    {
        $this->uploadedFilename = $file->getClientOriginalName();

        try {
            $content = file_get_contents($file->getRealPath());
            $parser = new IncomingInvoiceParserService();
            $this->parseResult = $parser->parse($content, $this->uploadedFilename);

            // Set form data if needed
            $set('parseResult', $this->parseResult);

            // Show notification based on status
            match ($this->parseResult['status']) {
                IncomingInvoiceParserService::STATUS_SUCCESS => Notification::make()
                    ->title(trans('ip.parse_success') ?? 'Parse Successful')
                    ->body(trans('ip.parse_success_body') ?? 'XML parsed successfully. Review the extracted data below.')
                    ->success()
                    ->send(),
                IncomingInvoiceParserService::STATUS_WARNING => Notification::make()
                    ->title(trans('ip.parse_warning') ?? 'Parse with Warnings')
                    ->body(trans('ip.parse_warning_body') ?? 'XML parsed with some warnings. Please review missing fields.')
                    ->warning()
                    ->send(),
                default => Notification::make()
                    ->title(trans('ip.parse_error') ?? 'Parse Error')
                    ->body(trans('ip.parse_error_body') ?? 'Failed to parse XML. Please check the file format.')
                    ->danger()
                    ->send(),
            };
        } catch (\Exception $e) {
            Notification::make()
                ->title(trans('ip.upload_failed') ?? 'Upload Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * RB-IMP-14: Import Flow
     *
     * Persist parsed invoice data as a DRAFT and redirect to the edit page.
     */
    protected function handleImportAsDraft(): void
    {
        if (! $this->parseResult || empty($this->parseResult['data'])) {
            Notification::make()
                ->title(trans('ip.import_failed') ?? 'Import Failed')
                ->body(trans('ip.import_failed_no_data') ?? 'No parsed data available. Please upload and parse an XML file first.')
                ->danger()
                ->send();

            return;
        }

        try {
            $companyId = auth()->user()?->company_id
                ?? filament()->getTenant()?->id
                ?? null;

            if (! $companyId) {
                throw new \RuntimeException('Could not determine current company.');
            }

            $parser  = new IncomingInvoiceParserService();
            $invoice = $parser->createDraftFromParsedData(
                $this->parseResult['data'],
                $companyId
            );

            Notification::make()
                ->title(trans('ip.import_success') ?? 'Draft Created')
                ->body(
                    trans('ip.import_success_body', ['number' => $invoice->invoice_number ?? '—'])
                    ?? "Draft invoice #{$invoice->invoice_number} created. Please review and complete it."
                )
                ->success()
                ->send();

            // Redirect to the new draft for review
            $this->redirect(
                InvoiceResource::getUrl('edit', ['record' => $invoice->id])
            );
        } catch (\Throwable $e) {
            Notification::make()
                ->title(trans('ip.import_failed') ?? 'Import Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Get info list for parse result display.
     */
    public function infolist(Infolist $infolist): Infolist
    {
        if (!$this->parseResult) {
            return $infolist->schema([]);
        }

        $result = $this->parseResult;
        $data = $result['data'] ?? [];

        return $infolist
            ->schema([
                InfolistSection::make(trans('ip.parse_result') ?? 'Parse Result')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                IconEntry::make('status_icon')
                                    ->icon(fn () => match ($result['status']) {
                                        IncomingInvoiceParserService::STATUS_SUCCESS => 'heroicon-o-check-circle',
                                        IncomingInvoiceParserService::STATUS_WARNING => 'heroicon-o-exclamation-triangle',
                                        default => 'heroicon-o-x-circle',
                                    })
                                    ->color(fn () => match ($result['status']) {
                                        IncomingInvoiceParserService::STATUS_SUCCESS => 'success',
                                        IncomingInvoiceParserService::STATUS_WARNING => 'warning',
                                        default => 'danger',
                                    }),
                                TextEntry::make('format')
                                    ->label(trans('ip.detected_format') ?? 'Detected Format')
                                    ->badge()
                                    ->color('info')
                                    ->formatStateUsing(fn () => strtoupper($result['format'])),
                                TextEntry::make('filename')
                                    ->label(trans('ip.filename') ?? 'Filename')
                                    ->formatStateUsing(fn () => $this->uploadedFilename),
                            ]),
                    ])
                    ->collapsible(),

                InfolistSection::make(trans('ip.invoice_details') ?? 'Invoice Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('invoice_number')
                                    ->label(trans('ip.invoice_number') ?? 'Invoice Number')
                                    ->formatStateUsing(fn () => $data['invoice_number'] ?? 'N/A'),
                                TextEntry::make('invoice_date')
                                    ->label(trans('ip.invoice_date') ?? 'Invoice Date')
                                    ->formatStateUsing(fn () => $data['invoice_date'] ?? 'N/A'),
                                TextEntry::make('invoice_type')
                                    ->label(trans('ip.invoice_type') ?? 'Invoice Type')
                                    ->formatStateUsing(fn () => $this->getInvoiceTypeLabel($data['invoice_type_code'] ?? '')),
                                TextEntry::make('due_date')
                                    ->label(trans('ip.due_date') ?? 'Due Date')
                                    ->formatStateUsing(fn () => $data['due_date'] ?? 'N/A'),
                            ]),
                    ])
                    ->collapsible(),

                InfolistSection::make(trans('ip.parties') ?? 'Parties')
                    ->schema([
                        Group::make([
                            TextEntry::make('seller_name')
                                ->label(trans('ip.seller') ?? 'Seller')
                                ->formatStateUsing(fn () => $data['seller']['name'] ?? 'N/A'),
                            TextEntry::make('seller_vat')
                                ->label(trans('ip.vat_id') ?? 'VAT ID')
                                ->formatStateUsing(fn () => $data['seller']['vat_id'] ?? 'N/A'),
                            TextEntry::make('seller_country')
                                ->label(trans('ip.country') ?? 'Country')
                                ->formatStateUsing(fn () => $data['seller']['country_code'] ?? 'N/A'),
                        ])->columnSpan(1),
                        Group::make([
                            TextEntry::make('buyer_name')
                                ->label(trans('ip.buyer') ?? 'Buyer')
                                ->formatStateUsing(fn () => $data['buyer']['name'] ?? 'N/A'),
                            TextEntry::make('buyer_vat')
                                ->label(trans('ip.vat_id') ?? 'VAT ID')
                                ->formatStateUsing(fn () => $data['buyer']['vat_id'] ?? 'N/A'),
                            TextEntry::make('buyer_country')
                                ->label(trans('ip.country') ?? 'Country')
                                ->formatStateUsing(fn () => $data['buyer']['country_code'] ?? 'N/A'),
                        ])->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),

                InfolistSection::make(trans('ip.totals') ?? 'Totals')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('line_total')
                                    ->label(trans('ip.line_total') ?? 'Line Total')
                                    ->formatStateUsing(fn () => $this->formatAmount($data['totals']['line_total'] ?? null, $data['totals']['currency'] ?? 'EUR')),
                                TextEntry::make('tax_total')
                                    ->label(trans('ip.tax_total') ?? 'Tax Total')
                                    ->formatStateUsing(fn () => $this->formatAmount($data['totals']['tax_total'] ?? null, $data['totals']['currency'] ?? 'EUR')),
                                TextEntry::make('grand_total')
                                    ->label(trans('ip.grand_total') ?? 'Grand Total')
                                    ->formatStateUsing(fn () => $this->formatAmount($data['totals']['grand_total'] ?? null, $data['totals']['currency'] ?? 'EUR'))
                                    ->weight('bold'),
                                TextEntry::make('due_total')
                                    ->label(trans('ip.amount_due') ?? 'Amount Due')
                                    ->formatStateUsing(fn () => $this->formatAmount($data['totals']['due_total'] ?? null, $data['totals']['currency'] ?? 'EUR')),
                            ]),
                    ])
                    ->collapsible(),

                InfolistSection::make(trans('ip.line_items') ?? 'Line Items')
                    ->schema([
                        TextEntry::make('items_count')
                            ->label(trans('ip.items_count') ?? 'Number of Items')
                            ->formatStateUsing(fn () => count($data['items'] ?? [])),
                    ])
                    ->collapsible(),

                // Warnings
                InfolistSection::make(trans('ip.warnings') ?? 'Warnings')
                    ->visible(!empty($result['warnings']))
                    ->schema([
                        ...collect($result['warnings'] ?? [])->map(fn ($w) => TextEntry::make("warning_{$w}")
                            ->formatStateUsing(fn () => $w)
                            ->color('warning')
                        )->toArray(),
                    ])
                    ->collapsible(),

                // Errors
                InfolistSection::make(trans('ip.errors') ?? 'Errors')
                    ->visible(!empty($result['errors']))
                    ->schema([
                        ...collect($result['errors'] ?? [])->map(fn ($e) => TextEntry::make("error_{$e}")
                            ->formatStateUsing(fn () => $e)
                            ->color('danger')
                        )->toArray(),
                    ])
                    ->collapsible(),
            ]);
    }

    /**
     * Get invoice type label from code.
     */
    protected function getInvoiceTypeLabel(string $code): string
    {
        return match ($code) {
            '380' => 'Commercial Invoice',
            '381' => 'Credit Note',
            '383' => 'Debit Note',
            '384' => 'Corrected Invoice',
            '389' => 'Self-Billed Invoice',
            default => $code ?: 'Unknown',
        };
    }

    /**
     * Format currency amount.
     */
    protected function formatAmount(?string $amount, string $currency = 'EUR'): string
    {
        if ($amount === null || $amount === '') {
            return 'N/A';
        }
        $num = floatval($amount);
        return number_format($num, 2, ',', '.') . ' ' . $currency;
    }
}
