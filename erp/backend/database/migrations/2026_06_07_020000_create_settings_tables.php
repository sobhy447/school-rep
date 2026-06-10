<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جداول المرحلة 1 — الإعدادات الأساسية.
 * كل جدول يحمل company_id (عزل المستأجر) + رمز فريد داخل الشركة.
 */
return new class extends Migration
{
    public function up(): void
    {
        // الفروع
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('code');
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->boolean('is_main')->default(false);
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        // السنوات المالية
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['OPEN', 'CLOSED'])->default('OPEN');
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'name']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        // العملات
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('code', 3);
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('symbol', 8)->nullable();
            $table->boolean('is_base')->default(false);
            $table->decimal('exchange_rate', 15, 6)->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        // مراكز التكلفة
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code');
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('cost_centers')->nullOnDelete();
        });

        // أنواع السندات
        Schema::create('voucher_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('code');
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->enum('direction', ['RECEIPT', 'PAYMENT', 'TRANSFER']);
            $table->string('prefix', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        // الضرائب (معطّلة افتراضياً)
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('code');
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->decimal('rate', 8, 4)->default(0);
            $table->enum('type', ['VAT', 'WITHHOLDING', 'OTHER'])->default('VAT');
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('voucher_types');
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('branches');
    }
};
