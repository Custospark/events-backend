<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('domain')->nullable()->unique();
            $table->string('logo_path')->nullable();
            $table->string('status')->default('active')->index();
            $table->decimal('commission_percent', 5, 2)->default(5);
            $table->char('currency', 3)->default('UGX');
            $table->string('timezone')->default('Africa/Kampala');
            $table->json('settings')->nullable();
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
