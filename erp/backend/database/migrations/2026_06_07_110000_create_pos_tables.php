<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المرحلة 11 — نقاط البيع (POS): بيع نقدي سريع يُرحَّل فوراً (مخزون + قيد نقدي مباشر).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('cash_account_id');     // الصندوق/البنك المُحصَّل فيه
            $table->string('sale_number');
            $table->date('sale_date');
            $table->decimal('subtotal', 15, 3)->default(0);
            $table->decimal('tax_amount', 15, 3)->default(0);
            $table->decimal('total', 15, 3)->default(0);
            $table->decimal('paid', 15, 3)->default(0);
            $table->decimal('change_amount', 15, 3)->default(0);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'sale_number']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_years');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->foreign('cash_account_id')->references('id')->on('accounts');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });

        Schema::create('pos_sale_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('pos_sale_id');
            $table->unsignedBigInteger('item_id');
            $table->integer('line_number');
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 3);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 15, 3)->default(0);
            $table->decimal('line_total', 15, 3);
            $table->decimal('cost_amount', 15, 3)->default(0);
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('pos_sale_id')->references('id')->on('pos_sales')->cascadeOnDelete();
            $table->foreign('item_id')->references('id')->on('inventory_items');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sale_lines');
        Schema::dropIfExists('pos_sales');
    }
};
