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
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_subject_id')->constrained('class_subjects')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('kind', 20);
            $table->date('assessed_on');
            $table->decimal('max_score', 5, 2)->default(100.00);
            $table->timestamp('released_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['class_subject_id', 'semester_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
