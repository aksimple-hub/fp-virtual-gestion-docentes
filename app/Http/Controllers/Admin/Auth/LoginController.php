<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'user' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Intentar login por nombre
        $loginByName = Auth::guard('admin')->attempt([
            'nombre' => $credentials['user'],
            'password' => $credentials['password']
        ], $request->boolean('remember'));

        // Intentar login por email si el anterior falla
        $loginByEmail = false;
        if (!$loginByName && filter_var($credentials['user'], FILTER_VALIDATE_EMAIL)) {
            $loginByEmail = Auth::guard('admin')->attempt([
                'email' => $credentials['user'],
                'password' => $credentials['password']
            ], $request->boolean('remember'));
        }

        if ($loginByName || $loginByEmail) {
            $request->session()->regenerate();
            return redirect()->intended(route('admin.dashboard'));
        }

        throw ValidationException::withMessages([
            'user' => __('auth.failed'),
        ]);
    }

    public function logout(Request $request)
    {
        // Elimina el remember_token del usuario actual
        if (Auth::guard('admin')->check()) {
            $admin = Auth::guard('admin')->user();
            $admin->setRememberToken(null);
            $admin->save();
        }

        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
}

}
