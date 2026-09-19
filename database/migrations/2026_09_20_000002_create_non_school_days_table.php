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
        Schema::create('non_school_days', function (Blueprint $table) {
            $table->id();
            // Single source of truth for no-school dates: auto-synced
            // national holidays + admin-managed school dates (spec 03
            // §Decisions "Non-School Day Calendar"). One row per date —
            // sync and manual entries collide by design.
            $table->date('date')->unique();
            $table->string('name');
            // sync = holiday-feed import; manual = admin CRUD.
            $table->string('source');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('non_school_days');
    }
};
