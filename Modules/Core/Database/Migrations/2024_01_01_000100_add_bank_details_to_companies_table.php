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
        Schema::table('companies', function (Blueprint $table) {
            // Bankverbindung für Rechnungen
            $table->string('iban', 34)->nullable()->after('coc_number');
            $table->string('bic', 11)->nullable()->after('iban');
            $table->string('bank_name', 255)->nullable()->after('bic');
            $table->string('account_holder', 255)->nullable()->after('bank_name');
            
            // Standard-Zahlungsbedingungen
            $table->integer('default_payment_terms')->default(30)->after('account_holder')
                ->comment('Standard Zahlungsziel in Tagen');
            $table->text('default_payment_terms_text')->nullable()->after('default_payment_terms')
                ->comment('Freitext Zahlungsbedingungen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'iban',
                'bic',
                'bank_name',
                'account_holder',
                'default_payment_terms',
                'default_payment_terms_text',
            ]);
        });
    }
};
