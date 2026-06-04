<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('payment_intent_id')
                ->unique()
                ->constrained('payment_intents')
                ->cascadeOnDelete();

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
            $table->string('status')->default('succeeded');

            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('UYU');

            $table->string('provider_reference')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            $table->text('failure_reason')->nullable();

            $table->timestamps();

            $table->index(['client_id']);
            $table->index(['professional_id']);
            $table->index(['status']);
            $table->index(['paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
