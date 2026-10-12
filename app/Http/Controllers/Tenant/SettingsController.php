<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;

class SettingsController extends Controller
{
    public function show()
    {
        return [
            'name'          => tenant('company_name') ?? tenant('id'),
            'logo'          => tenant('logo_url'),
            'primary_color' => tenant('primary_color') ?? '#4f46e5',
        ];
    }
}
