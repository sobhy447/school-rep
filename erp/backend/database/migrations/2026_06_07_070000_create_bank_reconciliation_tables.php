<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المرحلة 7 — التسويات البنكية (طبقة مطابقة: تأشير الحركات المُسوّاة مقابل كشف البنك).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('account_id');         // الحساب البنكي
            $table->date('statement_date');
            $table->decimal('statement_balance', 15, 3);      // رصيد كشف البنك
            $table->enum('status', ['DRAFT', 'COMPLETED'])->default('DRAFT');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'account_id']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('account_id')->references('id')->on('accounts');
        });

        Schema::create('bank_reconciliation_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('reconciliation_id');
            $table->unsignedBigInteger('journal_line_id');
            $table->boolean('is_cleared')->default(false);
            $table->timestamps();
            $table->unique(['reconciliation_id', 'journal_line_id'], 'brl_unique');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('reconciliation_id')->references('id')->on('bank_reconciliations')->cascadeOnDelete();
            $table->foreign('journal_line_id')->references('id')->on('journal_lines')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_lines');
        Schema::dropIfExists('bank_reconciliations');
    }
};
