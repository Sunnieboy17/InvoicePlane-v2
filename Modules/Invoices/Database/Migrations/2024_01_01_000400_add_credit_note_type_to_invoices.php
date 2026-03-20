<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * RB-IMP-15: Credit Note / Cancellation Baseline
     *
     * Adds credit_note_type field to distinguish between:
     * - credit: Full or partial credit
     * - cancellation: Complete cancellation
     * - correction: Corrected invoice
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('credit_note_type', 50)
                ->nullable()
                ->after('creditinvoice_parent_id')
                ->comment('Type of credit note: credit, cancellation, correction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('credit_note_type');
        });
    }
};
