<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. The partial unique index (one open enrollment
     * per student) needs raw DDL — the schema builder cannot express
     * WHERE-clause indexes; the statement is valid on both sqlite (tests)
     * and PostgreSQL (dev).
     */
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            // Students are only deletable with zero history, so a cascade
            // here can never orphan or destroy real history.
            $table->foreignId('student_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->index()->constrained();
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->index(['class_id', 'started_on']);
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX enrollments_open_unique ON enrollments (student_id) WHERE ended_on IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
