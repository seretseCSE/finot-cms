<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseLesson;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $categories = CourseCategory::whereNull('parent_id')
            ->where('status', 'Active')
            ->orderBy('display_order')
            ->get();

        $search = $request->input('search');

        $courses = Course::where('status', 'Published')
            ->with('category')
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('title_am', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('public.courses.index', compact('categories', 'courses', 'search'));
    }

    public function browse(string $slug)
    {
        $category = CourseCategory::where('slug', $slug)
            ->where('status', 'Active')
            ->firstOrFail();

        $subcategories = $category->activeChildren;
        $courses = $category->activeCourses()->latest()->paginate(12)->withQueryString();

        return view('public.courses.browse', compact('category', 'subcategories', 'courses'));
    }

    public function show(int $id)
    {
        $course = Course::with(['category', 'activeLessons'])->findOrFail($id);

        if ($course->status !== 'Published') {
            abort(404);
        }

        return view('public.courses.show', compact('course'));
    }

    public function lesson(Course $course, CourseLesson $lesson)
    {
        if ($course->status !== 'Published' || $lesson->status !== 'Published') {
            abort(404);
        }

        if ($lesson->course_id !== $course->id) {
            abort(404);
        }

        $lessons = $course->activeLessons;

        $prev = $lessons->where('display_order', '<', $lesson->display_order)->sortByDesc('display_order')->first();
        $next = $lessons->where('display_order', '>', $lesson->display_order)->sortBy('display_order')->first();

        return view('public.courses.lesson', compact('course', 'lesson', 'lessons', 'prev', 'next'));
    }
}
