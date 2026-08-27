<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ErrorLoggingMiddleware
{
    /**
     * @var list<string>
     */
    private array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'new_password_confirmation',
        'token',
        '_token',
        'authorization',
        'cookie',
        'remember',
        'remember_token',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);

            if ($response->getStatusCode() === 500) {
                Log::error('HTTP 500 Error detected', $this->context($request));
            }

            return $response;
        } catch (\Throwable $e) {
            Log::error('Unhandled exception', array_merge($this->context($request), [
                'exception' => $e->getMessage(),
            ]));

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function context(Request $request): array
    {
        return [
            'url' => $request->url(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'request_data' => $request->except($this->sensitiveKeys),
        ];
    }
}
