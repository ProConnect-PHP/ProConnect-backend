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
        Schema::create('availability_exceptions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('service_id')
                ->constrained('services')
                ->cascadeOnDelete();

            $table->date('exception_date');

            $table->boolean('is_unavailable')->default(true);

            $table->time('alt_start')->nullable();
            $table->time('alt_end')->nullable();

            $table->string('reason')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->unique(['service_id', 'exception_date']);
        });
        }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('availability_exceptions');
    }
};
