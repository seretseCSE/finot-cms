<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\TutorReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The PUBLIC tutor directory (no auth — this is the marketplace's SEO
 * storefront, like an Upwork search page). Approved profiles only; boosted
 * tutors rank first (paid placement, badged as "Featured" in the UI), then
 * rating, then volume. Contact details never leave this endpoint — hiring
 * goes through the authenticated request lane.
 */
class TutorDirectoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'subject_id' => ['nullable', 'integer'],
            'grade_sort' => ['nullable', 'integer'],
            'mode' => ['nullable', Rule::in(['online', 'in_person'])],
            'city' => ['nullable', 'string', 'max:80'],
            'max_rate' => ['nullable', 'numeric', 'min:0'],
            'min_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'sort' => ['nullable', Rule::in(['recommended', 'rating', 'price_low', 'price_high', 'experience'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);

        $query = TutorProfile::query()
            ->publiclyListed()
            ->with(['user:id,name,avatar_path', 'subjects.subject:id,name,code'])
            ->when(filled($data['search'] ?? null), function ($q) use ($data): void {
                $term = trim($data['search']);
                $q->where(fn ($w) => $w
                    ->where('headline', 'ilike', "%{$term}%")
                    ->orWhere('city', 'ilike', "%{$term}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'ilike', "%{$term}%"))
                    ->orWhereHas('subjects.subject', fn ($s) => $s->where('name', 'ilike', "%{$term}%")));
            })
            ->when(filled($data['subject_id'] ?? null), function ($q) use ($data): void {
                $q->whereHas('subjects', function ($s) use ($data): void {
                    $s->where('subject_id', (int) $data['subject_id']);

                    if (filled($data['grade_sort'] ?? null)) {
                        // Empty grade set = every grade; else the sort must be listed.
                        $s->where(fn ($g) => $g
                            ->whereNull('grade_sorts')
                            ->orWhereJsonLength('grade_sorts', 0)
                            ->orWhereJsonContains('grade_sorts', (int) $data['grade_sort']));
                    }
                });
            })
            ->when(filled($data['mode'] ?? null), fn ($q) => $q->whereIn('mode', [$data['mode'], 'both']))
            ->when(filled($data['city'] ?? null), fn ($q) => $q->where('city', 'ilike', '%'.$data['city'].'%'))
            ->when(filled($data['max_rate'] ?? null), fn ($q) => $q->where('hourly_rate', '<=', (float) $data['max_rate']))
            ->when(filled($data['min_rating'] ?? null), fn ($q) => $q->where('rating_avg', '>=', (float) $data['min_rating']));

        // Boosted-first is the recommended ranking's FIRST key (paid
        // placement); the alternates keep it as a soft nudge only.
        $boosted = 'CASE WHEN boosted_until IS NOT NULL AND boosted_until > NOW() THEN 0 ELSE 1 END';

        match ($data['sort'] ?? 'recommended') {
            'rating' => $query->orderByRaw('rating_avg DESC NULLS LAST')->orderByDesc('rating_count'),
            'price_low' => $query->orderBy('hourly_rate'),
            'price_high' => $query->orderByDesc('hourly_rate'),
            'experience' => $query->orderByRaw('experience_years DESC NULLS LAST')->orderByDesc('hours_taught'),
            default => $query->orderByRaw($boosted)
                ->orderByRaw('rating_avg DESC NULLS LAST')
                ->orderByDesc('rating_count')
                ->orderByDesc('hours_taught'),
        };

        $page = $query->paginate((int) ($data['per_page'] ?? 12));

        $page->getCollection()->transform(fn (TutorProfile $t) => $this->cardPayload($t));

        return response()->json($page);
    }

    /** One public profile by slug — the tutor's storefront page. */
    public function show(string $slug): JsonResponse
    {
        $profile = TutorProfile::query()
            ->publiclyListed()
            ->where('slug', $slug)
            ->with(['user:id,name,avatar_path', 'subjects.subject:id,name,code'])
            ->firstOrFail();

        $reviews = TutorReview::query()
            ->where('tutor_profile_id', $profile->id)
            ->where('direction', TutorReview::FAMILY_TO_TUTOR)
            ->where('is_public', true)
            ->with('reviewer:id,name')
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (TutorReview $r) => [
                'rating' => $r->rating,
                'comment' => $r->comment,
                // First name only — reviewers are families, not public figures.
                'reviewer' => explode(' ', trim((string) $r->reviewer?->name))[0] ?? null,
                'created_at' => $r->created_at?->toDateString(),
            ]);

        return response()->json(['data' => array_merge($this->cardPayload($profile), [
            'bio' => $profile->bio,
            'video_url' => $profile->video_url,
            'education_level' => $profile->education_level,
            'additional_child_rate' => $profile->additional_child_rate,
            'reviews' => $reviews,
        ])]);
    }

    /** Filter facets for the directory UI. */
    public function meta(): JsonResponse
    {
        $subjects = Subject::query()
            ->whereNull('school_id')
            ->whereHas('tutorSubjects')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $cities = TutorProfile::query()
            ->publiclyListed()
            ->whereNotNull('city')
            ->distinct()
            ->orderBy('city')
            ->pluck('city');

        return response()->json(['data' => [
            'subjects' => $subjects,
            'cities' => $cities,
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function cardPayload(TutorProfile $profile): array
    {
        return [
            'slug' => $profile->slug,
            'name' => $profile->user?->name,
            'avatar_url' => $profile->user?->avatarUrl(),
            'headline' => $profile->headline,
            'hourly_rate' => $profile->hourly_rate,
            'mode' => $profile->mode,
            'city' => $profile->city,
            'sub_city' => $profile->sub_city,
            'languages' => $profile->languages ?? [],
            'experience_years' => $profile->experience_years,
            'rating_avg' => $profile->rating_avg,
            'rating_count' => $profile->rating_count,
            'hours_taught' => (string) $profile->hours_taught,
            'students_count' => $profile->students_count,
            'boosted' => $profile->isBoosted(),
            'verified' => true, // approved = Fayda + documents vetted
            'subjects' => $profile->subjects->map(fn ($row) => [
                'subject_id' => $row->subject_id,
                'name' => $row->subject?->name,
                'grade_sorts' => $row->grade_sorts ?? [],
            ])->values(),
        ];
    }
}
