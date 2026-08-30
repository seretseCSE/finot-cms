<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Content Security Policy
        $csp = $this->buildContentSecurityPolicy();
        $response->headers->set('Content-Security-Policy', $csp);

        // HTTP Strict Transport Security (HSTS)
        if (app()->environment('production') && $request->secure()) {
            $hsts = 'max-age=31536000; includeSubDomains; preload';
            $response->headers->set('Strict-Transport-Security', $hsts);
        }

        // X-Frame-Options
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Additional security headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }

    /**
     * Build Content Security Policy based on application needs
     *
     * @return string
     */
    private function buildContentSecurityPolicy(): string
    {
        $directives = [
            // Default to self for most content types
            "default-src 'self'",
            
            // Script sources - allow self, inline for Filament, and CDN for assets
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://unpkg.com",
            
            // Style sources - allow self, inline, and CDN
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
            
            // Image sources - allow self, data URIs, and external image services
            "img-src 'self' data: https: blob:",
            
            // Font sources - allow self and Google Fonts
            "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net",
            
            // Connect sources - allow self and API endpoints
            "connect-src 'self'",
            
            // Frame sources - needed for YouTube/Vimeo embeds on public pages
            "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com https://vimeo.com https://www.google.com",

            // Frame ancestors - restrict to same origin
            "frame-ancestors 'self'",
            
            // Base URI - restrict to same origin
            "base-uri 'self'",
            
            // Form action - restrict to same origin
            "form-action 'self'",
            
            // Media sources - allow HTTPS for direct video/audio URLs
            "media-src 'self' https: blob:",
            
            // Object sources
            "object-src 'none'",
            
            // Worker sources
            "worker-src 'self'",
            
            // Manifest
            "manifest-src 'self'",
        ];

        return implode('; ', $directives);
    }
}
