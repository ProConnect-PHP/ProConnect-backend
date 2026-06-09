<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_session_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('video_session_id')
                ->constrained('video_sessions')
                ->cascadeOnDelete();

            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('role');

            $table->string('provider_identity')->nullable();
            $table->string('display_name')->nullable();

            $table->timestamp('first_joined_at')->nullable();
            $table->timestamp('last_joined_at')->nullable();
            $table->timestamp('left_at')->nullable();

            $table->unsignedInteger('join_count')->default(0);

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(['video_session_id', 'user_id']);
            $table->index(['user_id']);
            $table->index(['role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_session_participants');
    }
};
