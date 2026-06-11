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
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('recipient_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('type');

            $table->string('title');

            $table->text('message');

            $table->string('action_route')->nullable();

            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index(
                ['recipient_id', 'created_at'],
                'notifications_recipient_created_at_index'
            );

            $table->index(
                ['recipient_id', 'read_at'],
                'notifications_recipient_read_at_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
