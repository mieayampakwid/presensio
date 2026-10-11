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
        Schema::create('subject_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_semester_id')->constrained('course_semesters')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->unsignedTinyInteger('final_score')->nullable();
            $table->text('description')->nullable();
            $table->boolean('description_edited')->default(false);
            $table->timestamps();

            $table->unique(['course_semester_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subject_results');
    }
};
