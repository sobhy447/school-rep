<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المرحلة 15 — إدارة الشيكات (واردة/صادرة) بدورة محاسبية (تسجيل ➜ تحصيل/ارتداد).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cheques', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->enum('type', ['INCOMING', 'OUTGOING']);
            $table->string('cheque_number');
            $table->string('bank_name')->nullable();
            $table->decimal('amount', 15, 3);
            $table->date('issue_date');
            $table->date('due_date');
            $table->enum('status', ['PENDING', 'CLEARED', 'BOUNCED', 'CANCELLED'])->default('PENDING');
            $table->unsignedBigInteger('party_account_id');     // العميل/المورد
            $table->unsignedBigInteger('bank_account_id');      // البنك عند التحصيل/الصرف
            $table->unsignedBigInteger('register_entry_id')->nullable();
            $table->unsignedBigInteger('clear_entry_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status', 'due_date']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_years');
            $table->foreign('party_account_id')->references('id')->on('accounts');
            $table->foreign('bank_account_id')->references('id')->on('accounts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheques');
    }
};
