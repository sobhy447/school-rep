<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = AuditLog::query()
            ->when($request->query('type'), fn ($q, $t) => $q->where('auditable_type', $t))
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', $a))
            ->when($request->query('from'), fn ($q, $f) => $q->whereDate('created_at', '>=', $f))
            ->when($request->query('to'), fn ($q, $t) => $q->whereDate('created_at', '<=', $t))
            ->orderByDesc('id')->limit(200)->get();

        return response()->json(['success' => true, 'message' => null, 'data' => $logs]);
    }
}
