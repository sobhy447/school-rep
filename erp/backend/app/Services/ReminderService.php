<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\InventoryItem;
use App\Models\Reminder;
use App\Support\TenantContext;
use Illuminate\Support\Carbon;

/**
 * توليد التذكيرات تلقائياً من بيانات النظام + إدارة التذكيرات اليدوية.
 */
class ReminderService
{
    public function __construct(private InventoryService $inventory) {}

    /** يولّد تذكيرات تلقائية (يتجاهل المكرر المعلّق). */
    public function generate(int $companyId): int
    {
        $created = 0;
        $today = Carbon::today()->toDateString();

        // 1) أصناف تحت حد إعادة الطلب
        foreach (InventoryItem::query()->where('company_id', $companyId)->where('reorder_level', '>', 0)->get() as $item) {
            $onHand = $this->inventory->totalOnHand($item->id);
            if ($onHand <= (float) $item->reorder_level) {
                $created += $this->upsert($companyId, 'REORDER', 'INVENTORY_ITEM', $item->id,
                    "الصنف «{$item->name}» وصل حد إعادة الطلب (المتاح {$onHand})", $today);
            }
        }

        // 2) سنوات مالية تنتهي خلال 30 يوماً وما زالت مفتوحة
        foreach (FiscalYear::query()->where('company_id', $companyId)->where('status', 'OPEN')->get() as $fy) {
            $daysLeft = Carbon::today()->diffInDays($fy->end_date, false);
            if ($daysLeft >= 0 && $daysLeft <= 30) {
                $created += $this->upsert($companyId, 'FISCAL_YEAR', 'FISCAL_YEAR', $fy->id,
                    "السنة المالية «{$fy->name}» تنتهي قريباً — جهّز الإقفال", $fy->end_date->toDateString());
            }
        }

        return $created;
    }

    private function upsert(int $companyId, string $type, string $refType, int $refId, string $title, string $due): int
    {
        $exists = Reminder::query()->where('company_id', $companyId)->where('type', $type)
            ->where('reference_type', $refType)->where('reference_id', $refId)->where('status', 'PENDING')->exists();
        if ($exists) {
            return 0;
        }
        Reminder::query()->create([
            'company_id' => $companyId, 'type' => $type, 'title' => $title, 'due_date' => $due,
            'status' => 'PENDING', 'reference_type' => $refType, 'reference_id' => $refId, 'auto' => true,
        ]);
        return 1;
    }

    /** التذكيرات المستحقّة خلال عدد أيام. */
    public function due(int $companyId, int $within = 30): array
    {
        $limit = Carbon::today()->addDays($within)->toDateString();
        return Reminder::query()->where('company_id', $companyId)->where('status', 'PENDING')
            ->whereDate('due_date', '<=', $limit)->orderBy('due_date')->get()->all();
    }

    public function pendingCount(int $companyId): int
    {
        return Reminder::query()->where('company_id', $companyId)->where('status', 'PENDING')
            ->whereDate('due_date', '<=', Carbon::today()->addDays(30)->toDateString())->count();
    }
}
