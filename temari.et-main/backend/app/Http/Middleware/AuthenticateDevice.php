<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The machine lane: RFID terminals authenticate with their own bearer token
 * (devices.token_hash), never via user memberships. A matched device is bound
 * to the request; every gateway action reads its school/branch from there —
 * a device can never write outside its own branch.
 */
class AuthenticateDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        $device = $token
            ? Device::query()
                ->where('token_hash', Device::hashToken($token))
                ->where('is_active', true)
                ->first()
            : null;

        abort_if($device === null, 401, 'Invalid device credentials.');

        $device->forceFill(['last_seen_at' => now()])->saveQuietly();

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
