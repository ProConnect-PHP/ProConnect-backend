<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('package_product_id')
                ->constrained('package_products')
                ->cascadeOnDelete();

            $table->foreignUuid('client_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignUuid('professional_id')
                ->constrained('professional_profiles')
                ->cascadeOnDelete();

            $table->foreignUuid('service_id')
                ->nullable()
                ->constrained('services')
                ->nullOnDelete();

            $table->string('status')->default('active');

            $table->unsignedSmallInteger('total_sessions');
            $table->unsignedSmallInteger('used_sessions')->default(0);

            $table->unsignedBigInteger('price_snapshot');
            $table->string('currency', 3)->default('UYU');

            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('depleted_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['client_id']);
            $table->index(['professional_id']);
            $table->index(['service_id']);
            $table->index(['status']);
            $table->index(['expires_at']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignUuid('client_package_id')
                ->nullable()
                ->after('client_id')
                ->constrained('client_packages')
                ->nullOnDelete();

            $table->index(['client_package_id']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_package_id');
        });

        Schema::dropIfExists('client_packages');
    }
};
