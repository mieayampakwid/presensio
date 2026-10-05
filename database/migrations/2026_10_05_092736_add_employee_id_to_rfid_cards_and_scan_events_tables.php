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
        Schema::table('rfid_cards', function (Blueprint $table): void {
            $table->foreignId('employee_id')->nullable()->index()->constrained('employees')->nullOnDelete();
        });

        Schema::table('scan_events', function (Blueprint $table): void {
            $table->foreignId('employee_id')->nullable()->index()->constrained('employees')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scan_events', function (Blueprint $table): void {
            $table->dropForeign(['employee_id']);
            $table->dropColumn('employee_id');
        });

        Schema::table('rfid_cards', function (Blueprint $table): void {
            $table->dropForeign(['employee_id']);
            $table->dropColumn('employee_id');
        });
    }
};
