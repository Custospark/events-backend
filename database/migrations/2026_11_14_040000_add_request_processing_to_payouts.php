<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->foreignId('requested_by')->nullable()->after('tenant_id')->constrained('users')->nullOnDelete();
            $table->string('initiation', 20)->default('platform')->after('requested_by');
            $table->string('request_priority', 10)->default('normal')->after('initiation');
            $table->string('requested_note', 500)->nullable()->after('request_priority');
            $table->timestamp('requested_at')->nullable()->after('requested_note');
            $table->timestamp('processing_started_at')->nullable()->after('requested_at');
            $table->timestamp('completed_at')->nullable()->after('processing_started_at');
            $table->string('decline_reason', 500)->nullable()->after('completed_at');

            $table->index(['status', 'request_priority']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropForeign(['requested_by']);
            $table->dropIndex(['status', 'request_priority']);
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropColumn([
                'requested_by',
                'initiation',
                'request_priority',
                'requested_note',
                'requested_at',
                'processing_started_at',
                'completed_at',
                'decline_reason',
            ]);
        });
    }
};
