<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دعم تعدد الشركات: مستخدم خارق (super) يمكنه التبديل بين الشركات وإنشاء شركات.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super')->default(false)->after('role_id');
            $table->unsignedBigInteger('active_company_id')->nullable()->after('is_super');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_super', 'active_company_id']);
        });
    }
};
