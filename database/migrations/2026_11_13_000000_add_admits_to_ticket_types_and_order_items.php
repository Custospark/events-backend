<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->unsignedSmallInteger('admits')->default(1);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('admits')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('admits');
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn('admits');
        });
    }
};
