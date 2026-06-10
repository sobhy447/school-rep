<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Support\TenantContext;

/**
 * يسجّل تلقائياً عمليات الإنشاء/التعديل/الحذف في audit_logs — فقط لأفعال المستخدمين المسجّلين.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($m) => $m->writeAudit('CREATE', null, $m->auditableValues()));
        static::updated(function ($m) {
            $changes = $m->getChanges();
            unset($changes['updated_at']);
            if (empty($changes)) return;
            $old = array_intersect_key($m->getOriginal(), $changes);
            $m->writeAudit('UPDATE', $old, $changes);
        });
        static::deleted(fn ($m) => $m->writeAudit('DELETE', $m->auditableValues(), null));
    }

    protected function auditableValues(): array
    {
        return collect($this->attributesToArray())->except(['created_at', 'updated_at'])->all();
    }

    protected function writeAudit(string $action, ?array $old, ?array $new): void
    {
        $user = auth()->user();
        if (! $user) {
            return; // نسجّل أفعال المستخدمين فقط (لا البذور/الاختبارات الداخلية)
        }
        AuditLog::query()->create([
            'company_id' => $this->company_id ?? TenantContext::id(),
            'user_id' => $user->id,
            'user_name' => $user->name,
            'action' => $action,
            'auditable_type' => class_basename($this),
            'auditable_id' => $this->getKey(),
            'old_values' => $old,
            'new_values' => $new,
        ]);
    }
}
