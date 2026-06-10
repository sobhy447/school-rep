<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المرحلة 16 — مرتجعات المبيعات والمشتريات (تُرحَّل فوراً: حركة مخزون عكسية + قيد تلقائي).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_returns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->enum('type', ['SALES', 'PURCHASE']);
            $table->unsignedBigInteger('party_account_id');     // العميل/المورد
            $table->unsignedBigInteger('warehouse_id');
            $table->string('return_number');
            $table->date('return_date');
            $table->decimal('subtotal', 15, 3)->default(0);
            $table->decimal('tax_amount', 15, 3)->default(0);
            $table->decimal('total', 15, 3)->default(0);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'return_number']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_years');
            $table->foreign('party_account_id')->references('id')->on('accounts');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
        });

        Schema::create('trade_return_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('return_id');
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
            $table->foreign('return_id')->references('id')->on('trade_returns')->cascadeOnDelete();
            $table->foreign('item_id')->references('id')->on('inventory_items');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_return_lines');
        Schema::dropIfExists('trade_returns');
    }
};
