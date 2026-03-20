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
        Schema::create('standard_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50)->unique('company_code'); // Eindeutig pro Company
            $table->string('title', 255)->nullable();
            $table->text('body');
            $table->string('type', 50)->nullable(); // tax_notice, payment_notice, reminder_notice, legal_notice
            $table->string('trigger', 255)->nullable(); // z.B. "tax_code:DE-KLEIN-0" oder "country:EU"
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexe für Performance
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('standard_notices');
    }
};
