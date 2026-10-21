<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rent\UpdateSettingsRequest;
use App\Models\Rent\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->values()]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $values = $request->validated();

        DB::transaction(function () use ($values): void {
            foreach (Setting::DEFAULTS as $key => $default) {
                Setting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => (string) $values[$key]]
                );
            }
        });

        return response()->json([
            'message' => 'Settings saved successfully.',
            'data' => $this->values(),
        ]);
    }

    private function values(): array
    {
        return array_replace(
            Setting::DEFAULTS,
            Setting::query()
                ->whereIn('key', array_keys(Setting::DEFAULTS))
                ->pluck('value', 'key')
                ->all()
        );
    }
}
