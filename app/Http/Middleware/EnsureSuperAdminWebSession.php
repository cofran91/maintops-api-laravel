<?php

namespace App\Http\Middleware;

use App\Enums\SystemRole;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdminWebSession
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('internal-tools.login'));
        }

        abort_unless($user->hasRole(SystemRole::SuperAdmin->value), 403);

        return $next($request);
    }
}
