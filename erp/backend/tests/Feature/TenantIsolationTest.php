<?php

namespace Tests\Feature;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Company;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * يثبت أن BelongsToCompany يعزل الصفوف حسب الشركة الحالية.
 * نستخدم نموذجاً اختبارياً على جدول roles (يحمل company_id) لإثبات الآلية.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function scopedModel(): Model
    {
        return new class extends Model
        {
            use BelongsToCompany;

            protected $table = 'roles';
            public $timestamps = true;
            protected $fillable = ['company_id', 'slug', 'name'];
        };
    }

    public function test_global_scope_isolates_rows_by_company(): void
    {
        $a = Company::create(['code' => 'A', 'name' => 'A', 'currency_code' => 'KWD']);
        $b = Company::create(['code' => 'B', 'name' => 'B', 'currency_code' => 'KWD']);

        $model = $this->scopedModel();

        // بدون سياق: الإنشاء صريح بالشركة
        $model->newQuery()->create(['company_id' => $a->id, 'slug' => 'r1', 'name' => 'دور A']);
        $model->newQuery()->create(['company_id' => $b->id, 'slug' => 'r2', 'name' => 'دور B']);

        // سياق الشركة A: لا نرى إلا صفوف A
        TenantContext::set($a->id);
        $this->assertSame(1, $this->scopedModel()->newQuery()->count());
        $this->assertSame($a->id, (int) $this->scopedModel()->newQuery()->first()->company_id);

        // سياق الشركة B: لا نرى إلا صفوف B
        TenantContext::set($b->id);
        $this->assertSame(1, $this->scopedModel()->newQuery()->count());
        $this->assertSame($b->id, (int) $this->scopedModel()->newQuery()->first()->company_id);
    }

    public function test_company_id_is_set_automatically_on_create(): void
    {
        $a = Company::create(['code' => 'A', 'name' => 'A', 'currency_code' => 'KWD']);
        TenantContext::set($a->id);

        $row = $this->scopedModel()->newQuery()->create(['slug' => 'auto', 'name' => 'تلقائي']);

        $this->assertSame($a->id, (int) $row->company_id);
    }
}
