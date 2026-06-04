<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_products', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('professional_id')
                ->constrained('professional_profiles')
                ->cascadeOnDelete();

            $table->foreignUuid('service_id')
                ->nullable()
                ->constrained('services')
                ->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            $table->unsignedSmallInteger('sessions_count');

            $table->unsignedBigInteger('price');
            $table->string('currency', 3)->default('UYU');

            $table->unsignedSmallInteger('validity_days')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['professional_id']);
            $table->index(['service_id']);
            $table->index(['is_active']);
            $table->index(['price']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_products');
    }
};
