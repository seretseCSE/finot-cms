<?php

namespace App\Http\Middleware;

use App\Jobs\RecordPageViewJob;
use Closure;
use Illuminate\Http\Request;
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

            RecordPageViewJob::dispatch(
                substr($path, 0, 255),
                substr((string) $request->headers->get('referer'), 0, 255) ?: null,
                hash('sha256', $request->session()->getId() ?: $request->ip() ?: 'anon'),
                now()->toDateTimeString(),
            )->afterResponse();
        }

        return $response;
    }
}
