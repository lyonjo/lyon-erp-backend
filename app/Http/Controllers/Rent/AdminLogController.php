<?php

namespace App\Http\Controllers\Rent;

use App\Http\Controllers\Controller;
use App\Models\Rent\AdminLog;
use Illuminate\Http\Request;

class AdminLogController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim($validated['search'] ?? '');
        $perPage = (int) ($validated['per_page'] ?? 15);

        $logs = AdminLog::query()
            ->with('user:id,name,email')
            ->when($search !== '', function ($query) use ($search) {
                // Literal substring search: don't treat user-entered % and _ as wildcards.
                $term = '%' . addcslashes($search, '%_\\') . '%';

                $query->where(function ($q) use ($term) {
                    $q->where('action', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('model', 'like', $term)
                        ->orWhereHas('user', function ($user) use ($term) {
                            $user->where('name', 'like', $term)
                                ->orWhere('email', 'like', $term);
                        });
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $logs->getCollection()->map(fn (AdminLog $log) => [
                'action_id' => $log->id,
                'employee_id' => $log->user?->id ?? '-',
                'action' => $log->action,
                'details' => $log->description,
                'date' => $log->created_at?->format('Y-m-d'),
                'time' => $log->created_at?->format('H:i'),
            ])->values(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
