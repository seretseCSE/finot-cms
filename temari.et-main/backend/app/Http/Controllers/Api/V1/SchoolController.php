<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateSchoolAction;
use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSchoolRequest;
use App\Http\Requests\Api\V1\UpdateSchoolRequest;
use App\Http\Resources\SchoolResource;
use App\Models\School;
use App\Models\SchoolDirectoryEntry;
use App\Services\OrgStatsService;
use App\Support\DateFormatter;
use App\Support\JobTitles;
use App\Support\ReportCardSettings;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SchoolController extends Controller
{
    use HandlesListQueries;

    /** Maximum rows returned by a single export request. */
    private const EXPORT_LIMIT = 10000;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', School::class);

        $query = $this->baseQuery($request);
        $this->applySort(
            $query,
            $request,
            ['name', 'branches_count', 'students_count', 'teachers_count', 'is_active', 'created_at'],
            'created_at',
        );

        return SchoolResource::collection($query->paginate($this->perPage($request))->withQueryString());
    }

    public function export(Request $request): AnonymousResourceCollection
    {
        $this->authorize('export', School::class);

        $schools = $this->baseQuery($request)
            ->orderBy('name')
            ->limit(self::EXPORT_LIMIT)
            ->get();

        return SchoolResource::collection($schools);
    }

    public function store(StoreSchoolRequest $request, CreateSchoolAction $action): JsonResponse
    {
        $this->authorize('create', School::class);

        $school = $action->execute($request->validated());

        return (new SchoolResource($school->loadCount('branches')))
            ->additional(['message' => 'School created. The principal has been notified by SMS.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(School $school): SchoolResource
    {
        $this->authorize('view', $school);

        return new SchoolResource($school->loadCount('branches')->load('contactMemberships.user'));
    }

    /**
     * Aggregated profile vitals (students, guardians, workforce by job title,
     * subjects taught, per-grade picture, per-branch rollup). Visible to
     * whoever may open the school profile.
     */
    public function stats(School $school, OrgStatsService $stats): JsonResponse
    {
        $this->authorize('view', $school);

        return response()->json(['data' => $stats->forSchool($school)]);
    }

    public function update(UpdateSchoolRequest $request, School $school): SchoolResource
    {
        $this->authorize('update', $school);

        $school->update($request->validated());

        // Keep the platform school directory in step with renames.
        SchoolDirectoryEntry::query()
            ->where('school_id', $school->id)
            ->update(['name' => $school->name]);

        return new SchoolResource($school->loadCount('branches')->load('contactMemberships.user'));
    }

    public function destroy(School $school): JsonResponse
    {
        $this->authorize('delete', $school);

        $school->delete();

        return response()->json(['message' => 'School deleted.']);
    }

    /**
     * Set the official school logo. Platform staff ONLY (SchoolPolicy@manageLogo)
     * — it prints on official documents, so schools request changes rather
     * than self-serving.
     */
    public function logo(Request $request, School $school): JsonResponse
    {
        $this->authorize('manageLogo', $school);

        $request->validate([
            'logo' => ['required', 'image', 'max:2048'],
        ]);

        if ($school->logo_path !== null) {
            Storage::disk(config('filesystems.default'))->delete($school->logo_path);
        }

        $path = $request->file('logo')->store(
            "school-logos/{$school->id}",
            ['disk' => config('filesystems.default')],
        );

        $school->forceFill(['logo_path' => $path])->save();

        return response()->json([
            'data' => ['logo_url' => $school->logoUrl()],
            'message' => 'School logo updated.',
        ]);
    }

    /** Remove the school logo (platform staff only). */
    public function destroyLogo(School $school): JsonResponse
    {
        $this->authorize('manageLogo', $school);

        if ($school->logo_path !== null) {
            Storage::disk(config('filesystems.default'))->delete($school->logo_path);
            $school->forceFill(['logo_path' => null])->save();
        }

        return response()->json(['message' => 'School logo removed.']);
    }

    /**
     * Academic policy knobs — editable by the school's own managers (unlike
     * school CRUD, which is platform territory).
     */
    public function updateSettings(Request $request, School $school): SchoolResource
    {
        $this->authorize('updateSettings', $school);

        $data = $request->validate([
            'registration_gate' => ['sometimes', 'in:soft,hard'],
            // Calendar & clock: how every date/time DISPLAYS for this school
            // (storage is always Gregorian/UTC; official PDFs print both).
            'calendar_mode' => ['sometimes', 'in:ethiopian,gregorian'],
            'clock_mode' => ['sometimes', 'in:standard,ethiopian'],
            'promotion_threshold' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'teacher_assessments_enabled' => ['sometimes', 'boolean'],
            // Which job titles come with a portal account at hire; the four
            // role-mapped titles are forced back in server-side.
            'employee_account_job_titles' => ['sometimes', 'array'],
            'employee_account_job_titles.*' => [Rule::in(JobTitles::ALL)],
            'attendance_sms_enabled' => ['sometimes', 'boolean'],
            'attendance_sms_late' => ['sometimes', 'boolean'],
            'device_auto_absent' => ['sometimes', 'boolean'],
            'device_absent_cutoff' => ['sometimes', 'date_format:H:i'],
            'device_late_grace' => ['sometimes', 'integer', 'min:0', 'max:120'],
            // Concession policy — 0 turns a policy off. Suggestions only;
            // finance approves each generated concession (no silent discounts).
            'sibling_discount_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'sibling_min_children' => ['sometimes', 'integer', 'min:2', 'max:10'],
            'staff_child_discount_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            // Billing: mid-period joiner policy + the automated reminder ladder.
            'fee_proration' => ['sometimes', 'in:full,daily'],
            'fee_reminders_enabled' => ['sometimes', 'boolean'],
            'fee_reminder_days_before' => ['sometimes', 'integer', 'min:0', 'max:30'],
            'fee_reminder_overdue_every' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'fee_reminder_overdue_max' => ['sometimes', 'integer', 'min:0', 'max:10'],
            // Finance controls. School-scope ONLY (this endpoint is gated by
            // managesSchool) so a branch director can never flip them.
            'finance_self_approval' => ['sometimes', 'boolean'],
            'director_finance_access' => ['sometimes', 'boolean'],
            // Lesson planning: department heads join the review chain.
            'lesson_plan_department_review' => ['sometimes', 'boolean'],
            // Chat (ADR-019): the communication-book gate (teacher→parent
            // messages wait for a director) + student participation.
            'chat_teacher_parent_approval' => ['sometimes', 'in:off,first,all'],
            'chat_students_enabled' => ['sometimes', 'boolean'],
            // Preset chat templates: convenience vs mandated wording.
            'chat_template_mode' => ['sometimes', 'in:suggested,required'],
            // Report cards: the behavioral skill checklist (empty = no panel),
            // compact 2-per-page printing, opt-in per-subject ranks and the
            // yearly grading-criteria legend.
            ...ReportCardSettings::skillRules(),
            'report_card_per_page' => ['sometimes', 'integer', 'in:1,2,4'],
            'report_card_subject_ranks' => ['sometimes', 'boolean'],
            'report_card_grading_criteria' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('report_card_skills', $data)) {
            $data['report_card_skills'] = ReportCardSettings::normalize($data['report_card_skills'] ?? []);
        }

        $school->update(['settings' => array_merge($school->settings ?? [], $data)]);

        // The kernel caches this flag — new authority applies immediately.
        Cache::forget("school:{$school->id}:director_finance_access");
        Cache::forget("school:{$school->id}:lesson_plan_department_review");
        // Display-mode cache. Branches that do NOT override the calendar/clock
        // inherit the school's, but are cached under their own key — forget
        // those too, or a branch would keep printing the old calendar for
        // minutes after the school switched it.
        Cache::forget("display-modes:{$school->id}:0");
        foreach ($school->branches()->pluck('id') as $branchId) {
            Cache::forget("display-modes:{$school->id}:{$branchId}");
        }
        DateFormatter::flushMemo();

        return new SchoolResource($school->loadCount('branches'));
    }

    /**
     * Base query with visibility scoping and all list filters applied.
     *
     * @return Builder<School>
     */
    private function baseQuery(Request $request): Builder
    {
        $user = $request->user();

        $query = School::query()
            ->withListStats()
            ->with('contactMemberships.user')
            ->when(! $user->isPlatformUser(), fn (Builder $q) => $q->whereIn('id', $user->accessibleSchoolIds()));

        $this->applySearch($query, $request, fn ($q, string $n) => $q
            ->where('name', 'ilike', $this->needle($n)));

        $this->applyBooleanFilter($query, $request, 'is_active', 'is_active');
        $this->applyDateRange($query, $request, 'created_at', 'created_from', 'created_to');

        if ($user->hasPlatformPermission('schools.delete')) {
            $this->applyTrashedFilter($query, $request);
        }

        return $query;
    }
}
