<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('processing_fee_bearer', 20)->default('customer');
            $table->dropColumn('service_fee_percent');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('service_fee', 'processing_fee');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('processing_fee_bearer', 20)->default('customer');
            $table->decimal('buyer_processing_fee', 12, 2)->default(0);
            $table->decimal('gateway_fee_percent', 5, 2)->default(0);
            $table->decimal('custospark_fee_percent', 5, 2)->default(0);
            $table->decimal('gateway_fee', 12, 2)->default(0);
            $table->decimal('custospark_fee', 12, 2)->default(0);
        });

        DB::table('orders')->update([
            'buyer_processing_fee' => DB::raw('processing_fee'),
        ]);

        Schema::table('order_items', function (Blueprint $table) {
            $table->renameColumn('service_fee', 'processing_fee');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('organiser_name')->nullable();
            $table->string('event_title')->nullable();
            $table->string('transaction_label')->nullable();
            $table->string('fee_bearer_code', 20)->nullable();
            $table->string('reconciliation_code', 40)->nullable()->index();
            $table->json('reconciliation')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'organiser_name',
                'event_title',
                'transaction_label',
                'fee_bearer_code',
                'reconciliation_code',
                'reconciliation',
            ]);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->renameColumn('processing_fee', 'service_fee');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'processing_fee_bearer',
                'buyer_processing_fee',
                'gateway_fee_percent',
                'custospark_fee_percent',
                'gateway_fee',
                'custospark_fee',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('processing_fee', 'service_fee');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->decimal('service_fee_percent', 5, 2)->default(5);
            $table->dropColumn('processing_fee_bearer');
        });
    }
};
