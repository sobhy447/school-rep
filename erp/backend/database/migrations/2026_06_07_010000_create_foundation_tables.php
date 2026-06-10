<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جداول التأسيس: الشركات (المستأجرون) + الصلاحيات + الأدوار + ربط الأدوار بالصلاحيات.
 */
return new class extends Migration
{
    public function up(): void
    {
        // الشركات = المستأجرون (Multi-Tenant على قاعدة واحدة)
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('currency_code', 3)->default('KWD');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // الصلاحيات (مرجعية عالمية، مفتاح مثل accounts.view)
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('module')->index();
            $table->string('label_ar');
            $table->string('label_en');
            $table->timestamps();
        });

        // الأدوار (نظامية company_id=null، أو خاصة بشركة)
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('slug');
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->unique(['company_id', 'slug']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        // ربط الأدوار بالصلاحيات
        Schema::create('role_permission', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
            $table->primary(['role_id', 'permission_id']);
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('companies');
    }
};
