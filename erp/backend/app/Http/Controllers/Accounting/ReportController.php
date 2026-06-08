<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * التقارير المالية — كلها من القيود المُرحَّلة فقط.
 */
class ReportController extends Controller
{
    public function __construct(private ReportService $service) {}

    private function ok($data): JsonResponse
    {
        return response()->json(['success' => true, 'message' => null, 'data' => $data]);
    }

    private function range(Request $r): array
    {
        return [$r->query('from'), $r->query('to')];
    }

    public function trialBalance(Request $r): JsonResponse
    {
        [$from, $to] = $this->range($r);
        return $this->ok($this->service->trialBalance($from, $to));
    }

    public function incomeStatement(Request $r): JsonResponse
    {
        [$from, $to] = $this->range($r);
        return $this->ok($this->service->incomeStatement($from, $to));
    }

    public function balanceSheet(Request $r): JsonResponse
    {
        return $this->ok($this->service->balanceSheet($r->query('to')));
    }

    public function generalLedger(Request $r, int $accountId): JsonResponse
    {
        [$from, $to] = $this->range($r);
        return $this->ok($this->service->generalLedger($accountId, $from, $to));
    }

    public function cashFlow(Request $r): JsonResponse
    {
        [$from, $to] = $this->range($r);
        return $this->ok($this->service->cashFlow($from, $to));
    }

    public function claims(Request $r): JsonResponse
    {
        [$from, $to] = $this->range($r);
        $detailed = $r->query('mode', 'detailed') !== 'grouped';
        return $this->ok($this->service->claimsReport($detailed, $from, $to));
    }

    public function aging(Request $r): JsonResponse
    {
        return $this->ok($this->service->aging($r->query('type', 'CUSTOMER'), $r->query('as_of')));
    }

    public function vat(Request $r): JsonResponse
    {
        [$from, $to] = $this->range($r);
        return $this->ok($this->service->vatReport($from, $to));
    }

    public function monthlyTrend(Request $r): JsonResponse
    {
        return $this->ok($this->service->monthlyTrend($r->query('year') ? (int) $r->query('year') : null));
    }

    public function dashboard(): JsonResponse
    {
        return $this->ok($this->service->dashboard());
    }
}
