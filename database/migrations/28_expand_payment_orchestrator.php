<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_intents', function (Blueprint $table): void {
            $table->dropForeign(['booking_id']);
        });

        Schema::table('payment_intents', function (Blueprint $table): void {
            $table->uuid('booking_id')->nullable()->change();
            $table->foreign('booking_id')
                ->references('id')
                ->on('bookings')
                ->cascadeOnDelete();

            $table->foreignUuid('package_product_id')
                ->nullable()
                ->after('booking_id')
                ->constrained('package_products')
                ->cascadeOnDelete();
            $table->string('payable_type', 50)->nullable()->after('package_product_id');
            $table->uuid('payable_id')->nullable()->after('payable_type');
            $table->text('checkout_url')->nullable()->after('provider_reference');

            $table->index(['package_product_id']);
            $table->index(['payable_type', 'payable_id']);
        });

        DB::table('payment_intents')
            ->whereNotNull('booking_id')
            ->update([
                'payable_type' => 'booking',
                'payable_id' => DB::raw('booking_id'),
            ]);

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['booking_id']);
            $table->dropUnique(['booking_id']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->uuid('booking_id')->nullable()->change();
            $table->foreign('booking_id')
                ->references('id')
                ->on('bookings')
                ->cascadeOnDelete();

            $table->foreignUuid('package_product_id')
                ->nullable()
                ->after('booking_id')
                ->constrained('package_products')
                ->cascadeOnDelete();
            $table->foreignUuid('client_package_id')
                ->nullable()
                ->unique()
                ->after('package_product_id')
                ->constrained('client_packages')
                ->nullOnDelete();
            $table->string('provider_payment_id')->nullable()->after('provider_reference');
            $table->string('raw_provider_status')->nullable()->after('provider_payment_id');

            $table->index(['package_product_id']);
            $table->index(['provider', 'provider_payment_id']);
        });

        Schema::create('payment_webhook_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider', 50);
            $table->string('provider_event_id')->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->string('event_type')->nullable();
            $table->string('resource_type')->nullable();
            $table->string('resource_id')->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->string('status', 50)->default('received');
            $table->json('payload')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_event_id']);
            $table->index(['provider', 'event_type']);
            $table->index(['provider', 'resource_id']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['package_product_id']);
            $table->dropForeign(['client_package_id']);
            $table->dropIndex(['package_product_id']);
            $table->dropIndex(['provider', 'provider_payment_id']);
            $table->dropUnique(['client_package_id']);
            $table->dropColumn([
                'package_product_id',
                'client_package_id',
                'provider_payment_id',
                'raw_provider_status',
            ]);
        });

        Schema::table('payment_intents', function (Blueprint $table): void {
            $table->dropForeign(['package_product_id']);
            $table->dropIndex(['package_product_id']);
            $table->dropIndex(['payable_type', 'payable_id']);
            $table->dropColumn([
                'package_product_id',
                'payable_type',
                'payable_id',
                'checkout_url',
            ]);
        });
    }
};
