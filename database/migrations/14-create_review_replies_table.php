<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_replies', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('review_id')
                ->unique()
                ->constrained('reviews')
                ->cascadeOnDelete();

            $table->foreignUuid('professional_id')
                ->constrained('professional_profiles')
                ->cascadeOnDelete();

            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['professional_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_replies');
    }
};
