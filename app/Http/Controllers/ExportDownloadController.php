<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportDownloadController extends Controller
{
    public function __invoke(Request $request, string $filename): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'Invalid or expired download link.');

        $filename = basename(str_replace('\\', '/', $filename));

        abort_unless(
            (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.(xlsx|csv|pdf)$/i', $filename),
            404
        );

        $path = "exports/{$filename}";

        abort_if(! Storage::disk('local')->exists($path), 404, 'Export file not found.');

        return Storage::disk('local')->download($path);
    }

    /**
     * Generate a signed download URL for an export file (valid for 30 minutes).
     */
    public static function signedUrl(string $filename): string
    {
        return URL::temporarySignedRoute(
            'exports.download',
            now()->addMinutes(30),
            ['filename' => $filename]
        );
    }
}
