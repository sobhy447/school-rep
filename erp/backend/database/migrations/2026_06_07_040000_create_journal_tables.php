<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المرحلة 3 — القيود اليومية والسندات (السجل المحاسبي الموحّد).
 * - journal_entries / journal_lines هما مصدر الحقيقة الوحيد.
 * - السندات (قبض/صرف/تحويل) قيودٌ من أنواع خاصة (لا جداول منفصلة) ➜ ربط محاسبي تلقائي.
 * - إضافات: علم النقدية/البنك على الحسابات، وربط مركز التكلفة الإضافي بحساب + اسم خصم.
 */
return new class extends Migration
{
    public function up(): void
    {
        // رأس القيد
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('voucher_type_id')->nullable();
            $table->enum('type', ['MANUAL', 'RECEIPT', 'PAYMENT', 'TRANSFER', 'OPENING', 'CLOSING']);
            $table->string('entry_number');
            $table->date('entry_date');
            $table->string('currency_code', 3)->nullable()->default('KWD');
            $table->decimal('exchange_rate', 15, 6)->default(1);
            $table->text('description')->nullable();
            $table->string('party_name')->nullable();   // اسم المصروف له / المقبوض منه
            $table->string('reference_number')->nullable();
            $table->enum('status', ['DRAFT', 'APPROVED', 'POSTED', 'LOCKED'])->default('DRAFT');
            $table->decimal('total_debit', 15, 3)->default(0);
            $table->decimal('total_credit', 15, 3)->default(0);
            $table->unsignedBigInteger('reversed_entry_id')->nullable();  // يشير للقيد الأصلي عند العكس
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'fiscal_year_id', 'entry_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'entry_date']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_years');
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('voucher_type_id')->references('id')->on('voucher_types')->nullOnDelete();
        });

        // أسطر القيد
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('journal_entry_id');
            $table->integer('line_number');
            $table->unsignedBigInteger('account_id')->nullable();
            $table->decimal('debit', 15, 3)->default(0);
            $table->decimal('credit', 15, 3)->default(0);
            $table->unsignedBigInteger('cost_center_id')->nullable();        // أساسي
            $table->unsignedBigInteger('cost_center_extra_id')->nullable();  // إضافي
            $table->string('reference_number')->nullable();
            $table->string('description')->nullable();
            $table->string('counterparty_name')->nullable();                 // اسم الخصم (من مركز التكلفة الإضافي)
            $table->boolean('is_main')->default(false);                      // الجانب الرئيسي في السند
            $table->timestamps();
            $table->index('journal_entry_id');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->cascadeOnDelete();
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('cost_center_id')->references('id')->on('cost_centers')->nullOnDelete();
            $table->foreign('cost_center_extra_id')->references('id')->on('cost_centers')->nullOnDelete();
        });

        // علم: هل الحساب نقدية/بنك؟ (للتحقق في السندات)
        Schema::table('accounts', function (Blueprint $table) {
            $table->boolean('is_cash_or_bank')->default(false)->after('type');
        });

        // ربط مركز التكلفة الإضافي بحساب + اسم الموكل/الخصم
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->unsignedBigInteger('linked_account_id')->nullable()->after('parent_id');
            $table->string('client_name')->nullable();        // اسم الموكل
            $table->string('counterparty_name')->nullable();  // اسم الخصم
        });
    }

    public function down(): void
    {
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->dropColumn(['linked_account_id', 'client_name', 'counterparty_name']);
        });
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('is_cash_or_bank');
        });
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
    }
};
