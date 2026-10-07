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
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->nullable()->unique();
            $table->date('entry_date');
            $table->string('description', 255);
            $table->string('status', 10);
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('reverses_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('attachment_path', 255)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index('entry_date');
            $table->index(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
