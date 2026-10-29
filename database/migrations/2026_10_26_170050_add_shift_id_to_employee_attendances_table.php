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
        Schema::table('employee_attendances', function (Blueprint $table): void {
            // Snapshot of the effective shift at record creation (spec 20).
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_attendances', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shift_id');
        });
    }
};
