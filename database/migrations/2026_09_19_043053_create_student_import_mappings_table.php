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
        Schema::create('student_import_mappings', function (Blueprint $table) {
            $table->id();
            // sha256 of the normalized header row; identical file structures
            // share one mapping (spec 02 §8 "remembered mapping").
            $table->string('header_fingerprint')->unique();
            $table->json('mapping');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_import_mappings');
    }
};
