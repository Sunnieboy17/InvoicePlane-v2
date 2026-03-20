<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            // Leistungsdatum / Service Date
            // Für Deutschland: §14 Abs. 4 Nr. 6 UStG - Zeitpunkt der Lieferung/sonstigen Leistung
            $table->date('service_date')->nullable()->after('invoice_due_at');
            
            // Leistungszeitraum / Service Period
            // Für Dauerleistungen und periodische Abrechnungen
            $table->date('service_period_start')->nullable()->after('service_date');
            $table->date('service_period_end')->nullable()->after('service_period_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['service_date', 'service_period_start', 'service_period_end']);
        });
    }
};
