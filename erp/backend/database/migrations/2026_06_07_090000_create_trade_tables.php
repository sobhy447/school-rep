<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المرحلة 9 — المشتريات والمبيعات (فواتير تُنشئ حركة مخزون + قيد محاسبي تلقائي).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['purchase' => 'vendor_account_id', 'sales' => 'customer_account_id'] as $kind => $partyCol) {
            Schema::create("{$kind}_invoices", function (Blueprint $table) use ($partyCol) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('fiscal_year_id');
                $table->unsignedBigInteger($partyCol);          // حساب المورد/العميل (طرف في الشجرة)
                $table->unsignedBigInteger('warehouse_id');
                $table->string('invoice_number');
                $table->date('invoice_date');
                $table->decimal('subtotal', 15, 3)->default(0);
                $table->decimal('tax_amount', 15, 3)->default(0);
                $table->decimal('total', 15, 3)->default(0);
                $table->enum('status', ['DRAFT', 'POSTED'])->default('DRAFT');
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('posted_by')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['company_id', 'invoice_number']);
                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->foreign('fiscal_year_id')->references('id')->on('fiscal_years');
                $table->foreign($partyCol)->references('id')->on('accounts');
                $table->foreign('warehouse_id')->references('id')->on('warehouses');
                $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            });

            Schema::create("{$kind}_invoice_lines", function (Blueprint $table) use ($kind) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('invoice_id');
                $table->unsignedBigInteger('item_id');
                $table->integer('line_number');
                $table->decimal('quantity', 15, 3);
                $table->decimal('unit_price', 15, 3);           // تكلفة شراء / سعر بيع
                $table->decimal('tax_rate', 8, 4)->default(0);
                $table->decimal('tax_amount', 15, 3)->default(0);
                $table->decimal('line_total', 15, 3);           // الكمية × السعر (قبل الضريبة)
                $table->decimal('cost_amount', 15, 3)->default(0); // تكلفة البضاعة (للمبيعات)
                $table->timestamps();
                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->foreign('invoice_id')->references('id')->on("{$kind}_invoices")->cascadeOnDelete();
                $table->foreign('item_id')->references('id')->on('inventory_items');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_lines');
        Schema::dropIfExists('sales_invoices');
        Schema::dropIfExists('purchase_invoice_lines');
        Schema::dropIfExists('purchase_invoices');
    }
};
