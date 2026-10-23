<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overpayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('related_payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->foreignId('liability_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('deposit_account_id')->constrained('accounts')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('bank_mutation_date');
            $table->date('posting_date')->index();
            $table->string('reason', 30);
            $table->string('status', 30)->default('held');
            $table->text('note')->nullable();
            $table->string('proof_path');
            $table->string('proof_hash', 64)->index();
            foreach (['receipt_entry_id', 'refund_entry_id', 'cancellation_entry_id'] as $column) {
                $table->foreignId($column)->nullable()->unique()->constrained('journal_entries')->restrictOnDelete();
            }
            $table->date('refund_date')->nullable();
            $table->date('requested_cancel_date')->nullable();
            $table->foreignId('refund_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->decimal('bank_fee', 15, 2)->nullable();
            $table->decimal('net_refund', 15, 2)->nullable();
            $table->string('bank_reference')->nullable();
            $table->string('refund_proof_path')->nullable();
            $table->string('refund_proof_hash', 64)->nullable();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('refunded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'guardian_id']);
            $table->index('liability_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overpayments');
    }
};
