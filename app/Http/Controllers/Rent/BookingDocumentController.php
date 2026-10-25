<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\Booking;
use App\Models\Rent\BookingDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Authenticated tenant-only access to booking-specific document images.
 * The customer profile files are NEVER changed by these operations.
 */
class BookingDocumentController extends Controller
{
    private const TYPES = ['license', 'id_passport', 'flight_ticket'];

    private function assertType(string $type): void
    {
        abort_unless(in_array($type, self::TYPES, true), 404);
    }

    private function findDocument(Booking $booking, string $type): BookingDocument
    {
        $this->assertType($type);

        return BookingDocument::query()
            ->where('booking_id', $booking->getKey())
            ->where('doc_type', $type)
            ->firstOrFail();
    }

    public function index(Booking $booking): JsonResponse
    {
        $records = BookingDocument::query()
            ->where('booking_id', $booking->getKey())
            ->get()
            ->keyBy('doc_type');

        return response()->json(['data' => collect(self::TYPES)->mapWithKeys(
            fn(string $type) => [$type => [
                'exists' => $records->has($type),
                'name' => $records->get($type)?->original_name,
                'updated_at' => $records->get($type)?->updated_at?->toIso8601String(),
            ]]
        )]);
    }

    public function show(Booking $booking, string $type)
    {
        $document = $this->findDocument($booking, $type);
        $disk = Storage::disk('local'); // Stancl tenant-scoped private storage
        abort_unless($disk->exists($document->path), 404);

        $mime = $document->mime_type ?: ($disk->mimeType($document->path) ?: 'image/jpeg');

        return response()->stream(function () use ($disk, $document) {
            $stream = $disk->readStream($document->path);
            if ($stream === false) {
                return;
            }
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
        $data = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $file = $data['image'];
        $disk = Storage::disk('local');
        $path = $file->store("booking-documents/{$booking->getKey()}", 'local');
        if (!$path) {
            return response()->json(['message' => 'Unable to save image.'], 500);
        }

        try {
            $existing = BookingDocument::query()
                ->where('booking_id', $booking->getKey())
                ->where('doc_type', $type)
                ->first();
            $oldPath = $existing?->path;

            BookingDocument::query()->updateOrCreate(
                ['booking_id' => $booking->getKey(), 'doc_type' => $type],
                [
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                ]
            );

            if ($oldPath && $oldPath !== $path) {
                $disk->delete($oldPath);
            }
        } catch (\Throwable $exception) {
            $disk->delete($path);
            throw $exception;
        }

        return response()->json(['message' => 'Document saved successfully.', 'data' => ['type' => $type]], 200);
    }

    public function destroy(Booking $booking, string $type): JsonResponse
    {
        $document = $this->findDocument($booking, $type);
        $path = $document->path;
        $document->delete();
        Storage::disk('local')->delete($path);

        return response()->json(['message' => 'Document removed.']);
    }
}
