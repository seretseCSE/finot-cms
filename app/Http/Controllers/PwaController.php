<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PwaController extends Controller
{
    /**
     * Serve service worker for PWA.
     */
    public function serviceWorker(): Response
    {
        $path = public_path('service-worker.js');
        $content = file_exists($path) ? file_get_contents($path) : '';

        return response($content, 200, [
            'Content-Type' => 'application/javascript',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    /**
     * Get build information.
     */
    public function buildInfo(): JsonResponse
    {
        $path = public_path('build-info.json');
        $buildInfo = file_exists($path) ? json_decode(file_get_contents($path), true) : [
            'timestamp' => time(),
            'hash' => 'dev-' . substr(md5(time()), 0, 8),
            'assets' => []
        ];

        return response()->json($buildInfo);
    }

    /**
     * Serve the web app manifest.
     */
    public function manifest(): JsonResponse
    {
        $buildInfo = $this->getBuildInfo();

        return response()->json([
            'name' => 'FINOTE TSIDIK - Church Management System',
            'short_name' => 'FINOTE',
            'description' => 'Complete church management system for FINOTE TSIDIK',
            'start_url' => '/',
            'display' => 'standalone',
            'background_color' => '#1B4F72',
            'theme_color' => '#1B4F72',
            'orientation' => 'portrait-primary',
            'scope' => '/',
            'lang' => 'en',
            'categories' => ['business', 'productivity', 'utilities'],
            'version' => $buildInfo['hash'],
            'icons' => [
                [
                    'src' => asset('images/logo.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
                [
                    'src' => asset('images/logo.png'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
        ]);
    }

    /**
     * Get build info from file or default.
     */
    private function getBuildInfo(): array
    {
        $path = public_path('build-info.json');
        return file_exists($path) ? json_decode(file_get_contents($path), true) : [
            'timestamp' => time(),
            'hash' => 'dev-' . substr(md5(time()), 0, 8),
            'assets' => []
        ];
    }

    /**
     * Show offline fallback page.
     */
    public function offline(): Response
    {
        $appName = config('app.name', 'FINOTE TSIDIK');
        $logoUrl = asset('images/logo.png');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - {$appName}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: #f8f9fa;
            color: #212529;
            padding: 1rem;
        }
        .card {
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            max-width: 420px;
            width: 100%;
            padding: 2.5rem;
            text-align: center;
        }
        .logo {
            width: 64px;
            height: 64px;
            margin: 0 auto 1.25rem;
            object-fit: contain;
        }
        h1 { font-size: 1.5rem; font-weight: 700; margin: 0 0 0.5rem; color: #1B4F72; }
        p { font-size: 1rem; line-height: 1.6; color: #6c757d; margin: 0 0 1.5rem; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: #1B4F72;
            color: #fff;
            border: none;
            border-radius: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s ease;
        }
        .btn:hover { background: #154360; }
        .btn svg { width: 1rem; height: 1rem; }
        .hint { margin-top: 1rem; font-size: 0.875rem; color: #adb5bd; }
    </style>
</head>
<body>
    <div class="card">
        <img src="{$logoUrl}" alt="" class="logo">
        <h1>You are offline</h1>
        <p>Please check your internet connection and try again. Some pages may still be available if they were visited recently.</p>
        <button class="btn" onclick="location.reload()">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.051M20.418 9A9.96 9.96 0 0012 4c-4.477 0-8.268 2.943-9.542 7M20 20v-5h-.051M3.582 15A9.96 9.96 0 0012 20c4.477 0 8.268-2.943 9.542-7" />
            </svg>
            Retry
        </button>
        <div class="hint">{$appName}</div>
    </div>
    <script>
        window.addEventListener('online', function () {
            location.reload();
        });
    </script>
</body>
</html>
HTML;

        return response($html, 200, [
            'Content-Type' => 'text/html',
        ]);
    }
}
