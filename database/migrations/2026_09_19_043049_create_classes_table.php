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
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            // Classes are year-scoped instances — "5A 2026/2027" and
            // "5A 2027/2028" are different rows (spec 02 v2.0).
            $table->foreignId('academic_year_id')->index()->constrained();
            $table->string('name');
            $table->unique(['name', 'academic_year_id']);
            // Duplicates allowed on purpose; the 1:1 homeroom rule lives in
            // config validation (spec 02 §4), not the schema.
            $table->foreignId('teacher_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
