<?php

namespace Modules\Invoices\Services;

use Illuminate\Support\Collection;
use Modules\Invoices\Models\Invoice;
use Modules\Invoices\Peppol\FormatHandlers\CiiHandler;
use Modules\Invoices\Peppol\FormatHandlers\ZugferdHandler;

/**
 * EInvoiceStatusService - Service for determining E-Invoice export readiness.
 *
 * RB-IMP-13: E-Invoice Validation Status UI
 *
 * Provides status determination for E-Invoice export capability:
 * - not_ready: Critical fields missing
 * - partially_ready: Some optional fields missing
 * - export_ready: All required fields present
 * - export_failed: Last export attempt failed
 */
class EInvoiceStatusService
{
    /**
     * E-Invoice status constants.
     */
    public const STATUS_NOT_READY      = 'not_ready';
    public const STATUS_PARTIALLY_READY = 'partially_ready';
    public const STATUS_EXPORT_READY    = 'export_ready';
    public const STATUS_EXPORT_FAILED   = 'export_failed';

    /**
     * Get the E-Invoice status for an invoice.
     *
     * @param Invoice $invoice
     *
     * @return array{status: string, label: string, color: string, errors: array<string>, warnings: array<string>}
     */
    public function getStatus(Invoice $invoice): array
    {
        // Check if last export failed (from session or log)
        if ($this->hasRecentExportFailure($invoice)) {
            return $this->buildStatus(
                self::STATUS_EXPORT_FAILED,
                trans('ip.einvoice_status_export_failed'),
                'danger',
                [],
                [trans('ip.einvoice_status_export_failed_hint')]
            );
        }

        // Get validation errors from handler
        $validationErrors = $this->getValidationErrors($invoice);
        $validationWarnings = $this->getValidationWarnings($invoice);

        // Determine status based on validation results
        if (empty($validationErrors) && empty($validationWarnings)) {
            return $this->buildStatus(
                self::STATUS_EXPORT_READY,
                trans('ip.einvoice_status_export_ready'),
                'success',
                [],
                []
            );
        }

        if (empty($validationErrors) && !empty($validationWarnings)) {
            return $this->buildStatus(
                self::STATUS_PARTIALLY_READY,
                trans('ip.einvoice_status_partially_ready'),
                'warning',
                [],
                $validationWarnings
            );
        }

        return $this->buildStatus(
            self::STATUS_NOT_READY,
            trans('ip.einvoice_status_not_ready'),
            'danger',
            $validationErrors,
            $validationWarnings
        );
    }

    /**
     * Check if the invoice has a recent export failure.
     *
     * @param Invoice $invoice
     *
     * @return bool
     */
    protected function hasRecentExportFailure(Invoice $invoice): bool
    {
        // Check session for recent export failure
        $failureKey = "einvoice_export_failed_{$invoice->getKey()}";
        $failedAt = session($failureKey);

        if ($failedAt && strtotime($failedAt) > strtotime('-5 minutes')) {
            return true;
        }

        return false;
    }

    /**
     * Get validation errors (critical missing fields).
     *
     * @param Invoice $invoice
     *
     * @return array<string>
     */
    protected function getValidationErrors(Invoice $invoice): array
    {
        $errors = [];

        // Critical validation using CII handler
        $ciiHandler = new CiiHandler();
        $ciiErrors = $ciiHandler->validate($invoice);

        foreach ($ciiErrors as $error) {
            $errors[] = $error;
        }

        // Check for required company data
        $company = $invoice->company;
        if (!$company) {
            $errors[] = trans('ip.einvoice_error_no_company');
        } else {
            if (empty($company->vat_number)) {
                $errors[] = trans('ip.einvoice_error_company_vat');
            }
            if (empty($company->name)) {
                $errors[] = trans('ip.einvoice_error_company_name');
            }
        }

        // Check for required customer data
        $customer = $invoice->customer;
        if (!$customer) {
            $errors[] = trans('ip.einvoice_error_no_customer');
        } else {
            if (empty($customer->company_name) && empty($customer->name)) {
                $errors[] = trans('ip.einvoice_error_customer_name');
            }
        }

        return array_unique($errors);
    }

    /**
     * Get validation warnings (optional fields missing).
     *
     * @param Invoice $invoice
     *
     * @return array<string>
     */
    protected function getValidationWarnings(Invoice $invoice): array
    {
        $warnings = [];

        // Check for optional but recommended fields
        $customer = $invoice->customer;

        if ($customer && empty($customer->vat_number)) {
            $warnings[] = trans('ip.einvoice_warning_customer_vat');
        }

        if ($customer && empty($customer->peppol_id)) {
            $warnings[] = trans('ip.einvoice_warning_peppol_id');
        }

        // Check for bank details
        $company = $invoice->company;
        if ($company && empty($company->iban)) {
            $warnings[] = trans('ip.einvoice_warning_iban');
        }

        return array_unique($warnings);
    }

    /**
     * Build status array.
     *
     * @param string $status
     * @param string $label
     * @param string $color
     * @param array<string> $errors
     * @param array<string> $warnings
     *
     * @return array{status: string, label: string, color: string, errors: array<string>, warnings: array<string>}
     */
    protected function buildStatus(string $status, string $label, string $color, array $errors, array $warnings): array
    {
        return [
            'status'   => $status,
            'label'    => $label,
            'color'    => $color,
            'errors'   => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Get available export formats for an invoice.
     *
     * @param Invoice $invoice
     *
     * @return Collection<int, array{format: string, label: string, supported: bool}>
     */
    public function getAvailableFormats(Invoice $invoice): Collection
    {
        $ciiStatus = (new CiiHandler())->validate($invoice);
        $zugferdStatus = (new ZugferdHandler())->validate($invoice);

        return collect([
            [
                'format'    => 'cii',
                'label'     => 'XRechnung (CII)',
                'supported' => empty($ciiStatus),
            ],
            [
                'format'    => 'zugferd_1.0',
                'label'     => 'ZUGFeRD 1.0',
                'supported' => empty($zugferdStatus),
            ],
            [
                'format'    => 'zugferd_2.0',
                'label'     => 'ZUGFeRD 2.0',
                'supported' => empty($zugferdStatus),
            ],
            [
                'format'    => 'peppol_bis_3.0',
                'label'     => 'Peppol BIS 3.0',
                'supported' => empty($ciiStatus), // Uses similar validation
            ],
        ]);
    }

    /**
     * Check if invoice can be exported as E-Invoice.
     *
     * @param Invoice $invoice
     *
     * @return bool
     */
    public function canExport(Invoice $invoice): bool
    {
        $status = $this->getStatus($invoice);

        return $status['status'] !== self::STATUS_NOT_READY
            && $status['status'] !== self::STATUS_EXPORT_FAILED;
    }
}
