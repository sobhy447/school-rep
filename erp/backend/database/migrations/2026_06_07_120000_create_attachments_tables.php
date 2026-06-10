<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المرحلة 12 — المرفقات (PDF) للسندات/القيود + ربط كل سطر بأرقام صفحاته في الملف.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('journal_entry_id');
            $table->string('file_name');          // الاسم الأصلي
            $table->string('stored_name');         // الاسم المُخزَّن (مولّد)
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('file_hash', 64)->nullable();
            $table->integer('total_pages')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
            $table->index('journal_entry_id');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->cascadeOnDelete();
        });

        // ربط سطر القيد بأرقام صفحاته في ملف الـ PDF
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->unsignedInteger('page_from')->nullable()->after('counterparty_name');
            $table->unsignedInteger('page_to')->nullable()->after('page_from');
        });
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropColumn(['page_from', 'page_to']);
        });
        Schema::dropIfExists('document_attachments');
    }
};
