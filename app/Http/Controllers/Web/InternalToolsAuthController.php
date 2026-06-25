<?php

namespace App\Http\Controllers\Web;

use App\Enums\SystemRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InternalToolsAuthController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()?->hasRole(SystemRole::SuperAdmin->value)) {
            return redirect()->intended(route('docs'));
        }

        return view('auth.internal-tools-login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $authenticated = Auth::attempt([
            'email' => Str::lower((string) $credentials['email']),
            'password' => $credentials['password'],
            'is_active' => true,
        ], $request->boolean('remember'));

        if (! $authenticated) {
            throw ValidationException::withMessages([
                'email' => 'Invalid credentials.',
            ]);
        }

        $request->session()->regenerate();

        if (! $request->user()?->hasRole(SystemRole::SuperAdmin->value)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Only super_admin users can access this console.',
            ]);
        }

        return redirect()->intended(route('docs'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('internal-tools.login');
    }
}
