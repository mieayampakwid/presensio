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
        Schema::create('fee_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('name', 100);
            $table->string('code', 30)->unique();
            $table->string('type', 30);
            $table->decimal('default_amount', 15, 2);
            $table->text('description')->nullable();
            $table->foreignId('receivable_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('revenue_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('discount_account_id')->constrained('accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_types');
    }
};
