<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المرحلة 4 — العملاء/الموردون + العهد + الأمانات/السداد + الإقفال.
 */
return new class extends Migration
{
    public function up(): void
    {
        // إعدادات الشركة (مفتاح/قيمة): حساب العهدة، حساب النتيجة، الأرباح المحتجزة...
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('key');
            $table->string('value')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'key']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        // نوع الطرف على الحساب (عميل/مورد) — العملاء/الموردون حسابات في الشجرة
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('party_type')->nullable()->after('is_cash_or_bank'); // CUSTOMER / VENDOR / null
        });

        // بنود الصرف للعهد (إعدادات) مع حدود التكرار
        Schema::create('petty_cash_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('code');
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->decimal('default_amount', 15, 3)->nullable();
            $table->integer('max_repeat')->nullable();        // الحد الأقصى لعدد المرات
            $table->integer('forbidden_months')->nullable();  // الفترة الممنوعة (شهور)
            $table->boolean('is_permanent')->default(false);  // «دائم»: لا يتكرر نهائياً لنفس المركز
            $table->unsignedBigInteger('expense_account_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('expense_account_id')->references('id')->on('accounts')->nullOnDelete();
        });

        // كشوف العهد (رأس)
        Schema::create('expense_claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('claim_number');
            $table->unsignedBigInteger('employee_id')->nullable(); // مستخدم النظام كموظف
            $table->date('claim_date');
            $table->text('description')->nullable();
            $table->decimal('total_amount', 15, 3)->default(0);
            $table->enum('status', ['DRAFT', 'SUBMITTED', 'APPROVED', 'CONVERTED'])->default('DRAFT');
            $table->unsignedBigInteger('journal_entry_id')->nullable(); // القيد/السند الناتج
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('converted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'claim_number']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });

        // سطور كشوف العهد
        Schema::create('expense_claim_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('claim_id');
            $table->integer('line_number');
            $table->unsignedBigInteger('petty_cash_item_id');
            $table->decimal('amount', 15, 3);
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('cost_center_extra_id')->nullable();
            $table->unsignedBigInteger('expense_account_id')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('claim_id')->references('id')->on('expense_claims')->cascadeOnDelete();
            $table->foreign('petty_cash_item_id')->references('id')->on('petty_cash_items');
            $table->foreign('cost_center_id')->references('id')->on('cost_centers')->nullOnDelete();
            $table->foreign('cost_center_extra_id')->references('id')->on('cost_centers')->nullOnDelete();
            $table->foreign('expense_account_id')->references('id')->on('accounts')->nullOnDelete();
        });

        // مخصصات السداد (طبقة مطابقة منفصلة عن المحاسبة)
        // تربط حركة أمانة (دائن على العميل) باستحقاق (مدين على العميل) بمبلغ مخصّص.
        Schema::create('settlement_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('customer_account_id');
            $table->unsignedBigInteger('trust_line_id');        // سطر دائن (أمانة)
            $table->unsignedBigInteger('entitlement_line_id');  // سطر مدين (استحقاق)
            $table->decimal('amount', 15, 3);
            $table->date('allocation_date');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'customer_account_id']);
            $table->index('trust_line_id');
            $table->index('entitlement_line_id');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('customer_account_id')->references('id')->on('accounts');
            $table->foreign('trust_line_id')->references('id')->on('journal_lines')->cascadeOnDelete();
            $table->foreign('entitlement_line_id')->references('id')->on('journal_lines')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_allocations');
        Schema::dropIfExists('expense_claim_lines');
        Schema::dropIfExists('expense_claims');
        Schema::dropIfExists('petty_cash_items');
        Schema::table('accounts', fn (Blueprint $t) => $t->dropColumn('party_type'));
        Schema::dropIfExists('company_settings');
    }
};
