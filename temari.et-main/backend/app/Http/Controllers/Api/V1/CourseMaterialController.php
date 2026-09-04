<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CourseMaterialResource;
use App\Models\CourseMaterial;
use App\Models\GradeLevel;
use App\Models\SubjectAssignment;
use App\Models\User;
use App\Services\Notify\Notifier;
use App\Support\SearchTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Learning materials — staff lane (ADR-016). Teachers post to their own
 * classes (targets); directors/principals post subject + grade-window wide;
 * Temari.et staff post platform library rows for exam prep (`?platform=1`).
 * One row of truth per material — never per-section copies. v1 video =
 * YouTube embed or R2 file behind signed URLs.
 */
class CourseMaterialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = CourseMaterial::query()
            ->with(['subject:id,name', 'branch:id,name', 'creator:id,name', 'targets.subjectAssignment.section:id,name'])
            ->orderByDesc('is_pinned')
            ->orderByDesc('id');

        if ($request->boolean('platform')) {
            abort_unless($user->hasPlatformPermission('exam_prep.manage'), 403);
            $query->whereNull('school_id');
        } elseif ($request->filled('subject_assignment_id')) {
            // One class's stream: targeted rows ∪ window rows for its grade+subject.
            $anchor = SubjectAssignment::with('section.gradeLevel')->findOrFail($request->integer('subject_assignment_id'));
            abort_unless($this->mayViewAnchor($request, $anchor), 403);
            $query->where(
                fn ($q) => $q
                ->whereHas('targets', fn ($t) => $t->where('subject_assignment_id', $anchor->id))
                ->orWhere(fn ($w) => $this->windowMatch($w, $anchor)),
            );
        } else {
            $branch = $this->activeBranchOrNull($request);
            $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);
            // Platform staff have no active school context of their own — the
            // materials filter lets them browse one school explicitly instead.
            if ($schoolId === null && $user->isPlatformUser() && $request->filled('school_id')) {
                $schoolId = $request->integer('school_id');
            }
            abort_if($schoolId === null, 422, 'Select a school context to view materials.');

            if ($user->hasPermissionForScope('lms.view', $schoolId, $branch?->id)) {
                $query->where('school_id', $schoolId)
                    ->when($branch !== null, fn ($q) => $q->where(
                        fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $branch->id),
                    ))
                    ->when($this->branchFilterId($request, $branch), fn ($q, $id) => $q->where(
                        fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $id),
                    ));
            } elseif ($user->hasPermissionForScope('lms.manage_own', $schoolId, $branch?->id)) {
                // Teachers: what they posted + what reaches their classes.
                $query->where(
                    fn ($q) => $q
                    ->where('created_by', $user->id)
                    ->orWhereHas('targets.subjectAssignment.employee', fn ($e) => $e->where('user_id', $user->id)),
                );
            } else {
                abort(403);
            }
        }

        $materials = $query
            ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', $request->integer('subject_id')))
            ->when($request->filled('grade_level_id'), fn ($q) => $this->applyGradeFilter($q, $request->integer('grade_level_id')))
            ->when($request->filled('section_id'), fn ($q) => $q->whereHas(
                'targets.subjectAssignment.section',
                fn ($s) => $s->where('sections.id', $request->integer('section_id')),
            ))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when(! $request->boolean('all'), fn ($q) => $q->where('is_active', true))
            ->tap(fn ($query) => SearchTerm::apply($query, $request->string('q')->trim()->value(), fn ($w, string $n) => $w
                ->where('title', 'ilike', SearchTerm::contains($n))))
            ->paginate(min($request->integer('per_page', 25), 100));

        return CourseMaterialResource::collection($materials)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'type' => ['required', Rule::in(CourseMaterial::TYPES)],
            'url' => ['required_if:type,link,youtube', 'nullable', 'url', 'max:2000'],
            'body' => ['required_if:type,text', 'nullable', 'string', 'max:50000'],
            'file' => ['required_if:type,file', 'nullable', 'file', 'max:51200',
                'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png,webp,mp3,mp4,zip'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'min_grade_sort' => ['nullable', 'integer', 'min:1', 'max:50'],
            'max_grade_sort' => ['nullable', 'integer', 'min:1', 'max:50'],
            // Materials aren't subject-locked like exams, so an audience of grade+sections
            // naturally fans out across every subject taught there — allow a wide net.
            'subject_assignment_ids' => ['sometimes', 'array', 'max:1000'],
            'subject_assignment_ids.*' => ['integer', 'exists:subject_assignments,id'],
            'is_pinned' => ['sometimes', 'boolean'],
            'platform' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $targetIds = collect($data['subject_assignment_ids'] ?? []);

        if ($request->boolean('platform')) {
            abort_unless($user->hasPlatformPermission('exam_prep.manage'), 403);
            $schoolId = null;
            $branchId = null;
            $targetIds = collect();
        } elseif ($targetIds->isNotEmpty()) {
            // Teacher (or manager) posting to specific classes.
            $anchors = SubjectAssignment::query()->findMany($targetIds);
            abort_unless($anchors->count() === $targetIds->count(), 422);

            foreach ($anchors as $anchor) {
                abort_unless($this->mayManageAnchor($request, $anchor), 403);
            }

            $schoolId = (int) $anchors->first()->school_id;
            $branchId = (int) $anchors->first()->branch_id;
            abort_unless(
                $anchors->every(fn (SubjectAssignment $a): bool => (int) $a->school_id === $schoolId),
                422,
                'All target classes must belong to one school.',
            );
        } else {
            // Grade-window post — supervisory only, branch- or school-wide.
            if ($request->boolean('school_wide')) {
                $branch = $this->activeBranchOrNull($request);
                $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);
                abort_if($schoolId === null, 422, 'Select a school context to post materials.');
                abort_unless($user->hasPermissionForScope('lms.manage', $schoolId, null), 403);
                $branchId = null;
            } else {
                $branch = $this->targetBranch($request);
                $schoolId = $branch->school_id;
                $branchId = $branch->id;
                abort_unless($user->hasPermissionForScope('lms.manage', $schoolId, $branchId), 403);
            }
        }

        $material = CourseMaterial::create([
            'school_id' => $schoolId,
            'branch_id' => $branchId,
            'subject_id' => $data['subject_id'] ?? null,
            'min_grade_sort' => $data['min_grade_sort'] ?? null,
            'max_grade_sort' => $data['max_grade_sort'] ?? null,
            'title' => $data['title'],
            'description' => $this->cleanRichText($data['description'] ?? null),
            'type' => $data['type'],
            'content' => $this->buildContent($request, $data),
            'is_pinned' => $request->boolean('is_pinned'),
            'created_by' => $user->id,
        ]);

        foreach ($targetIds as $id) {
            $material->targets()->create(['subject_assignment_id' => $id]);
        }

        // Targeted class posts announce to their students (in-app, queued).
        // Grade-window and platform posts stay quiet — students find them in
        // the library; broadcasting a whole school per upload is noise.
        if ($targetIds->isNotEmpty()) {
            $sectionIds = SubjectAssignment::query()->whereIn('id', $targetIds)->pluck('section_id')->unique();

            $students = User::query()
                ->whereHas('studentProfile.enrollments', fn ($q) => $q
                    ->whereIn('section_id', $sectionIds)
                    ->where('status', EnrollmentStatus::Active->value))
                ->get();

            app(Notifier::class)->toUsers($students, 'lms.material_published', [
                'title' => $material->title,
                'subject' => $material->subject?->name ?? '',
            ], [
                'link' => '/me/learn?tab=materials',
                'schoolId' => $schoolId,
                'branchId' => $branchId,
            ]);
        }

        return (new CourseMaterialResource($material->load(['subject:id,name', 'targets.subjectAssignment.section:id,name'])))
            ->additional(['message' => 'Material posted.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, CourseMaterial $courseMaterial): JsonResponse
    {
        $this->authorize('update', $courseMaterial);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'url' => ['sometimes', 'nullable', 'url', 'max:2000'],
            'body' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'min_grade_sort' => ['nullable', 'integer', 'min:1', 'max:50'],
            'max_grade_sort' => ['nullable', 'integer', 'min:1', 'max:50'],
            'is_pinned' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            // Materials aren't subject-locked like exams, so an audience of grade+sections
            // naturally fans out across every subject taught there — allow a wide net.
            'subject_assignment_ids' => ['sometimes', 'array', 'max:1000'],
            'subject_assignment_ids.*' => ['integer', 'exists:subject_assignments,id'],
        ]);

        if (array_key_exists('description', $data)) {
            $data['description'] = $this->cleanRichText($data['description']);
        }

        $content = $courseMaterial->content;
        if ($courseMaterial->type === 'link' && array_key_exists('url', $data)) {
            $content = ['url' => $data['url']];
        } elseif ($courseMaterial->type === 'youtube' && array_key_exists('url', $data)) {
            $content = $this->youtubeContent((string) $data['url']);
        } elseif ($courseMaterial->type === 'text' && array_key_exists('body', $data)) {
            $content = ['body' => $this->cleanRichText($data['body'])];
        }

        $courseMaterial->update([
            ...collect($data)->only([
                'title', 'description', 'subject_id', 'min_grade_sort',
                'max_grade_sort', 'is_pinned', 'is_active',
            ])->all(),
            'content' => $content,
        ]);

        if (isset($data['subject_assignment_ids'])) {
            $ids = collect($data['subject_assignment_ids']);
            foreach (SubjectAssignment::query()->findMany($ids) as $anchor) {
                abort_unless($this->mayManageAnchor($request, $anchor), 403);
            }

            $courseMaterial->targets()->whereNotIn('subject_assignment_id', $ids)->delete();
            foreach ($ids as $id) {
                $courseMaterial->targets()->firstOrCreate(['subject_assignment_id' => $id]);
            }
        }

        return (new CourseMaterialResource(
            $courseMaterial->refresh()->load(['subject:id,name', 'targets.subjectAssignment.section:id,name']),
        ))->response();
    }

    public function destroy(CourseMaterial $courseMaterial): JsonResponse
    {
        $this->authorize('delete', $courseMaterial);

        if ($courseMaterial->type === 'file') {
            $path = data_get($courseMaterial->content, 'path');
            if ($path !== null) {
                Storage::disk(config('filesystems.default'))->delete($path);
            }
        }

        $courseMaterial->targets()->delete();
        $courseMaterial->delete();

        return response()->json(['message' => 'Material deleted.']);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function buildContent(Request $request, array $data): array
    {
        return match ($data['type']) {
            'link' => ['url' => $data['url']],
            'youtube' => $this->youtubeContent((string) $data['url']),
            'text' => ['body' => $this->cleanRichText($data['body'])],
            'file' => $this->storeFile($request),
        };
    }

    private function storeFile(Request $request): array
    {
        $file = $request->file('file');

        $path = $file->store('lms/materials', ['disk' => config('filesystems.default')]);

        return [
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ];
    }

    /** Accepts any YouTube URL shape and stores the bare video id. */
    private function youtubeContent(string $url): array
    {
        preg_match(
            '~(?:youtube\.com/(?:watch\?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{6,20})~',
            $url,
            $matches,
        );

        if (! isset($matches[1])) {
            throw ValidationException::withMessages(['url' => ['Paste a valid YouTube link.']]);
        }

        return ['video_id' => $matches[1], 'url' => $url];
    }

    /** Grade filter for the list: targeted rows in that grade, or window rows whose band covers it. */
    private function applyGradeFilter($query, int $gradeLevelId): void
    {
        $gradeSort = GradeLevel::find($gradeLevelId)?->sort_order;

        $query->where(
            fn ($q) => $q
            ->whereHas('targets.subjectAssignment.section', fn ($s) => $s->where('grade_level_id', $gradeLevelId))
            ->when($gradeSort !== null, fn ($q2) => $q2->orWhere(
                fn ($w) => $w
                ->whereDoesntHave('targets')
                ->where(fn ($y) => $y->whereNull('min_grade_sort')->orWhere('min_grade_sort', '<=', $gradeSort))
                ->where(fn ($y) => $y->whereNull('max_grade_sort')->orWhere('max_grade_sort', '>=', $gradeSort)),
            )),
        );
    }

    /** Grade-window rows that reach this class (same school, branch or school-wide). */
    private function windowMatch($query, SubjectAssignment $anchor): void
    {
        $gradeSort = $anchor->section?->gradeLevel?->sort_order;

        $query->where('school_id', $anchor->school_id)
            ->whereDoesntHave('targets')
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $anchor->branch_id))
            ->where(fn ($q) => $q->whereNull('subject_id')->orWhere('subject_id', $anchor->subject_id))
            ->when(
                $gradeSort !== null,
                fn ($q) => $q
                ->where(fn ($w) => $w->whereNull('min_grade_sort')->orWhere('min_grade_sort', '<=', $gradeSort))
                ->where(fn ($w) => $w->whereNull('max_grade_sort')->orWhere('max_grade_sort', '>=', $gradeSort)),
            );
    }

    private function mayViewAnchor(Request $request, SubjectAssignment $anchor): bool
    {
        $user = $request->user();

        return $user->hasPermissionForScope('lms.view', $anchor->school_id, $anchor->branch_id)
            || ($user->hasPermissionForScope('lms.manage_own', $anchor->school_id, $anchor->branch_id)
                && $anchor->isOwnedBy($user));
    }

    private function mayManageAnchor(Request $request, SubjectAssignment $anchor): bool
    {
        $user = $request->user();

        return $user->hasPermissionForScope('lms.manage', $anchor->school_id, $anchor->branch_id)
            || ($user->hasPermissionForScope('lms.manage_own', $anchor->school_id, $anchor->branch_id)
                && $anchor->isOwnedBy($user));
    }
}
