<?php

namespace App\Http\Controllers;

use App\Models\LibraryCategory;
use App\Models\LibraryResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LibraryController extends Controller
{
    public function index(Request $request)
    {
        $categories = LibraryCategory::where('status', 'Active')
            ->orderBy('name')
            ->get();

        $subcategories = null;
        if ($request->category) {
            $subcategories = \App\Models\LibrarySubcategory::where('category_id', $request->category)
                ->where('status', 'Active')
                ->orderBy('name')
                ->get();
        }

        $resources = LibraryResource::query()
            ->with(['category', 'subcategory'])
            ->where('is_active', true)
            ->when($request->category, function ($query, $category) {
                $query->where('category_id', $category);
            })
            ->when($request->subcategory, function ($query, $subcategory) {
                $query->where('subcategory_id', $subcategory);
            })
            ->when($request->search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $totalResources = LibraryResource::where('is_active', true)->count();
        $featuredResources = LibraryResource::where('is_active', true)->where('is_featured', true)->count();

        return view('public.library', compact('categories', 'subcategories', 'resources', 'totalResources', 'featuredResources'));
    }

    public function download(LibraryResource $resource)
    {
        if (! $resource->is_active) {
            abort(404);
        }

        $disk = Storage::disk('library');

        if (! $disk->exists($resource->file_path)) {
            \Log::error('Library file not found', [
                'resource_id' => $resource->id,
                'file_path' => $resource->file_path,
                'disk' => 'library',
            ]);
            abort(404, 'File not found');
        }

        $extension = pathinfo($resource->file_path, PATHINFO_EXTENSION);
        $downloadName = $resource->title.($extension ? '.'.$extension : '');

        return $disk->download($resource->file_path, $downloadName);
    }
}
