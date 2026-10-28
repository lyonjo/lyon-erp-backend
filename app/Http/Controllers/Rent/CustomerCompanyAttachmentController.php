<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\CustomerCompany;
use App\Models\Rent\CustomerCompanyAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CustomerCompanyAttachmentController extends Controller
{
    public function index(CustomerCompany $customerCompany)
    {
        return response()->json(['data' => $customerCompany->attachments()->get()->map(fn($a) => ['id' => $a->id, 'original_name' => $a->original_name, 'size' => $a->size, 'mime_type' => $a->mime_type])]);
    }
    public function store(Request $request, CustomerCompany $customerCompany)
    {
        $validated = $request->validate(['file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx']]);
        $file = $validated['file'];
        $path = $file->store('customer-companies/' . $customerCompany->id . '/attachments', 'local');
        $attachment = $customerCompany->attachments()->create(['original_name' => $file->getClientOriginalName(), 'path' => $path, 'size' => $file->getSize(), 'mime_type' => $file->getMimeType()]);
        return response()->json(['data' => ['id' => $attachment->id, 'original_name' => $attachment->original_name]], 201);
    }
    public function download(CustomerCompany $customerCompany, CustomerCompanyAttachment $attachment)
    {
        abort_unless($attachment->customer_company_id === $customerCompany->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);
        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }
    public function destroy(CustomerCompany $customerCompany, CustomerCompanyAttachment $attachment)
    {
        abort_unless($attachment->customer_company_id === $customerCompany->id, 404);
        $path = $attachment->path;
        $attachment->delete();
        Storage::disk('local')->delete($path);
        return response()->noContent();
    }
}
