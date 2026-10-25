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
        Schema::create('timetable_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained('semesters');
            $table->foreignId('class_id')->constrained('classes');
            $table->unsignedTinyInteger('day_of_week');
            $table->foreignId('lesson_period_id')->constrained('lesson_periods');
            $table->unsignedTinyInteger('period_count')->default(1);
            $table->foreignId('class_subject_id')->nullable()->constrained('class_subjects');
            $table->string('activity_name', 100)->nullable();
            $table->string('room', 50)->nullable();
            $table->timestamps();

            $table->index(['semester_id', 'class_id', 'day_of_week']);
            $table->index('class_subject_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetable_slots');
    }
};
