<?php

namespace Modules\Invoices\Filament\Company\Resources\Invoices\Pages;

use Exception;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\Badge;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Invoices\Actions\SendInvoiceToPeppolAction;
use Modules\Invoices\Filament\Company\Resources\Invoices\InvoiceResource;
use Modules\Invoices\Services\EInvoiceStatusService;
use Modules\Invoices\Services\GermanB2BTransitionService;
use Modules\Invoices\Services\InvoiceService;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    public function mount($record): void
    {
        parent::mount($record);
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        $this->authorizeAccess();

        $this->callHook('beforeValidate');
        $data = $this->form->getState();
        $this->callHook('afterValidate');

        $data = $this->mutateFormDataBeforeSave($data);
        $this->callHook('beforeSave');

        $this->record = $this->handleRecordUpdate($this->getRecord(), $data);

        $this->callHook('afterSave');

        if ($shouldSendSavedNotification) {
            $this->getSavedNotification()?->send();
        }

        if ($shouldRedirect) {
            $this->redirect($this->getRedirectUrl());
        }
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(InvoiceService::class)->updateInvoice($record, $data);
    }

    protected function getHeaderActions(): array
    {
        $einvoiceStatus = app(EInvoiceStatusService::class)->getStatus($this->getRecord());
        $canExport = $einvoiceStatus['status'] !== 'not_ready' && $einvoiceStatus['status'] !== 'export_failed';

        return [
            Action::make('send_to_peppol')
                ->label(trans('ip.send_to_peppol'))
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->disabled(!$canExport)
                ->requiresConfirmation()
                ->form([
                    TextInput::make('customer_peppol_id')
                        ->label(trans('ip.customer_peppol_id'))
                        ->helperText(trans('ip.customer_peppol_id_helper'))
                        ->placeholder('BE:0123456789')
                        ->required(),
                ])
                ->action(function (array $data) {
                    try {
                        $action = app(SendInvoiceToPeppolAction::class);
                        $result = $action->execute($this->getRecord(), $data);

                        Notification::make()
                            ->title(trans('ip.peppol_success_title'))
                            ->body(trans('ip.peppol_success_body', [
                                'document_id' => $result['document_id'] ?? 'N/A',
                            ]))
                            ->success()
                            ->send();
                    } catch (Exception $e) {
                        Notification::make()
                            ->title(trans('ip.peppol_error_title'))
                            ->body(trans('ip.peppol_error_body', ['error' => $e->getMessage()]))
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('export_xrechnung')
                ->label('XRechnung')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->disabled(!$canExport)
                ->action(function () {
                    $this->downloadEInvoice('cii');
                }),
            Action::make('export_zugferd')
                ->label('ZUGFeRD')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->disabled(!$canExport)
                ->action(function () {
                    $this->downloadEInvoice('zugferd_2.0');
                }),
            DeleteAction::make(),
        ];
    }

    /**
     * Get E-Invoice status for display.
     */
    public function getEInvoiceStatus(): array
    {
        return app(EInvoiceStatusService::class)->getStatus($this->getRecord());
    }

    /**
     * Download E-Invoice as XML.
     */
    protected function downloadEInvoice(string $format): void
    {
        try {
            $invoice = $this->getRecord();
            $handler = \Modules\Invoices\Peppol\FormatHandlers\FormatHandlerFactory::create(
                \Modules\Invoices\Peppol\Enums\PeppolDocumentFormat::from($format)
            );

            $xml = $handler->generateXml($invoice);

            $filename = 'invoice_' . ($invoice->invoice_number ?? 'draft') . '_' . $format . '.xml';

            response()->streamDownload(
                fn () => print($xml),
                $filename,
                ['Content-Type' => 'application/xml']
            )->send();
        } catch (Exception $e) {
            // Store failure in session
            session()->put("einvoice_export_failed_{$this->getRecord()->getKey()}", now()->toDateTimeString());

            Notification::make()
                ->title(trans('ip.einvoice_export_error') ?? 'Export Error')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->redirect(request()->url());
        }
    }

    /**
     * Get the info list for the invoice.
     *
     * Shows both the E-Invoice export readiness status (RB-IMP-13)
     * and the German B2B transition status (RB-IMP-16).
     */
    public function infoList(Infolist $infolist): Infolist
    {
        $status     = $this->getEInvoiceStatus();
        $b2bStatus  = app(GermanB2BTransitionService::class)
            ->getTransitionStatus($this->getRecord());

        return $infolist
            ->schema([
                // --- E-Invoice export readiness (RB-IMP-13) ---
                Section::make(trans('ip.einvoice_status') ?? 'E-Invoice Status')
                    ->schema([
                        Group::make([
                            Badge::make('status', fn () => $status['status'])
                                ->label(trans('ip.einvoice_status') ?? 'Status')
                                ->color($status['color'])
                                ->icon(fn (string $state): string => match ($state) {
                                    'export_ready'    => 'heroicon-o-check-circle',
                                    'partially_ready' => 'heroicon-o-exclamation-circle',
                                    'not_ready'       => 'heroicon-o-x-circle',
                                    'export_failed'   => 'heroicon-o-exclamation-triangle',
                                    default           => 'heroicon-o-question-mark-circle',
                                }),
                        ]),
                    ])
                    ->headerActions([
                        Action::make('refresh_status')
                            ->icon('heroicon-o-arrow-path')
                            ->label('')
                            ->tooltip('Refresh Status')
                            ->action(function () {
                                $this->redirect(request()->url());
                            }),
                    ])
                    ->collapsible(),

                // --- German B2B E-Invoice transition status (RB-IMP-16) ---
                Section::make(trans('ip.b2b_transition_status') ?? 'B2B E-Invoice Transition')
                    ->schema([
                        Group::make([
                            Badge::make('b2b_status', fn () => $b2bStatus['status'])
                                ->label(trans('ip.b2b_transition_status_label') ?? 'DE B2B Status')
                                ->color($b2bStatus['color'])
                                ->icon(fn (string $state): string => match ($state) {
                                    GermanB2BTransitionService::STATUS_EINVOICE_EXPECTED    => 'heroicon-o-exclamation-triangle',
                                    GermanB2BTransitionService::STATUS_EINVOICE_RECOMMENDED => 'heroicon-o-information-circle',
                                    GermanB2BTransitionService::STATUS_REVIEW_REQUIRED      => 'heroicon-o-magnifying-glass',
                                    default                                                  => 'heroicon-o-minus-circle',
                                }),
                            TextEntry::make('b2b_description')
                                ->label(trans('ip.b2b_transition_description') ?? 'Details')
                                ->formatStateUsing(fn () => $b2bStatus['description'])
                                ->visible(fn (): bool => $b2bStatus['requires_action']),
                        ]),
                    ])
                    ->visible(fn (): bool => $b2bStatus['status'] !== GermanB2BTransitionService::STATUS_NOT_APPLICABLE)
                    ->collapsible(),
            ]);
    }
}
