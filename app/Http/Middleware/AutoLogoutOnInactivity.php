<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AutoLogoutOnInactivity
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $timeoutMinutes = (int) env('AUTO_LOGOUT_MINUTES', (int) config('session.lifetime', 120));
            $lastActivity = $request->session()->get('last_activity');

            if ($lastActivity && (time() - (int) $lastActivity) > ($timeoutMinutes * 60)) {
                Auth::logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->withErrors(['email' => 'Tu sesión se cerró por inactividad.']);
            }

            $request->session()->put('last_activity', time());
        }

        return $next($request);
    }
}
