<?php

namespace App\Http\Controllers;

use App\Services\Contracts\PushNotificationServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function publicKey(): JsonResponse
    {
        return response()->json([
            'publicKey' => config('finot.vapid.public_key'),
        ]);
    }

    public function store(Request $request, PushNotificationServiceInterface $push): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        $push->subscribe(
            (int) $request->user()->id,
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, PushNotificationServiceInterface $push): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
        ]);

        $push->unsubscribe((int) $request->user()->id, $data['endpoint']);

        return response()->json(['ok' => true]);
    }
}
