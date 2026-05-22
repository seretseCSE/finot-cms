<?php

namespace App\Http\Controllers;

use App\Models\LibraryResource;
use App\Models\Course;
use App\Models\CourseLesson;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $query = $request->input('q');
        $type = $request->input('type', 'all');

        if (empty($query)) {
            return redirect()->route('courses.index');
        }

        $libraryResults = collect();
        $courseResults = collect();
        $lessonResults = collect();

        if ($type === 'all' || $type === 'library') {
            $libraryResults = LibraryResource::where('is_active', true)
                ->where(function ($q) use ($query) {
                    $q->whereNotNull('content')->orWhereNotNull('content_am');
                })
                ->where(function ($q) use ($query) {
                    $q->where('title', 'like', "%{$query}%")
                        ->orWhere('content', 'like', "%{$query}%")
                        ->orWhere('content_am', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%");
                })
                ->with('category')
                ->limit(20)
                ->get();
        }

        if ($type === 'all' || $type === 'courses') {
            $courseResults = Course::where('status', 'Published')
                ->where(function ($q) use ($query) {
                    $q->where('title', 'like', "%{$query}%")
                        ->orWhere('title_am', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%")
                        ->orWhere('description_am', 'like', "%{$query}%");
                })
                ->with('category')
                ->limit(10)
                ->get();

            $lessonResults = CourseLesson::where('status', 'Published')
                ->where(function ($q) use ($query) {
                    $q->where('title', 'like', "%{$query}%")
                        ->orWhere('title_am', 'like', "%{$query}%")
                        ->orWhere('content', 'like', "%{$query}%")
                        ->orWhere('content_am', 'like', "%{$query}%");
                })
                ->with('course')
                ->limit(10)
                ->get();
        }

        return view('public.search', compact('query', 'type', 'libraryResults', 'courseResults', 'lessonResults'));
    }
}
