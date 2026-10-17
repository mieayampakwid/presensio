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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 30)->nullable()->unique();
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 20);
            $table->foreignId('deposit_account_id')->constrained('accounts')->restrictOnDelete();
            $table->date('payment_date');
            $table->string('origin_bank', 100)->nullable();
            $table->string('proof_path', 255)->nullable();
            $table->char('proof_hash', 64)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('reject_reason', 255)->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->string('notes', 255)->nullable();
            $table->foreignId('submitted_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('verified_at');
            $table->index('voided_at');
            $table->index('proof_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
