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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            // RESTRICT (no cascade): the DB-level mirror of the application
            // deletion guard — attendance history is never wiped.
            $table->foreignId('student_id')->index()->constrained();
            $table->date('date');
            // present | absent | late | sick | leave (spec 03 §Schema).
            $table->string('status');
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            // rfid | dynamic_qr | manual_override; null = system-generated
            // (cron sweep, excuse injection).
            $table->string('scan_method')->nullable();
            $table->foreignId('override_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            // One record per student per day: simultaneous taps on two
            // scanners stay safe (create-once, update-thereafter).
            $table->unique(['student_id', 'date']);
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
