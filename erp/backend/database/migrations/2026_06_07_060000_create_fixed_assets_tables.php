<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المرحلة 6 — الأصول الثابتة والإهلاك.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('code');
            $table->string('name');
            $table->string('name_en')->nullable();
            // الحسابات المحاسبية المرتبطة
            $table->unsignedBigInteger('asset_account_id');                 // حساب الأصل
            $table->unsignedBigInteger('accumulated_depreciation_account_id'); // مجمع الإهلاك
            $table->unsignedBigInteger('depreciation_expense_account_id');  // مصروف الإهلاك
            $table->date('acquisition_date');
            $table->decimal('cost', 15, 3);
            $table->decimal('salvage_value', 15, 3)->default(0);           // القيمة التخريدية
            $table->integer('useful_life_months');                          // العمر الإنتاجي بالشهور
            $table->enum('method', ['STRAIGHT_LINE', 'DECLINING_BALANCE'])->default('STRAIGHT_LINE');
            $table->decimal('declining_rate', 8, 4)->nullable();           // نسبة سنوية للمتناقص
            $table->decimal('accumulated_depreciation', 15, 3)->default(0);
            $table->enum('status', ['ACTIVE', 'DISPOSED'])->default('ACTIVE');
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_proceeds', 15, 3)->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('asset_account_id')->references('id')->on('accounts');
            $table->foreign('accumulated_depreciation_account_id')->references('id')->on('accounts');
            $table->foreign('depreciation_expense_account_id')->references('id')->on('accounts');
            $table->foreign('cost_center_id')->references('id')->on('cost_centers')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });

        // سجلّ تشغيلات الإهلاك (يمنع التكرار لنفس الفترة)
        Schema::create('depreciation_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('fixed_asset_id');
            $table->date('period_date');
            $table->decimal('amount', 15, 3);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'fixed_asset_id', 'period_date'], 'dep_unique_period');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('fixed_asset_id')->references('id')->on('fixed_assets')->cascadeOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_entries');
        Schema::dropIfExists('fixed_assets');
    }
};
