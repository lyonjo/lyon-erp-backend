<?php

namespace App\Services\Rent;

use App\Models\Rent\AdminLog;
use Illuminate\Support\Facades\Auth;

class AdminLogger
{
    public static function log(string $action, ?string $model = null, ?int $modelId = null, ?string $description = null, array $data = []): AdminLog
    {
        return AdminLog::create([
            'user_id'     => Auth::id(),
            'action'      => $action,
            'model'       => $model,
            'model_id'    => $modelId,
            'description' => $description,
            'data'        => $data,
        ]);
    }
}
