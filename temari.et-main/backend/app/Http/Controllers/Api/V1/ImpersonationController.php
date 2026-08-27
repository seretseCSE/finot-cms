<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\ImpersonationToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ImpersonationController extends Controller
{
    public function authenticate(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        $record = ImpersonationToken::where('token', hash('sha256', $request->input('token')))->first();

        if ($record === null || ! $record->isValid()) {
            throw ValidationException::withMessages([
                'token' => ['This impersonation link is invalid or has expired.'],
            ]);
        }

        $record->forceFill(['used_at' => now()])->save();

        $user = $record->targetUser()->firstOrFail();
        $user->load('memberships');

        $token = $user->createToken('impersonation')->plainTextToken;

        return (new UserResource($user))
            ->additional(['meta' => ['token' => $token], 'message' => 'Impersonation successful.'])
            ->response();
    }
}
