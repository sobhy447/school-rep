<?php

namespace App\Services;

use App\Models\Account;
use App\Support\AccountType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * منطق دليل الحسابات: الشجرة، ترحيل الأرصدة من الأسفل للأعلى، منع الدوائر، الاستيراد.
 */
class AccountService
{
    /** يبني الشجرة الكاملة لشركة المستخدم مع الأرصدة المجمّعة. */
    public function tree(): array
    {
        $accounts = Account::query()->withCount('children')->orderBy('code')->get();
        $balances = $this->rolledUpBalances($accounts);

        $byParent = [];
        foreach ($accounts as $acc) {
            $byParent[$acc->parent_id][] = $acc;
        }

        $build = function ($parentId) use (&$build, $byParent, $balances) {
            $nodes = [];
            foreach ($byParent[$parentId] ?? [] as $acc) {
                $nodes[] = [
                    'id' => $acc->id,
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'name_en' => $acc->name_en,
                    'type' => $acc->type,
                    'normal_balance' => $acc->normal_balance,
                    'statement' => $acc->statement,
                    'is_leaf' => (int) $acc->children_count === 0,
                    'balance' => $balances[$acc->id] ?? 0.0,
                    'children' => $build($acc->id),
                ];
            }
            return $nodes;
        };

        return $build(null);
    }

    /**
     * الرصيد المجمّع لكل حساب = رصيده الافتتاحي + مجموع أرصدة أبنائه (Bottom-up).
     * (في المرحلة 3 ستُضاف حركة القيود المرحّلة.)
     *
     * @param  \Illuminate\Support\Collection<int,Account>  $accounts
     * @return array<int,float>  معرّف الحساب ➜ الرصيد بإشارته
     */
    public function rolledUpBalances($accounts): array
    {
        $own = [];
        $childrenOf = [];
        foreach ($accounts as $acc) {
            $own[$acc->id] = $acc->signedOpeningBalance();
            $childrenOf[$acc->parent_id][] = $acc->id;
        }

        $memo = [];
        $compute = function ($id) use (&$compute, &$memo, $own, $childrenOf) {
            if (isset($memo[$id])) {
                return $memo[$id];
            }
            $sum = $own[$id] ?? 0.0;
            foreach ($childrenOf[$id] ?? [] as $childId) {
                $sum += $compute($childId);
            }
            return $memo[$id] = round($sum, 3);
        };

        $result = [];
        foreach ($accounts as $acc) {
            $result[$acc->id] = $compute($acc->id);
        }
        return $result;
    }

    /** رصيد حساب واحد مجمّعاً. */
    public function balanceOf(Account $account): float
    {
        $accounts = Account::query()->get();
        return $this->rolledUpBalances($accounts)[$account->id] ?? 0.0;
    }

    /** يمنع جعل الحساب أباً لنفسه أو لأحد أحفاده (دائرة). */
    public function assertNoCycle(Account $account, ?int $newParentId): void
    {
        if ($newParentId === null) {
            return;
        }
        if ($newParentId === $account->id) {
            throw ValidationException::withMessages(['parent_id' => ['لا يمكن جعل الحساب أباً لنفسه']]);
        }
        $cursor = Account::query()->find($newParentId);
        while ($cursor) {
            if ($cursor->id === $account->id) {
                throw ValidationException::withMessages(['parent_id' => ['لا يمكن نقل الحساب أسفل أحد أبنائه']]);
            }
            $cursor = $cursor->parent_id ? Account::query()->find($cursor->parent_id) : null;
        }
    }

    /** عند ربط حساب بأب، يصبح الأب تصنيفاً (لا يقبل حركة). */
    public function refreshParentPostable(?int $parentId): void
    {
        if ($parentId) {
            Account::query()->whereKey($parentId)->update(['accepts_entries' => false]);
        }
    }

    /** التحقق من توافق نوع الابن مع نوع الأب. */
    public function assertTypeMatchesParent(string $type, ?int $parentId): void
    {
        if (! $parentId) {
            return;
        }
        $parent = Account::query()->find($parentId);
        if ($parent && $parent->type !== $type) {
            throw ValidationException::withMessages([
                'type' => ["نوع الحساب يجب أن يطابق نوع الحساب الأب ({$parent->type})"],
            ]);
        }
    }

