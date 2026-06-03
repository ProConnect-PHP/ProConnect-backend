<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('booking_id')
                ->unique()
                ->constrained('bookings')
                ->cascadeOnDelete();

            $table->foreignUuid('service_id')
                ->constrained('services')
                ->cascadeOnDelete();

            $table->foreignUuid('professional_id')
                ->constrained('professional_profiles')
                ->cascadeOnDelete();

            $table->foreignUuid('client_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('comment_deleted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['service_id', 'created_at']);
            $table->index(['professional_id', 'created_at']);
            $table->index(['client_id']);
            $table->index(['rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
