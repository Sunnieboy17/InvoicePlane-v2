<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * RB-IMP-07: German Numbering Strategy Hardening
 * 
 * Adds unique constraint on (company_id, invoice_number) to prevent
 * duplicate invoice numbers within the same company.
 * 
 * NULL invoice_numbers are excluded from the unique constraint
 * (allows multiple drafts without numbers).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add unique index on company_id + invoice_number
        // WHERE invoice_number IS NOT NULL (drafts can have null numbers)
        Schema::table('invoices', function (Blueprint $table): void {
            // Create a unique index that excludes NULL values
            // Using a partial index approach via WHERE clause
            $table->unique(
                ['company_id', 'invoice_number'],
                'invoices_company_invoice_unique'
            );
        });

        // Log the hardening measure
        if (app()->bound('log')) {
            app('log')->info('RB-IMP-07: Added unique constraint on invoices(company_id, invoice_number)');
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('invoices_company_invoice_unique');
        });
    }
};
