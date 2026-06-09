<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('booking_id')
                ->unique()
                ->constrained('bookings')
                ->cascadeOnDelete();

            $table->foreignUuid('client_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignUuid('professional_id')
                ->constrained('professional_profiles')
                ->cascadeOnDelete();

            $table->string('provider')->default('simulator');
            $table->string('status')->default('scheduled');

            $table->string('room_name')->unique();
            $table->string('join_url')->nullable();

            $table->string('provider_room_id')->nullable();
            $table->json('provider_metadata')->nullable();

            $table->timestamp('scheduled_start_at')->nullable();
            $table->timestamp('scheduled_end_at')->nullable();

            $table->timestamp('opened_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();

            $table->timestamps();

            $table->index(['client_id']);
            $table->index(['professional_id']);
            $table->index(['status']);
            $table->index(['provider']);
            $table->index(['scheduled_start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_sessions');
    }
};
