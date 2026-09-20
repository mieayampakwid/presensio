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
        Schema::create('absence_notifications', function (Blueprint $table) {
            $table->id();
            // RESTRICT: attendance rows have no delete path (spec 03), so
            // the delivery ledger should never be orphaned either.
            $table->foreignId('attendance_id')->index()->constrained();
            $table->foreignId('guardian_id')->index()->constrained();
            // whatsapp | email (spec 05 §Decisions: WhatsApp primary,
            // email fallback).
            $table->string('channel');
            // pending | sent | failed — written before the send attempt;
            // retries re-attempt everything not yet 'sent'.
            $table->string('status');
            $table->timestamps();

            // Spec 05 §Requirements 3: at most one notification per
            // guardian per attendance record — this unique pair is the
            // idempotency key across queue retries.
            $table->unique(['attendance_id', 'guardian_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('absence_notifications');
    }
};
