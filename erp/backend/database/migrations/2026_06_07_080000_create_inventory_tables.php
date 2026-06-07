<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المرحلة 8 — المخزون (مخازن + أصناف + حركات مخزنية بتقييم متوسط التكلفة).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('code');
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('code');
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('unit', 20)->default('قطعة');
            $table->string('barcode')->nullable();
            $table->decimal('purchase_price', 15, 3)->default(0);
            $table->decimal('sale_price', 15, 3)->default(0);
            $table->decimal('average_cost', 15, 3)->default(0);   // متوسط التكلفة الجاري
            $table->decimal('reorder_level', 15, 3)->default(0);  // حد إعادة الطلب
            $table->enum('cost_method', ['AVERAGE'])->default('AVERAGE');
            // الحسابات المحاسبية للربط التلقائي (مبيعات/مشتريات)
            $table->unsignedBigInteger('inventory_account_id')->nullable();
            $table->unsignedBigInteger('cogs_account_id')->nullable();       // تكلفة البضاعة المباعة
            $table->unsignedBigInteger('revenue_account_id')->nullable();    // إيراد المبيعات
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('inventory_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('cogs_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('revenue_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('tax_rate_id')->references('id')->on('tax_rates')->nullOnDelete();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->enum('movement_type', ['IN', 'OUT', 'ADJUST', 'TRANSFER_IN', 'TRANSFER_OUT']);
            $table->decimal('quantity', 15, 3);          // كمية موجبة دائماً
            $table->decimal('unit_cost', 15, 3)->default(0);
            $table->decimal('total_cost', 15, 3)->default(0);
            $table->date('movement_date');
            $table->string('reference_type')->nullable();  // PURCHASE / SALE / MANUAL ...
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'item_id', 'warehouse_id']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('item_id')->references('id')->on('inventory_items')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('warehouses');
    }
};
