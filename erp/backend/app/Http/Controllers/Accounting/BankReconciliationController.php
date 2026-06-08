<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\BankReconciliation;
use App\Services\BankReconciliationService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BankReconciliationController extends Controller
{
    public function __construct(private BankReconciliationService $service) {}

    private function ok($data, ?string $msg = null, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $msg, 'data' => $data], $status);
    }

    public function index(): JsonResponse
    {
        return $this->ok(BankReconciliation::query()->with('account:id,code,name')->orderByDesc('id')->get());
    }

    public function show(int $id): JsonResponse
    {
        return $this->ok($this->service->view(BankReconciliation::query()->findOrFail($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('company_id', TenantContext::id())],
            'statement_date' => ['required', 'date'],
            'statement_balance' => ['required', 'numeric'],
        ]);
        $rec = $this->service->create(TenantContext::id(), $request->user()?->id, $data);

        return $this->ok($this->service->view($rec), 'تم إنشاء التسوية', 201);
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'line_id' => ['required', 'integer'],
            'cleared' => ['required', 'boolean'],
        ]);
        $rec = BankReconciliation::query()->findOrFail($id);
        return $this->ok($this->service->toggle($rec, $data['line_id'], $data['cleared']), 'تم التحديث');
    }

    /** استيراد كشف بنك CSV (أعمدة: date, description, amount) ومطابقته تلقائياً. */
    public function importStatement(Request $request, int $id): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);
        $rec = BankReconciliation::query()->findOrFail($id);

        $rows = [];
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = null;
        while (($data = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = array_map(fn ($h) => strtolower(trim($h)), $data);
                continue;
            }
            $row = @array_combine($header, $data);
            if (! $row) continue;
            $amount = isset($row['amount']) ? (float) $row['amount']
                : ((float) ($row['debit'] ?? 0) - (float) ($row['credit'] ?? 0));
            $rows[] = ['amount' => $amount];
        }
        fclose($handle);

        $result = $this->service->importStatement($rec, $rows);
        return $this->ok($result['view'], "تمت مطابقة {$result['matched']} حركة من الكشف");
    }

    public function complete(int $id): JsonResponse
    {
        $rec = BankReconciliation::query()->findOrFail($id);
        return $this->ok($this->service->complete($rec), 'تمت التسوية');
    }
}
