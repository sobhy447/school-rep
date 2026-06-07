<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المرحلة 10 — الموارد البشرية والرواتب.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('code');
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('civil_id')->nullable();
            $table->date('hire_date')->nullable();
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->decimal('basic_salary', 15, 3)->default(0);
            $table->string('bank_account')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('code');
            $table->string('name');
            $table->enum('type', ['EARNING', 'DEDUCTION']);
            $table->decimal('default_amount', 15, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('component_id');
            $table->decimal('amount', 15, 3);
            $table->timestamps();
            $table->unique(['employee_id', 'component_id'], 'esc_unique');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('component_id')->references('id')->on('salary_components')->cascadeOnDelete();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->integer('period_year');
            $table->integer('period_month');
            $table->date('run_date');
            $table->enum('status', ['DRAFT', 'POSTED'])->default('DRAFT');
            $table->decimal('total_earnings', 15, 3)->default(0);
            $table->decimal('total_deductions', 15, 3)->default(0);
            $table->decimal('net_total', 15, 3)->default(0);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'period_year', 'period_month'], 'payroll_period_unique');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_years');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });

        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('payroll_run_id');
            $table->unsignedBigInteger('employee_id');
            $table->decimal('basic', 15, 3)->default(0);
            $table->decimal('earnings', 15, 3)->default(0);
            $table->decimal('deductions', 15, 3)->default(0);
            $table->decimal('net', 15, 3)->default(0);
            $table->json('breakdown')->nullable();
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('payroll_run_id')->references('id')->on('payroll_runs')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_lines');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employee_salary_components');
        Schema::dropIfExists('salary_components');
        Schema::dropIfExists('employees');
    }
};
