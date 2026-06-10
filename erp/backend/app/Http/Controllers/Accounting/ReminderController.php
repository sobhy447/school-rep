<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Settings\BaseCrudController;
use App\Models\Reminder;
use App\Services\ReminderService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReminderController extends BaseCrudController
{
    protected string $modelClass = Reminder::class;

    public function __construct(private ReminderService $service) {}

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'type' => ['nullable', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['PENDING', 'DONE', 'DISMISSED'])],
        ];
    }

    public function index(): JsonResponse
    {
        return $this->ok(Reminder::query()->orderBy('status')->orderBy('due_date')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules($request));
        $data['type'] = $data['type'] ?? 'CUSTOM';
        $data['status'] = $data['status'] ?? 'PENDING';
        $data['created_by'] = $request->user()?->id;
        return $this->ok(Reminder::query()->create($data), 'تم إنشاء التذكير', 201);
    }

    /** توليد التذكيرات التلقائية. */
    public function generate(): JsonResponse
    {
        $count = $this->service->generate(TenantContext::id());
        return $this->ok(['created' => $count], "تم توليد {$count} تذكير");
    }

    /** المستحقّة + العدّاد (للجرس). */
    public function due(Request $request): JsonResponse
    {
        $within = (int) $request->query('within', 30);
        return $this->ok([
            'count' => $this->service->pendingCount(TenantContext::id()),
            'items' => $this->service->due(TenantContext::id(), $within),
        ]);
    }

    public function markDone(int $id): JsonResponse
    {
        $r = Reminder::query()->findOrFail($id);
        $r->update(['status' => 'DONE']);
        return $this->ok($r, 'تم الإنهاء');
    }

    public function dismiss(int $id): JsonResponse
    {
        $r = Reminder::query()->findOrFail($id);
        $r->update(['status' => 'DISMISSED']);
        return $this->ok($r, 'تم التجاهل');
    }
}
