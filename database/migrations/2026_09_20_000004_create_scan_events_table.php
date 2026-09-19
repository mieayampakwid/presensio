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
        Schema::create('scan_events', function (Blueprint $table) {
            $table->id();
            // Null when the credential resolves to nobody.
            $table->foreignId('student_id')->nullable()->index();
            // rfid | dynamic_qr only (spec 03 §Schema).
            $table->string('scan_method');
            // Raw rfid_number on RFID attempts; always null for QR —
            // tokens expire by design and are never stored.
            $table->string('identifier')->nullable();
            // Device clock when trusted within the drift window, else
            // server time.
            $table->timestamp('scanned_at');
            $table->string('outcome');
            $table->timestamps();

            $table->index('scanned_at');

            // Append-only history: every scan attempt lands here exactly
            // once, including errors. Nothing ever updates or deletes rows.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scan_events');
    }
};