    /**
     * استيراد شجرة حسابات (Excel/قديم) عبر صفوف منسّقة.
     * كل صف: code, name, name_en?, type, parent_code?, opening_balance?, opening_balance_type?
     * - commit=false ➜ معاينة بالأخطاء فقط (Dry run).
     * - يحلّ الأب بالرمز (من الصفوف أو القائمة الحالية)، ويرتّب الإدراج حسب التبعية.
     *
     * @return array{valid:int, errors:array, imported:int}
     */
    public function import(array $rows, bool $commit): array
    {
        $errors = [];
        $codes = [];
        foreach ($rows as $i => $row) {
            $line = $i + 1;
            $code = trim((string) ($row['code'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $type = strtoupper(trim((string) ($row['type'] ?? '')));

            if ($code === '') {
                $errors[] = ['line' => $line, 'message' => 'الرمز مطلوب'];
            } elseif (isset($codes[$code])) {
                $errors[] = ['line' => $line, 'message' => "الرمز مكرّر داخل الملف: {$code}"];
            } else {
                $codes[$code] = $line;
            }
            if ($name === '') {
                $errors[] = ['line' => $line, 'message' => 'الاسم مطلوب'];
            }
            if (! AccountType::isValid($type)) {
                $errors[] = ['line' => $line, 'message' => "نوع غير صالح: {$type}"];
            }
            $obt = strtoupper(trim((string) ($row['opening_balance_type'] ?? 'DEBIT')));
            if (! in_array($obt, ['DEBIT', 'CREDIT'], true)) {
                $errors[] = ['line' => $line, 'message' => 'نوع الرصيد الافتتاحي يجب DEBIT أو CREDIT'];
            }
        }

        // تحقّق وجود الأب (في الملف أو في قاعدة البيانات الحالية)
        $existingCodes = Account::query()->pluck('code')->flip();
        foreach ($rows as $i => $row) {
            $parentCode = trim((string) ($row['parent_code'] ?? ''));
            if ($parentCode !== '' && ! isset($codes[$parentCode]) && ! isset($existingCodes[$parentCode])) {
                $errors[] = ['line' => $i + 1, 'message' => "الحساب الأب غير موجود: {$parentCode}"];
            }
        }

        if (! empty($errors) || ! $commit) {
            return ['valid' => count($rows) - count($errors), 'errors' => $errors, 'imported' => 0];
        }

        // الإدراج مع حلّ الآباء بترتيب التبعية
        $imported = DB::transaction(function () use ($rows) {
            $pending = $rows;
            $createdByCode = [];
            // خرائط الأب الموجودة مسبقاً
            $count = 0;
            $guard = 0;
            while (! empty($pending) && $guard++ < count($rows) + 5) {
                foreach ($pending as $key => $row) {
                    $parentCode = trim((string) ($row['parent_code'] ?? ''));
                    $parentId = null;
                    if ($parentCode !== '') {
                        $parentId = $createdByCode[$parentCode]
                            ?? Account::query()->where('code', $parentCode)->value('id');
                        if (! $parentId) {
                            continue; // الأب لسه مش متعمل، نأجّل الصف
                        }
                    }
                    $acc = Account::query()->create([
                        'code' => trim((string) $row['code']),
                        'name' => trim((string) $row['name']),
                        'name_en' => $row['name_en'] ?? null,
                        'type' => strtoupper(trim((string) $row['type'])),
                        'parent_id' => $parentId,
                        'opening_balance' => (float) ($row['opening_balance'] ?? 0),
                        'opening_balance_type' => strtoupper(trim((string) ($row['opening_balance_type'] ?? 'DEBIT'))),
                    ]);
                    $createdByCode[$acc->code] = $acc->id;
                    if ($parentId) {
                        Account::query()->whereKey($parentId)->update(['accepts_entries' => false]);
                    }
                    unset($pending[$key]);
                    $count++;
                }
            }
            return $count;
        });

        return ['valid' => count($rows), 'errors' => [], 'imported' => $imported];
    }
}
