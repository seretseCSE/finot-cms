<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RecordPageView
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $response->isSuccessful()) {
            $path = '/'.ltrim($request->path(), '/');
            if ($path === '//') {
                $path = '/';
            }

            try {
                PageView::query()->create([
                    'path' => substr($path, 0, 255),
                    'referrer' => substr((string) $request->headers->get('referer'), 0, 255) ?: null,
                    'session_hash' => hash('sha256', $request->session()->getId() ?: $request->ip() ?: 'anon'),
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to record public page view.', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $response;
    }
}
