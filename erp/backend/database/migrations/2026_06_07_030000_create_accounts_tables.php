<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المرحلة 2 — دليل الحسابات الشجري + فئات الحسابات (تعدد الفئات).
 */
return new class extends Migration
{
    public function up(): void
    {
        // فئات الحسابات: مجموعات تصنيف مرنة (نوع العميل، التابع لمن، التصنيف الفرعي للقائمة...)
        Schema::create('account_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            // اسم المجموعة (البُعد): مثل customer_type / affiliation / statement_group
            $table->string('group');
            $table->string('code');
            $table->string('name');
            $table->string('name_en')->nullable();
            // اختياري: تقتصر الفئة على نوع حساب معيّن (ASSET/.../أو null = الكل)
            $table->string('applies_to_type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'group', 'code']);
            $table->index(['company_id', 'group']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        // دليل الحسابات (شجرة غير محدودة المستويات)
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('code');
            $table->string('name');
            $table->string('name_en')->nullable();
            // النوع المحاسبي (الطبيعة والقائمة المالية تُشتقّان منه)
            $table->enum('type', ['ASSET', 'LIABILITY', 'EQUITY', 'REVENUE', 'EXPENSE']);
            // يقبل حركة؟ (الأب تصنيف فقط؛ يُحوَّل تلقائياً لـ false إذا صار له أبناء)
            $table->boolean('accepts_entries')->default(true);
            // الأرصدة الافتتاحية (على حسابات الأوراق فقط)
            $table->decimal('opening_balance', 15, 3)->default(0);
            $table->enum('opening_balance_type', ['DEBIT', 'CREDIT'])->default('DEBIT');
            // ارتباطات اختيارية
            $table->string('currency_code', 3)->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->boolean('cost_center_required')->default(false);
            // بيانات تفصيلية مرنة حسب نوع الحساب (هاتف/رقم مدني/نوع تعاقد للعميل... إلخ)
            $table->json('meta')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'type']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('cost_center_id')->references('id')->on('cost_centers')->nullOnDelete();
            $table->foreign('tax_rate_id')->references('id')->on('tax_rates')->nullOnDelete();
        });

        // تعدد الفئات: حساب ↔ عدة فئات (من مجموعات مختلفة)
        Schema::create('account_category_account', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('account_category_id');
            $table->primary(['account_id', 'account_category_id'], 'aca_pk');
            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
            $table->foreign('account_category_id')->references('id')->on('account_categories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_category_account');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('account_categories');
    }
};
