<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\TenantLoginImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantLoginImageController extends Controller
{
    private const SLOTS = ['logo', 'illustration'];

    /** Public tenant-scoped metadata, available before login. */
    public function index(): JsonResponse
    {
        $slots = TenantLoginImage::query()->whereIn('slot', self::SLOTS)->pluck('slot')->all();

        return response()->json(['data' => [
            'logo' => in_array('logo', $slots, true),
            'illustration' => in_array('illustration', $slots, true),
        ]])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    /** Public tenant-scoped image. 404 means the frontend should show its default bundled image. */
    public function show(string $slot): StreamedResponse
    {
        abort_unless(in_array($slot, self::SLOTS, true), 404);

        $record = TenantLoginImage::query()->where('slot', $slot)->firstOrFail();
        $disk = Storage::disk('public');
        abort_unless($disk->exists($record->path), 404);

        $mime = $disk->mimeType($record->path);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 415);

        return $disk->response($record->path, null, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Requires auth:sanctum + module:rent + administrator authorization at route level. */
    public function update(Request $request, string $slot): JsonResponse
    {
        abort_unless(in_array($slot, self::SLOTS, true), 404);

        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        /** @var UploadedFile $image */
        $image = $validated['image'];
        $disk = Storage::disk('public');
        $newPath = $image->store('login-branding', 'public');
        abort_if($newPath === false, 500, 'Could not store the image.');

        try {
            $old = TenantLoginImage::query()->where('slot', $slot)->first();
            $oldPath = $old?->path;

            TenantLoginImage::query()->updateOrCreate(
                ['slot' => $slot],
                ['path' => $newPath]
            );
        } catch (\Throwable $e) {
            $disk->delete($newPath);
            throw $e;
        }

        if ($oldPath && $oldPath !== $newPath) {
            $disk->delete($oldPath);
        }

        return response()->json(['message' => 'Login image updated.'])
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    /** Reverts an overridden image to the bundled frontend default. */
    public function destroy(string $slot): JsonResponse
    {
        abort_unless(in_array($slot, self::SLOTS, true), 404);

        $record = TenantLoginImage::query()->where('slot', $slot)->first();
        if ($record) {
            $path = $record->path;
            $record->delete();
            Storage::disk('public')->delete($path);
        }

        return response()->json(['message' => 'Default login image restored.'])
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
