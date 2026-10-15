<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\Company;
use App\Models\Rent\Customer;
use Illuminate\Support\Facades\Storage;

class RentMediaController extends Controller
{
    public function customerDocument(
        Customer $customer,
        string $type
    ) {
        // Only expose known customer document types.
        abort_unless(
            in_array($type, Customer::DOCUMENT_TYPES, true),
            404
        );

        $image = $customer->images()
            ->where('type', $type)
            ->firstOrFail();

        return $this->serve($image->path);
    }

    public function companyLogo(Company $company)
    {
        $image = $company->logo()->firstOrFail();

        return $this->serve($image->path);
    }

    private function serve(string $path)
    {
        $disk = Storage::disk('public');

        abort_unless($disk->exists($path), 404);

        // Only serve supported raster images.
        // SVG is intentionally excluded.
        $mime = $disk->mimeType($path);

        abort_unless(
            in_array($mime, [
                'image/jpeg',
                'image/png',
                'image/webp',
            ], true),
            415,
            'Unsupported image format.'
        );

        return $disk->response($path, null, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
