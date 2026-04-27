<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DemoContactMessageController extends Controller
{
    /**
     * List all contact messages.
     */
    public function index(): JsonResponse
    {
        $messages = ContactMessage::latest()->get();

        return response()->json([
            'success' => true,
            'count' => $messages->count(),
            'data' => $messages,
        ])->header('X-Demo-Endpoint', 'list');
    }

    /**
     * Show a single contact message.
     */
    public function show(int $id): JsonResponse
    {
        $message = ContactMessage::find($id);

        if (! $message) {
            return response()->json([
                'success' => false,
                'message' => 'Contact message not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $message,
        ])->header('X-Demo-Endpoint', 'show');
    }

    /**
     * Store a new contact message.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:20'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ]);

        $contactMessage = ContactMessage::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Contact message created successfully.',
            'data' => $contactMessage->fresh(),
        ], 201)->header('X-Demo-Endpoint', 'create');
    }

    /**
     * Update an existing contact message.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $contactMessage = ContactMessage::find($id);

        if (! $contactMessage) {
            return response()->json([
                'success' => false,
                'message' => 'Contact message not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:191'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'message' => ['sometimes', 'required', 'string'],
            'is_read' => ['sometimes', 'boolean'],
        ]);

        $contactMessage->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Contact message updated successfully.',
            'data' => $contactMessage->fresh(),
        ])->header('X-Demo-Endpoint', 'update');
    }

    /**
     * Delete a contact message.
     */
    public function destroy(int $id): JsonResponse
    {
        $contactMessage = ContactMessage::find($id);

        if (! $contactMessage) {
            return response()->json([
                'success' => false,
                'message' => 'Contact message not found.',
            ], 404);
        }

        $contactMessage->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact message deleted successfully.',
        ], 200)->header('X-Demo-Endpoint', 'delete');
    }
}
