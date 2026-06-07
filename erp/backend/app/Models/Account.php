<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\AccountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'parent_id', 'code', 'name', 'name_en', 'type',
        'accepts_entries', 'opening_balance', 'opening_balance_type',
        'currency_code', 'cost_center_id', 'tax_rate_id', 'cost_center_required',
        'meta', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'accepts_entries' => 'boolean',
            'cost_center_required' => 'boolean',
            'is_active' => 'boolean',
            'opening_balance' => 'decimal:3',
            'meta' => 'array',
        ];
    }

    // الحقول المشتقّة تظهر في الـ JSON تلقائياً
    protected $appends = ['normal_balance', 'statement', 'is_leaf'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(AccountCategory::class, 'account_category_account');
    }

    /** الطبيعة المدينة/الدائنة (مشتقّة من النوع). */
    public function getNormalBalanceAttribute(): string
    {
        return AccountType::normalBalance($this->type);
    }

    /** القائمة المالية (مشتقّة من النوع). */
    public function getStatementAttribute(): string
    {
        return AccountType::statement($this->type);
    }

    /** حساب ورقي (لا أبناء له) ➜ يقبل القيود. */
    public function getIsLeafAttribute(): bool
    {
        // children_count محمّل مسبقاً عند الإمكان لتجنّب استعلامات إضافية
        $count = $this->children_count ?? $this->children()->count();
        return (int) $count === 0;
    }

    /** الرصيد الافتتاحي بإشارة موجبة/سالبة حسب طبيعة الحساب. */
    public function signedOpeningBalance(): float
    {
        $debit = $this->opening_balance_type === 'DEBIT' ? (float) $this->opening_balance : 0.0;
        $credit = $this->opening_balance_type === 'CREDIT' ? (float) $this->opening_balance : 0.0;
        return AccountType::signedBalance($this->type, $debit, $credit);
    }
}
