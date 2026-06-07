<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المرحلة 13 — نظام التذكيرات/التنبيهات.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('type');             // REORDER / FISCAL_YEAR / CONTRACT / CHEQUE / CUSTOM ...
            $table->string('title');
            $table->date('due_date');
            $table->enum('status', ['PENDING', 'DONE', 'DISMISSED'])->default('PENDING');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('auto')->default(false);   // مولّد تلقائياً؟
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status', 'due_date']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
