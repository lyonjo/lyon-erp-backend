<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rent\CompanyRequest;
use App\Http\Resources\Rent\CompanyResource;
use App\Models\Rent\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;
use App\Services\Rent\AdminLogger;

class CompanyController extends Controller
{
    public function index()
    {
        return CompanyResource::collection(
            Company::query()->with('logo')->orderByDesc('is_main')->orderBy('id')->get()
        );
    }

    public function show(Company $company)
    {
        return new CompanyResource($company->load('logo'));
    }

    public function store(CompanyRequest $request)
    {
        $data = $request->validated();
        $newPath = null;

        try {
            $company = DB::transaction(function () use ($request, $data, &$newPath) {
                // At most one company should be marked as main (see README for concurrency note).
                if (! empty($data['is_main'])) {
                    Company::query()->where('is_main', true)->update(['is_main' => false]);
                }

                $company = Company::create(collect($data)->except('logo')->all());
                $newPath = $request->file('logo')->store("companies/{$company->id}", 'public');
                $company->logo()->create(['type' => 'logo', 'path' => $newPath]);
                AdminLogger::log(
                    action: 'created',
                    model: Company::class,
                    modelId: $company->id,
                    description: "Created company #{$company->id}",
                    data: $company->toArray()
                );
                return $company;
            });
        } catch (Throwable $e) {
            if ($newPath) Storage::disk('public')->delete($newPath);
            throw $e;
        }

        return (new CompanyResource($company->load('logo')))
            ->response()->setStatusCode(201);
    }

    public function update(CompanyRequest $request, Company $company)
    {
        $data = $request->validated();
        $newPath = null;
        $oldPath = null;

        try {
            DB::transaction(function () use ($request, $data, $company, &$newPath, &$oldPath) {
                if (! empty($data['is_main'])) {
                    Company::query()->where('is_main', true)->whereKeyNot($company->id)
                        ->update(['is_main' => false]);
                }

                $company->update(collect($data)->except('logo')->all());
                AdminLogger::log(
                    action: 'updated',
                    model: Company::class,
                    modelId: $company->id,
                    description: "Updated company #{$company->id}",
                    data: $company->toArray()
                );
                if ($request->hasFile('logo')) {
                    $newPath = $request->file('logo')->store("companies/{$company->id}", 'public');
                    $logo = $company->logo()->first();
                    $oldPath = $logo?->path;

                    if ($logo) {
                        $logo->update(['path' => $newPath]);
                    } else {
                        $company->logo()->create(['type' => 'logo', 'path' => $newPath]);
                    }
                }
            });
        } catch (Throwable $e) {
            if ($newPath) Storage::disk('public')->delete($newPath);
            throw $e;
        }

        if ($oldPath) Storage::disk('public')->delete($oldPath);

        return new CompanyResource($company->fresh('logo'));
    }

    public function destroy(Company $company)
    {
        $logoPath = $company->logo?->path;
        DB::transaction(function () use ($company) {
            $company->logo()->delete();
            $company->delete();
        });

        if ($logoPath) Storage::disk('public')->delete($logoPath);
        Storage::disk('public')->deleteDirectory("companies/{$company->id}");

        return response()->noContent();
    }
    public function logo(Company $company)
    {
        $image = $company->logo;

        if (! $image) {
            abort(404, 'Company logo not found.');
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($image->path)) {
            abort(404, 'Company logo file not found.');
        }

        return $disk->response($image->path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
