<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * تحكّم CRUD أساسي موحّد لكيانات الإعدادات.
 * كل الاستعلامات معزولة تلقائياً بالمستأجر عبر Global Scope (BelongsToCompany).
 * كل فئة فرعية تحدّد: $modelClass + rules() + (اختياري) transform().
 */
abstract class BaseCrudController extends Controller
{
    /** @var class-string<Model> */
    protected string $modelClass;

    /** قواعد التحقّق؛ $id موجود عند التحديث (لتجاهل السجل الحالي في unique). */
    abstract protected function rules(Request $request, ?int $id = null): array;

    /** ترتيب العرض الافتراضي. */
    protected string $orderBy = 'id';

    public function index(): JsonResponse
    {
        $items = $this->modelClass::query()->orderBy($this->orderBy)->get();

        return $this->ok($items);
    }

    public function show(int $id): JsonResponse
    {
        $item = $this->modelClass::query()->findOrFail($id);

        return $this->ok($item);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules($request));
        $item = $this->modelClass::query()->create($data);

        return $this->ok($item, 'تم الإنشاء بنجاح', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = $this->modelClass::query()->findOrFail($id);
        $data = $request->validate($this->rules($request, $id));
        $item->update($data);

        return $this->ok($item->fresh(), 'تم التحديث بنجاح');
    }

    public function destroy(int $id): JsonResponse
    {
        $item = $this->modelClass::query()->findOrFail($id);
        $item->delete();

        return $this->ok(null, 'تم الحذف بنجاح');
    }

    protected function ok($data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
