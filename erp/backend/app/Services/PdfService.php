<?php

namespace App\Services;

use Mpdf\Mpdf;

/**
 * توليد PDF بدعم عربي/RTL، مع إمكانية دمج صفحات مرفقات (PDF) خارجية.
 */
class PdfService
{
    private function make(): Mpdf
    {
        return new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'directionality' => 'rtl',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'tempDir' => storage_path('app/mpdf'),
            'margin_top' => 14, 'margin_bottom' => 14, 'margin_left' => 12, 'margin_right' => 12,
        ]);
    }

    /** يحوّل HTML إلى PDF ويرجّع المحتوى الثنائي. */
    public function render(string $html): string
    {
        $mpdf = $this->make();
        $mpdf->WriteHTML($this->wrap($html));
        return $mpdf->Output('', 'S');
    }

    /**
     * يولّد PDF من HTML ثم يُلحق صفحات من ملفات مرفقة.
     * @param array $attachments  [['path'=>absolute, 'from'=>int, 'to'=>int], ...]
     */
    public function renderWithAttachments(string $html, array $attachments): string
    {
        $mpdf = $this->make();
        $mpdf->WriteHTML($this->wrap($html));

        foreach ($attachments as $att) {
            if (empty($att['path']) || ! is_file($att['path'])) {
                continue;
            }
            try {
                $pageCount = $mpdf->setSourceFile($att['path']);
            } catch (\Throwable $e) {
                continue; // ملف غير صالح — تخطّاه
            }
            $from = max(1, (int) ($att['from'] ?? 1));
            $to = min($pageCount, (int) ($att['to'] ?? $from));
            for ($p = $from; $p <= $to; $p++) {
                $tpl = $mpdf->importPage($p);
                $size = $mpdf->getTemplateSize($tpl);
                $orient = ($size['width'] > $size['height']) ? 'L' : 'P';
                $mpdf->AddPageByArray(['orientation' => $orient]);
                $mpdf->useTemplate($tpl, 0, 0, $size['width'], $size['height']);
            }
        }

        return $mpdf->Output('', 'S');
    }

    private function wrap(string $body): string
    {
        return '<html dir="rtl"><head><style>
            body{font-family:dejavusans,sans-serif;font-size:12px;color:#0f172a;}
            h1,h2,h3{margin:0 0 6px;color:#1e3a8a;}
            table{width:100%;border-collapse:collapse;margin-top:8px;}
            th,td{border:1px solid #cbd5e1;padding:6px 8px;text-align:right;font-size:11px;}
            th{background:#eef2ff;}
            .head{display:flex;justify-content:space-between;border-bottom:2px solid #1e3a8a;padding-bottom:8px;margin-bottom:10px;}
            .tot{font-weight:bold;background:#f8fafc;}
            .muted{color:#64748b;}
        </style></head><body>' . $body . '</body></html>';
    }
}
