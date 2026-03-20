<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * RB-IMP-09: Business Reference Fields Baseline
     * Fügt strukturierte Referenzfelder für XRechnung/B2B-Prozesse hinzu.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Referenzfelder für B2B/E-Invoice-Prozesse (am Ende der Tabelle)
            $table->string('buyer_reference', 255)->nullable()
                ->comment('Leitweg-/Käuferreferenz (Buyer Reference)');
            $table->string('order_reference', 255)->nullable()
                ->comment('Bestellreferenz (Order Reference)');
            $table->string('project_reference', 255)->nullable()
                ->comment('Projektreferenz');
            
            // Index für schnelle Suche
            $table->index('buyer_reference');
            $table->index('order_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['buyer_reference']);
            $table->dropIndex(['order_reference']);
            $table->dropColumn([
                'buyer_reference',
                'order_reference',
                'project_reference',
            ]);
        });
    }
};
