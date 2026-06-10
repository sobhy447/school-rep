<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الموازنات التقديرية: مبلغ مُقدَّر لكل حساب في سنة مالية (للمقارنة بالفعلي).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->unsignedBigInteger('account_id');
            $table->decimal('amount', 15, 3)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'fiscal_year_id', 'account_id'], 'budget_unique');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_years')->cascadeOnDelete();
            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
