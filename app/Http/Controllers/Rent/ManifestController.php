<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\Company;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Mpdf\Mpdf;

class ManifestController extends Controller
{
    public function generate(Request $request)
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'pickup_date' => ['required', 'date_format:Y-m-d'],
            'pickup_time' => ['required', 'date_format:H:i'],
            'from' => ['required', 'string', 'max:255'],
            'to' => ['required', 'string', 'max:255'],
            'flight_ticket' => ['nullable', 'string', 'max:255'],
            'driver' => ['nullable', 'string', 'max:255'],
            'vehicle' => ['nullable', 'string', 'max:255'],
            'vehicle_no' => ['nullable', 'string', 'max:100'],
            'font' => ['nullable', 'integer', 'between:8,40'],
            'name_font' => ['nullable', 'integer', 'between:14,80'],
            'format' => ['nullable', 'in:pdf,json'],
        ]);

        $name = trim($data['customer_name']);
        if (mb_strlen($name) > 40) {
            $name = mb_substr($name, 0, 40) . '…';
        }

        $values = [
            'customerName' => $name,
            'date' => Carbon::parse($data['pickup_date'])->format('Y-m-d'),
            'pickupTime' => $data['pickup_time'],
            'from' => trim($data['from']),
            'to' => trim($data['to']),
            'flight' => filled($data['flight_ticket'] ?? null) ? trim($data['flight_ticket']) : '-',
            'driver' => filled($data['driver'] ?? null) ? trim($data['driver']) : '-',
            'vehicle' => filled($data['vehicle'] ?? null) ? trim($data['vehicle']) : '-',
            'carNo' => filled($data['vehicle_no'] ?? null) ? trim($data['vehicle_no']) : '-',
        ];

        if (($data['format'] ?? 'pdf') === 'json') {
            return response()->json([
                'customer_name' => $values['customerName'],
                'pickup_date' => $values['date'],
                'pickup_time' => $values['pickupTime'],
                'from' => $values['from'],
                'to' => $values['to'],
                'flight_ticket' => $values['flight'],
                'driver' => $values['driver'],
                'vehicle' => $values['vehicle'],
                'vehicle_no' => $values['carNo'],
            ]);
        }

        $len = mb_strlen($name);
        $autoNameFont = $len > 28 ? 20 : ($len > 20 ? 28 : ($len > 14 ? 32 : 44));
        $values['nameFont'] = (int) ($data['name_font'] ?? $autoNameFont);
        $values['detailFont'] = (int) ($data['font'] ?? 13);
        $values['namePaddingTop'] = $len > 28 ? 42 : ($len > 20 ? 38 : ($len > 14 ? 35 : 30));

        // Do not display fallback branding. Only the actual main company is eligible.
        $mainCompany = Company::query()->with('logo')->where('is_main', 1)->first();
        $values['companyName'] = $mainCompany && filled($mainCompany->name) ? $mainCompany->name : null;
        $values['logoSrc'] = $mainCompany ? $this->imageDataUri($mainCompany->logo?->path) : null;

        $cache = storage_path('framework/cache/mpdf');
        if (!is_dir($cache) && !mkdir($cache, 0775, true) && !is_dir($cache)) {
            abort(500, 'Cannot create the mPDF cache directory.');
        }
        if (!is_writable($cache)) {
            abort(500, 'The mPDF cache directory is not writable by the PHP process.');
        }

        try {
            $pdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4-L',
                'margin_left' => 0,
                'margin_right' => 0,
                'margin_top' => 0,
                'margin_bottom' => 0,
                'default_font' => 'dejavusans',
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
                'tempDir' => $cache,
            ]);
            $pdf->SetAutoPageBreak(false);
            $pdf->WriteHTML(view('rent.manifest', $values)->render());
            return response($pdf->Output('', 'S'), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="manifest.pdf"')
                ->header('Cache-Control', 'private, no-store');
        } catch (\Throwable $e) {
            Log::error('Manifest PDF generation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function imageDataUri(?string $path): ?string
    {
        if (!$path) return null;
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        if (str_contains($path, '..') || str_contains($path, '://')) return null;
        foreach (['public', 'local'] as $disk) {
            $storage = Storage::disk($disk);
            if (!$storage->exists($path)) continue;
            $bytes = $storage->get($path);
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
            if (in_array($mime, ['image/png', 'image/jpeg'], true)) {
                return 'data:' . $mime . ';base64,' . base64_encode($bytes);
            }
            if ($mime === 'image/webp' && function_exists('imagecreatefromstring')) {
                $source = @imagecreatefromstring($bytes);
                if ($source !== false) {
                    ob_start();
                    imagepng($source);
                    $png = ob_get_clean();
                    imagedestroy($source);
                    return 'data:image/png;base64,' . base64_encode($png);
                }
            }
        }
        return null;
    }
}
