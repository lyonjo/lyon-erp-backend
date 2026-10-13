<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (tenant()->status !== 'active') {
            return response()->json(['message' => 'This website is not active.'], 403);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        return response()->json([
            'token' => $user->createToken('spa')->plainTextToken,
            'user' => $user,
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $modules = tenant()->activeModuleKeys();

        $permissions = $user->getAllPermissions()
            ->pluck('name')
            ->filter(fn($p) => in_array(Str::before($p, '.'), $modules, true))
            ->values();

        return [
            'user'        => $user->only(['id', 'name', 'email']),
            'roles'       => $user->getRoleNames(),
            'modules'     => $modules,
            'permissions' => $permissions,
        ];
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->noContent();
    }
}
