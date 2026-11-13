<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('events')
            ->where('service_fee_percent', 0)
            ->update(['service_fee_percent' => 5]);

        Schema::table('events', function (Blueprint $table) {
            $table->decimal('service_fee_percent', 5, 2)->default(5)->change();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->decimal('service_fee_percent', 5, 2)->default(0)->change();
        });
    }
};
