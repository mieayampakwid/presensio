<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            // Single-row table (id = 1). All times are evaluated in the
            // school timezone — the app itself runs UTC (spec 03 §Decisions).
            $table->string('school_timezone')->default('Asia/Jakarta');
            $table->time('school_start_time')->default('07:30:00');
            $table->boolean('require_checkout')->default(false);
            $table->time('auto_absent_cron_time')->default('15:30:00');
            $table->unsignedSmallInteger('scan_debounce_minutes')->default(1);
            $table->unsignedSmallInteger('scan_drift_tolerance_minutes')->default(2);
            $table->timestamps();
        });

        DB::table('settings')->insert([
            'id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
