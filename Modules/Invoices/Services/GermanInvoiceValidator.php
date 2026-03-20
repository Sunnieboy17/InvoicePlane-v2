<?php

namespace Modules\Invoices\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Invoices\Enums\InvoiceStatus;
use Modules\Invoices\Models\Invoice;

/**
 * German Invoice Validation Service
 * 
 * Validates invoices according to German legal requirements for finalization.
 * Draft invoices are allowed to be incomplete.
 * 
 * RB-IMP-06: Invoice Required Field Validation
 */
class GermanInvoiceValidator
{
    /**
     * Validation rules for German invoice finalization.
     * Only applied when moving to non-draft status.
     */
    protected array $requiredRules = [
        'customer_id'     => 'required|integer|exists:relations,id',
        'invoice_number'  => 'required|string|max:255',
        'invoiced_at'    => 'required|date',
        'invoice_due_at' => 'required|date|after_or_equal:invoiced_at',
        'invoiceItems'   => 'required|array|min:1',
    ];

    /**
     * German-specific field requirements for E-Invoice export readiness.
     */
    protected array $germanRequiredFields = [
        'company.vat_number'     => 'ip.vat_id',
        'company.name'          => 'ip.name',
        'customer.vat_number'   => 'ip.vat_id',
        'customer.company_name' => 'ip.company_name',
    ];

    /**
     * Validate an invoice for finalization (non-draft status).
     * 
     * @param Invoice $invoice
     * @return array ['valid' => bool, 'errors' => array, 'messages' => array]
     */
    public function validateForFinalization(Invoice $invoice): array
    {
        $errors = [];
        $messages = [];

        // Check if already in draft status - drafts can always be saved
        if ($invoice->invoice_status === InvoiceStatus::DRAFT->value) {
            return [
                'valid' => true,
                'errors' => [],
                'messages' => [],
            ];
        }

        // Run standard validation
        $validationResult = $this->validateRequiredFields($invoice);
        $errors = array_merge($errors, $validationResult['errors']);
        $messages = array_merge($messages, $validationResult['messages']);

        // Run German-specific validation
        $germanResult = $this->validateGermanRequirements($invoice);
        $errors = array_merge($errors, $germanResult['errors']);
        $messages = array_merge($messages, $germanResult['messages']);

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'messages' => $messages,
        ];
    }

    /**
     * Validate required fields for invoice.
     */
    protected function validateRequiredFields(Invoice $invoice): array
    {
        $errors = [];
        $messages = [];

        // Customer validation
        if (!$invoice->customer_id) {
            $errors[] = 'customer_id';
            $messages[] = trans('ip.validation_customer_required');
        }

        // Invoice number validation
        if (empty($invoice->invoice_number)) {
            $errors[] = 'invoice_number';
            $messages[] = trans('ip.validation_invoice_number_required');
        }

        // Invoice date validation
        if (empty($invoice->invoiced_at)) {
            $errors[] = 'invoiced_at';
            $messages[] = trans('ip.validation_invoice_date_required');
        }

        // Due date validation
        if (empty($invoice->invoice_due_at)) {
            $errors[] = 'invoice_due_at';
            $messages[] = trans('ip.validation_due_date_required');
        } elseif ($invoice->invoiced_at && $invoice->invoice_due_at < $invoice->invoiced_at) {
            $errors[] = 'invoice_due_at';
            $messages[] = trans('ip.validation_due_date_before_invoice_date');
        }

        // Invoice items validation
        if (!$invoice->invoiceItems || $invoice->invoiceItems->isEmpty()) {
            $errors[] = 'invoiceItems';
            $messages[] = trans('ip.validation_invoice_items_required');
        }

        return [
            'errors' => $errors,
            'messages' => $messages,
        ];
    }

    /**
     * Validate German-specific invoice requirements.
     */
    protected function validateGermanRequirements(Invoice $invoice): array
    {
        $errors = [];
        $messages = [];

        $company = $invoice->company;
        $customer = $invoice->customer;

        // Company requirements
        if (!$company) {
            $errors[] = 'company';
            $messages[] = trans('ip.validation_company_required');
        } else {
            if (empty($company->name)) {
                $errors[] = 'company.name';
                $messages[] = trans('ip.validation_company_name_required');
            }
        }

        // Customer requirements
        if (!$customer) {
            $errors[] = 'customer';
            $messages[] = trans('ip.validation_customer_required');
        } else {
            if (empty($customer->company_name)) {
                $errors[] = 'customer.company_name';
                $messages[] = trans('ip.validation_customer_name_required');
            }
        }

        return [
            'errors' => $errors,
            'messages' => $messages,
        ];
    }

    /**
     * Get all missing fields for an invoice.
     * 
     * @param Invoice $invoice
     * @return Collection
     */
    public function getMissingFields(Invoice $invoice): Collection
    {
        $missing = collect();

        if (!$invoice->customer_id) {
            $missing->push(['field' => 'customer_id', 'label' => trans('ip.customer')]);
        }

        if (empty($invoice->invoice_number)) {
            $missing->push(['field' => 'invoice_number', 'label' => trans('ip.invoice_number')]);
        }

        if (empty($invoice->invoiced_at)) {
            $missing->push(['field' => 'invoiced_at', 'label' => trans('ip.invoice_date')]);
        }

        if (empty($invoice->invoice_due_at)) {
            $missing->push(['field' => 'invoice_due_at', 'label' => trans('ip.invoice_due_at')]);
        }

        if (!$invoice->invoiceItems || $invoice->invoiceItems->isEmpty()) {
            $missing->push(['field' => 'invoiceItems', 'label' => trans('ip.invoice_items')]);
        }

        return $missing;
    }

    /**
     * Check if invoice can be finalized.
     * 
     * @param Invoice $invoice
     * @return bool
     */
    public function canFinalize(Invoice $invoice): bool
    {
        // Drafts can always be finalized (they need data first)
        if ($invoice->invoice_status === InvoiceStatus::DRAFT->value) {
            return true;
        }

        return $this->validateForFinalization($invoice)['valid'];
    }
}
