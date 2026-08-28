<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        if (! $user instanceof User || $user->temp_password_changed) {
            return $next($request);
        }

        if ($this->shouldAllowWithoutPasswordChange($request)) {
            return $next($request);
        }

        return redirect()->route('change-initial-password');
    }

    protected function shouldAllowWithoutPasswordChange(Request $request): bool
    {
        if ($request->routeIs([
            'change-initial-password',
            'change-initial-password.submit',
            'filament.admin.pages.change-password',
            'filament.admin.logout',
            'filament.admin.auth.logout',
            'filament.admin.auth.login',
            'login',
            'logout',
            'livewire.*',
        ])) {
            return true;
        }

        if ($request->is([
            'change-initial-password',
            'admin/change-password',
            'livewire*',
            'livewire/*',
        ])) {
            return true;
        }

        if ($request->hasHeader('X-Livewire')) {
            return true;
        }

        return false;
    }
}
