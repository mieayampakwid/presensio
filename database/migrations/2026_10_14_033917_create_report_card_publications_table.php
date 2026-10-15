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
        Schema::create('report_card_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->smallInteger('version')->unsigned();
            $table->timestamp('published_at');
            $table->foreignId('published_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('retracted_at')->nullable();
            $table->foreignId('retracted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('retract_reason')->nullable();
            $table->timestamps();

            $table->unique(['semester_id', 'class_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_card_publications');
    }
};
