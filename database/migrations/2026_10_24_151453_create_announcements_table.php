<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('audience_type', 20);
            $table->string('audience_role', 20)->nullable();
            $table->unsignedTinyInteger('audience_grade_level')->nullable();
            $table->foreignId('class_id')->nullable()->constrained('classes')->restrictOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('requires_acknowledgement')->default(false);
            $table->boolean('notify_urgent')->default(false);
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->json('notification_recipients')->nullable();
            $table->timestamp('notifications_dispatched_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['audience_type', 'published_at']);
            $table->index(['class_id', 'published_at']);
            $table->index(['is_pinned', 'published_at']);
            $table->index(['academic_year_id', 'audience_grade_level']);
            $table->index(['notifications_dispatched_at', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
