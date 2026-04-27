<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportDownloadController extends Controller
{
    public function __invoke(string $filename): StreamedResponse
    {
        $path = "exports/{$filename}";

        abort_if(! Storage::disk('local')->exists($path), 404, 'Export file not found.');

        return Storage::disk('local')->download($path);
    }
}
