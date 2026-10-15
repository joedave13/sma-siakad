<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChangeCompleted
{
    /**
     * Force users with must_change_password to the password change screen.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->must_change_password
            && ! $request->routeIs('login', 'login.store', 'logout', 'force-password.*', 'two-factor.*', 'passkeys.*')) {
            return redirect()->route('force-password.change');
        }

        return $next($request);
    }
}
