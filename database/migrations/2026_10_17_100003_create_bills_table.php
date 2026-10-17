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
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('fee_type_id')->constrained('fee_types')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->string('period_key', 7);
            $table->string('title', 255);
            $table->decimal('amount', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0.00);
            $table->string('discount_reason', 255)->nullable();
            $table->decimal('paid_amount', 15, 2)->default(0.00);
            $table->string('status', 20)->default('unpaid');
            $table->date('recognized_at')->nullable();
            $table->boolean('is_opening')->default(false);
            $table->timestamp('written_off_at')->nullable();
            $table->string('write_off_reason', 255)->nullable();
            $table->date('due_date');
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'fee_type_id', 'period_key']);
            $table->index(['student_id', 'status']);
            $table->index(['academic_year_id', 'due_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
