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
        Schema::create('lesson_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bell_schedule_id')->constrained('bell_schedules')->cascadeOnDelete();
            $table->string('kind', 10);
            $table->unsignedTinyInteger('number')->nullable();
            $table->string('name', 50)->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedTinyInteger('sort_order');
            $table->timestamps();

            $table->unique(['bell_schedule_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_periods');
    }
};
