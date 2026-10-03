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
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('dedupe_key', 191);
            $table->string('type_key', 50);
            $table->string('channel', 20);
            $table->string('recipient_type', 30);
            $table->unsignedBigInteger('recipient_id');
            $table->string('recipient_contact', 255)->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('scheduled_for')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('provider_message_id', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['dedupe_key', 'channel']);
            $table->index(['status', 'created_at']);
            $table->index(['type_key', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
