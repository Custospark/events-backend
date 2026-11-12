<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('role_id')->nullable()->after('tenant_id')->constrained('roles')->nullOnDelete();
            $table->string('phone', 40)->nullable()->after('email');
            $table->string('status')->default('active')->after('password')->index();
            $table->boolean('is_platform_admin')->default(false)->after('status')->index();
            $table->timestamp('last_login_at')->nullable()->after('is_platform_admin');
            $table->json('preferences')->nullable()->after('last_login_at');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn([
                'phone',
                'status',
                'is_platform_admin',
                'last_login_at',
                'preferences',
                'deleted_at',
            ]);
        });
    }
};
