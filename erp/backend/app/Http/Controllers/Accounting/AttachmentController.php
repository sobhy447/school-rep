<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\DocumentAttachment;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * مرفقات السندات/القيود (PDF) + ربط كل سطر بأرقام صفحاته في الملف.
 */
class AttachmentController extends Controller
{
    private function ok($data, ?string $msg = null, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $msg, 'data' => $data], $status);
    }

    private function entry(int $id): JournalEntry
    {
        return JournalEntry::query()->findOrFail($id); // مفلتر بالمستأجر تلقائياً
    }

    public function index(int $entryId): JsonResponse
    {
        $this->entry($entryId);
        return $this->ok(DocumentAttachment::query()->where('journal_entry_id', $entryId)->get());
    }

    /** رفع ملف PDF وربطه بالقيد. */
    public function upload(Request $request, int $entryId): JsonResponse
    {
        $entry = $this->entry($entryId);
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'total_pages' => ['nullable', 'integer', 'min:1'],
        ]);
        $file = $request->file('file');
        $hash = hash_file('sha256', $file->getRealPath());
        $stored = $entry->company_id . '/' . uniqid('att_') . '.pdf';
        Storage::disk('local')->putFileAs('attachments/' . $entry->company_id, $file, basename($stored));

        $att = DocumentAttachment::query()->create([
            'company_id' => $entry->company_id,
            'journal_entry_id' => $entry->id,
            'file_name' => $file->getClientOriginalName(),
            'stored_name' => basename($stored),
            'file_path' => 'attachments/' . $stored,
            'file_size' => $file->getSize(),
            'file_hash' => $hash,
            'total_pages' => $request->input('total_pages'),
            'uploaded_by' => $request->user()?->id,
        ]);
        return $this->ok($att, 'تم رفع المرفق', 201);
    }

    /** تنزيل/عرض المرفق (يدعم #page=N في المتصفح). */
    public function download(int $id): StreamedResponse
    {
        $att = DocumentAttachment::query()->findOrFail($id);
        abort_unless(Storage::disk('local')->exists($att->file_path), 404);
        return Storage::disk('local')->download($att->file_path, $att->file_name, ['Content-Type' => 'application/pdf']);
    }

    public function destroy(int $id): JsonResponse
    {
        $att = DocumentAttachment::query()->findOrFail($id);
        Storage::disk('local')->delete($att->file_path);
        $att->delete();
        return $this->ok(null, 'تم حذف المرفق');
    }

    /**
     * ربط أرقام الصفحات بكل سطر: [{line_id, page_from, page_to}].
     * صفحة واحدة ➜ page_to = page_from. أكثر من صفحة ➜ من..إلى.
     */
    public function setLinePages(Request $request, int $entryId): JsonResponse
    {
        $this->entry($entryId);
        $data = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*.line_id' => ['required', 'integer'],
            'lines.*.page_from' => ['nullable', 'integer', 'min:1'],
            'lines.*.page_to' => ['nullable', 'integer', 'min:1', 'gte:lines.*.page_from'],
        ]);
        foreach ($data['lines'] as $l) {
            $from = $l['page_from'] ?? null;
            $to = $l['page_to'] ?? $from; // صفحة واحدة
            JournalLine::query()->where('journal_entry_id', $entryId)->where('id', $l['line_id'])
                ->update(['page_from' => $from, 'page_to' => $to]);
        }
        return $this->ok(null, 'تم ربط الصفحات بالسطور');
    }
}
