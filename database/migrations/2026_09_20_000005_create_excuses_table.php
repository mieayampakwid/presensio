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
        Schema::create('excuses', function (Blueprint $table) {
            $table->id();
            // RESTRICT: the DB-level mirror of the application deletion
            // guard — excuse history is never wiped.
            $table->foreignId('student_id')->index()->constrained();
            // sick | leave (spec 04 §Schema) — strictly categorized,
            // no generic 'excused'.
            $table->string('type');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason');
            // Local-disk path under excuses/ (opaque token name); null =
            // submitted without proof.
            $table->string('attachment_path')->nullable();
            // pending | approved | rejected — terminal once resolved
            // (spec 04 decision): a mind-changed admin asks the guardian
            // to resubmit.
            $table->string('status');
            $table->string('review_note')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Overlap is a validation-time rule (rejected ranges may be
            // resubmitted, so no unique across ranges) — these indexes
            // serve the guard and the status-first list ordering.
            $table->index(['student_id', 'start_date']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('excuses');
    }
};
