<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\Booking;
use App\Models\Rent\BookingDocument;
use App\Models\Rent\Customer;
use App\Models\Rent\CustomerCompany;
use App\Models\Rent\Image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * License and identity documents belong to the actual customer/company profile;
 * flight tickets belong to individual bookings.
 *
 * IMPORTANT: This adapter expects your tenant `images` polymorphic model to use
 * imageable_id, imageable_type, type, path, title, alt and the tenant public disk.
 * Confirm it matches the existing Customer document implementation before deploying.
 */
class BookingDocumentController extends Controller
{
    private const TYPES = ['license', 'id_passport', 'flight_ticket'];
    private const PROFILE_IMAGE_TYPES = [
        'license' => ['license_front', 'license_back'],
        'id_passport' => ['id_front', 'passport', 'id_back'],
    ];

    private function assertType(string $type): void
    {
        abort_unless(in_array($type, self::TYPES, true), 404);
    }

    private function profile(Booking $booking): Customer|CustomerCompany|null
    {
        if ($booking->customer_id !== null) {
            return Customer::query()->findOrFail($booking->customer_id);
        }
        if ($booking->customer_company_id !== null) {
            return CustomerCompany::query()->findOrFail($booking->customer_company_id);
        }
        return null;
    }

    private function profileImage(Booking $booking, string $type): ?Image
    {
        $profile = $this->profile($booking);
        if (!$profile || !isset(self::PROFILE_IMAGE_TYPES[$type])) {
            return null;
        }
        // Order matters: prefer the main/front image, otherwise an existing passport/back image.
        foreach (self::PROFILE_IMAGE_TYPES[$type] as $key) {
            $image = Image::query()
                ->where('imageable_type', $profile->getMorphClass())
                ->where('imageable_id', $profile->getKey())
                ->where('type', $key)
                ->first();
            if ($image) {
                return $image;
            }
        }
        return null;
    }

    private function bookingDocument(Booking $booking, string $type): ?BookingDocument
    {
        return BookingDocument::query()
            ->where('booking_id', $booking->getKey())
            ->where('doc_type', $type)
            ->first();
    }

    public function index(Booking $booking): JsonResponse
    {
        $out = [];
        foreach (self::TYPES as $type) {
            if ($type === 'flight_ticket') {
                $doc = $this->bookingDocument($booking, $type);
                $out[$type] = [
                    'exists' => (bool) $doc,
                    'name' => $doc?->original_name,
                    'updated_at' => $doc?->updated_at?->toIso8601String(),
                    'source' => 'booking',
                ];
            } else {
                $image = $this->profileImage($booking, $type);
                $out[$type] = [
                    'exists' => (bool) $image,
                    'name' => $image?->title,
                    'updated_at' => $image?->updated_at?->toIso8601String(),
                    'source' => 'profile',
                ];
            }
        }
        return response()->json(['data' => $out]);
    }

    public function show(Booking $booking, string $type)
    {
        $this->assertType($type);
        if ($type === 'flight_ticket') {
            $doc = $this->bookingDocument($booking, $type);
            abort_unless($doc, 404);
            $disk = Storage::disk('local');
            $path = $doc->path;
            $mime = $doc->mime_type ?: 'image/jpeg';
        } else {
            $image = $this->profileImage($booking, $type);
            abort_unless($image, 404);
            $disk = Storage::disk('public');
            $path = $image->path;
            $mime = $disk->mimeType($path) ?: 'image/jpeg';
        }
        abort_unless($disk->exists($path), 404);
        return response()->stream(function () use ($disk, $path) {
            $stream = $disk->readStream($path);
            abort_if($stream === false, 404);
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(Request $request, Booking $booking, string $type): JsonResponse
    {
        $this->assertType($type);
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $file = $request->file('image');

        if ($type === 'flight_ticket') {
            $disk = Storage::disk('local');
            $path = $file->store("booking-documents/{$booking->getKey()}", 'local');
            abort_unless($path, 500);
            try {
                DB::transaction(function () use ($booking, $type, $file, $disk, $path) {
                    $old = $this->bookingDocument($booking, $type);
                    BookingDocument::query()->updateOrCreate(
                        ['booking_id' => $booking->getKey(), 'doc_type' => $type],
                        ['path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType()]
                    );
                    if ($old && $old->path !== $path) $disk->delete($old->path);
                });
            } catch (\Throwable $e) {
                $disk->delete($path);
                throw $e;
            }
            return response()->json(['message' => 'Flight ticket updated.', 'data' => ['type' => $type]]);
        }

        $profile = $this->profile($booking);
        abort_unless($profile, 422, 'Booking has no customer or customer company.');
        $old = $this->profileImage($booking, $type);
        $key = $old?->type ?: self::PROFILE_IMAGE_TYPES[$type][0];
        $disk = Storage::disk('public');
        $folder = 'customer-documents/' . class_basename($profile) . '/' . $profile->getKey();
        $path = $file->store($folder, 'public');
        abort_unless($path, 500, 'Could not save document.');
        try {
            DB::transaction(function () use ($profile, $key, $path, $file, $old) {
                // Save into the same polymorphic image table as the Customer module.
                Image::query()->updateOrCreate(
                    [
                        'imageable_type' => $profile->getMorphClass(),
                        'imageable_id' => $profile->getKey(),
                        'type' => $key,
                    ],
                    [
                        'path' => $path,
                        'title' => $file->getClientOriginalName(),
                        'alt' => $key,
                    ]
                );
            });
        } catch (\Throwable $e) {
            $disk->delete($path);
            throw $e;
        }
        if ($old && $old->path !== $path) $disk->delete($old->path);
        return response()->json(['message' => 'Customer profile document updated.', 'data' => ['type' => $type]]);
    }
}
