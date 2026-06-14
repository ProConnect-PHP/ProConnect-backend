<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->json('metadata')
                ->nullable()
                ->after('action_route');

            $table->timestamp('archived_at')
                ->nullable()
                ->after('read_at');

            $table->index(
                ['recipient_id', 'archived_at', 'created_at'],
                'notifications_recipient_archived_created_at_index'
            );

            $table->index(
                ['recipient_id', 'type'],
                'notifications_recipient_type_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex(
                'notifications_recipient_archived_created_at_index'
            );
            $table->dropIndex('notifications_recipient_type_index');
            $table->dropColumn(['metadata', 'archived_at']);
        });
    }
};
