<?php

use App\Http\Controllers\Api\V1\AbsenceExcuseController;
use App\Http\Controllers\Api\V1\AcademicYearController;
use App\Http\Controllers\Api\V1\AccountLinkRequestController;
use App\Http\Controllers\Api\V1\AiActionController;
use App\Http\Controllers\Api\V1\AiChatController;
use App\Http\Controllers\Api\V1\AiConversationController;
use App\Http\Controllers\Api\V1\AiFeedbackController;
use App\Http\Controllers\Api\V1\AiPlanController;
use App\Http\Controllers\Api\V1\AiSubscriptionController;
use App\Http\Controllers\Api\V1\AssessmentController;
use App\Http\Controllers\Api\V1\AssetController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AttendanceNotificationLogController;
use App\Http\Controllers\Api\V1\AttendanceReportController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BankAccountController;
use App\Http\Controllers\Api\V1\BranchContactController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\BranchSettingsController;
use App\Http\Controllers\Api\V1\CardRequestController;
use App\Http\Controllers\Api\V1\Catalogs\BankCatalogController;
use App\Http\Controllers\Api\V1\Catalogs\CatalogOverviewController;
use App\Http\Controllers\Api\V1\Catalogs\GradeLevelCatalogController;
use App\Http\Controllers\Api\V1\Catalogs\HealthConditionCatalogController;
use App\Http\Controllers\Api\V1\Catalogs\NotificationEventCatalogController;
use App\Http\Controllers\Api\V1\Catalogs\SchoolDirectoryCatalogController;
use App\Http\Controllers\Api\V1\Catalogs\SubjectCatalogController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\ChatMessageController;
use App\Http\Controllers\Api\V1\ChatTemplateController;
use App\Http\Controllers\Api\V1\ContextController;
use App\Http\Controllers\Api\V1\ContinuousAssessmentController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\CourseMaterialController;
use App\Http\Controllers\Api\V1\DailyLessonPlanController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\DeviceEventController;
use App\Http\Controllers\Api\V1\DeviceGatewayController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\EmployeeAttachmentController;
use App\Http\Controllers\Api\V1\EmployeeAttendanceController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\EmployeeTeachingController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\FeeConcessionController;
use App\Http\Controllers\Api\V1\FeeReportController;
use App\Http\Controllers\Api\V1\FeeStructureController;
use App\Http\Controllers\Api\V1\FinanceBookController;
use App\Http\Controllers\Api\V1\FinanceCategoryController;
use App\Http\Controllers\Api\V1\GatewayTransactionController;
use App\Http\Controllers\Api\V1\GlobalSearchController;
use App\Http\Controllers\Api\V1\GradeLevelController;
use App\Http\Controllers\Api\V1\GradingPolicyController;
use App\Http\Controllers\Api\V1\GradingReportController;
use App\Http\Controllers\Api\V1\GradingScaleController;
use App\Http\Controllers\Api\V1\GuardianController;
use App\Http\Controllers\Api\V1\HealthConditionController;
use App\Http\Controllers\Api\V1\HolidayController;
use App\Http\Controllers\Api\V1\HrReportController;
use App\Http\Controllers\Api\V1\IdCardController;
use App\Http\Controllers\Api\V1\ImpersonationController;
use App\Http\Controllers\Api\V1\InventoryCategoryController;
use App\Http\Controllers\Api\V1\InventoryItemController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\LeaveRequestController;
use App\Http\Controllers\Api\V1\LeaveTypeController;
use App\Http\Controllers\Api\V1\LessonPlanController;
use App\Http\Controllers\Api\V1\LmsAssignmentController;
use App\Http\Controllers\Api\V1\MarklistController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MeCourseController;
use App\Http\Controllers\Api\V1\MeExamPrepController;
use App\Http\Controllers\Api\V1\MeLessonPlanController;
use App\Http\Controllers\Api\V1\MeLmsController;
use App\Http\Controllers\Api\V1\MembershipController;
use App\Http\Controllers\Api\V1\MeTransferController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OtherIncomeController;
use App\Http\Controllers\Api\V1\ParentAttachmentController;
use App\Http\Controllers\Api\V1\ParentController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaymentGatewayController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\Api\V1\PayrollController;
use App\Http\Controllers\Api\V1\PortalAccountController;
use App\Http\Controllers\Api\V1\ProgramController;
use App\Http\Controllers\Api\V1\PromotionController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\QuestionBankController;
use App\Http\Controllers\Api\V1\QuestionController;
use App\Http\Controllers\Api\V1\QuizController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RequisitionController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\RosterController;
use App\Http\Controllers\Api\V1\SchoolContactController;
use App\Http\Controllers\Api\V1\SchoolController;
use App\Http\Controllers\Api\V1\SchoolDirectoryController;
use App\Http\Controllers\Api\V1\SectionAssignmentController;
use App\Http\Controllers\Api\V1\SectionController;
use App\Http\Controllers\Api\V1\SignupController;
use App\Http\Controllers\Api\V1\StockMovementController;
use App\Http\Controllers\Api\V1\StockTakeController;
use App\Http\Controllers\Api\V1\StudentAttachmentController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\StudentEnrollmentController;
use App\Http\Controllers\Api\V1\StudentImportController;
use App\Http\Controllers\Api\V1\SubjectAssignmentController;
use App\Http\Controllers\Api\V1\SubjectController;
use App\Http\Controllers\Api\V1\TeacherAvailabilityController;
use App\Http\Controllers\Api\V1\TeacherEvaluationController;
use App\Http\Controllers\Api\V1\TermAssignmentController;
use App\Http\Controllers\Api\V1\TermController;
use App\Http\Controllers\Api\V1\TermPeriodController;
use App\Http\Controllers\Api\V1\TermResultController;
use App\Http\Controllers\Api\V1\TextbookController;
use App\Http\Controllers\Api\V1\TimetableController;
use App\Http\Controllers\Api\V1\TranscriptController;
use App\Http\Controllers\Api\V1\TransferApplicationController;
use App\Http\Controllers\Api\V1\TransferController;
use App\Http\Controllers\Api\V1\TutorAdminController;
use App\Http\Controllers\Api\V1\TutorBoostController;
use App\Http\Controllers\Api\V1\TutorDirectoryController;
use App\Http\Controllers\Api\V1\TutoringCycleController;
use App\Http\Controllers\Api\V1\TutoringEngagementController;
use App\Http\Controllers\Api\V1\TutoringRequestController;
use App\Http\Controllers\Api\V1\TutoringSessionController;
use App\Http\Controllers\Api\V1\TutorPayoutController;
use App\Http\Controllers\Api\V1\TutorProfileController;
use App\Http\Controllers\Api\V1\TutorReviewController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WeeklyLessonPlanController;
use App\Http\Middleware\AuthenticateDevice;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Login takes ONE identifier (phone or Temari student ID); per-identifier
    // rate limiting lives in the controller, this throttle is the IP backstop.
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:10,1');
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1');
    Route::post('auth/set-password', [AuthController::class, 'setPassword'])->middleware('throttle:10,1');
    // Self-signup (phone + OTP + PIN): activates a school-provisioned account
    // when the verified phone matches one, else creates a public B2C account.
    // OTP sends cost SMS money — throttled tight.
    Route::post('auth/signup/request-otp', [SignupController::class, 'requestOtp'])->middleware('throttle:5,1');
    Route::post('auth/signup', [SignupController::class, 'register'])->middleware('throttle:10,1');
    Route::post('auth/impersonate', [ImpersonationController::class, 'authenticate']);

    // PUBLIC tutor directory — the marketplace storefront (SEO pages).
    // Approved profiles only; hiring requires an account.
    Route::get('public/tutors/meta', [TutorDirectoryController::class, 'meta']);
    Route::get('public/tutors', [TutorDirectoryController::class, 'index']);
    Route::get('public/tutors/{slug}', [TutorDirectoryController::class, 'show']);

    // Payment gateway webhooks (Chapa/Telebirr…): signature-checked by the
    // driver, and NEVER trusted alone — the manager re-verifies server-side.
    Route::post('webhooks/payments/{gateway}', PaymentWebhookController::class)
        ->middleware('throttle:120,1')->name('webhooks.payments');

    // PUBLIC document verification — the QR code on a printed transfer
    // letter resolves here (unguessable token, approved letters only).
    Route::get('public/transfer-letters/{token}', [TransferController::class, 'publicLetter']);
    Route::get('public/withdrawal-letters/{token}', [StudentEnrollmentController::class, 'publicWithdrawalLetter']);
    // …and the QR on a printed payment receipt.
    Route::get('public/receipts/{token}', [PaymentController::class, 'publicReceipt']);
    // Unified verify lane for every backend-generated official PDF
    // (receipts, letters, transcripts, report cards, statements, payslips):
    // the QR on the document resolves here and proves authenticity without
    // exposing marks or pay.
    Route::get('public/documents/{token}', [DocumentController::class, 'verify']);
    // The transcript QR opens the LIVE record (full article, token-scoped).
    Route::get('public/transcripts/{token}', [TranscriptController::class, 'publicTranscript']);
    // The roster QR opens the LIVE roster sheet (token-scoped, killed by revoke).
    Route::get('public/rosters/{token}', [RosterController::class, 'publicRoster']);
    // The shareable device-integration docs: right token → 200, else 404.
    // Rotating/revoking the platform share setting kills old links instantly.
    Route::get('public/device-docs/{token}', [DeviceController::class, 'publicDocs'])->middleware('throttle:30,1');

    // MACHINE LANE: RFID terminals authenticate with their own bearer token
    // (see AuthenticateDevice), never via user accounts. Three verbs only:
    // phone home, pull the offline-verification roster, flush the scan queue.
    Route::prefix('device')->middleware(AuthenticateDevice::class)->group(function (): void {
        Route::post('heartbeat', [DeviceGatewayController::class, 'heartbeat']);
        Route::get('roster', [DeviceGatewayController::class, 'roster']);
        Route::post('events', [DeviceGatewayController::class, 'events']);
    });

    Route::middleware(['auth:sanctum', 'active.account', 'active.context'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        // ⌘K palette: permission-aware fan-out search in the active context.
        Route::get('search', GlobalSearchController::class);
        // The staff landing page: one aggregated, permission-adaptive payload.
        Route::get('dashboard', [DashboardController::class, 'show']);
        Route::get('auth/contexts', [ContextController::class, 'index']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // RELATIONSHIP LANE (ADR-012): access derived from guardian links /
        // being the student — never from memberships or context headers.
        Route::prefix('me')->group(function (): void {
            Route::get('children', [MeController::class, 'children']);
            Route::get('children/{student}/home', [MeController::class, 'childHome']);
            Route::get('children/{student}/result-card', [MeController::class, 'childResultCard']);
            Route::get('children/{student}/report-cards', [MeController::class, 'childReportCards']);
            Route::get('children/{student}/report-card', [MeController::class, 'childReportCard']);
            Route::get('children/{student}/transcript', [MeController::class, 'childTranscript']);
            Route::get('children/{student}/attendance-summary', [MeController::class, 'childAttendanceSummary']);
            Route::get('children/{student}/attendance', [MeController::class, 'childAttendance']);
            Route::get('children/{student}/absence-excuses', [MeController::class, 'childAbsenceExcuses']);
            Route::post('children/{student}/absence-excuses', [MeController::class, 'storeChildAbsenceExcuse'])->middleware('throttle:10,1');
            // Bank/wallet catalog (names + logos) for the payment form.
            Route::get('banks', [MeController::class, 'banks']);
            Route::get('children/{student}/invoices', [MeController::class, 'childInvoices']);
            Route::get('children/{student}/payments', [MeController::class, 'childPayments']);
            Route::get('children/{student}/upcoming-fees', [MeController::class, 'childUpcomingFees']);
            Route::post('children/{student}/invoices/{invoice}/verify-payment', [MeController::class, 'verifyChildInvoicePayment']);
            Route::get('children/{student}/timetable', [MeController::class, 'childTimetable']);
            Route::get('children/{student}/calendar', [MeController::class, 'childCalendar']);
            Route::get('children/{student}/teachers', [MeController::class, 'childTeachers']);
            Route::get('student/calendar', [MeController::class, 'ownCalendar']);
            Route::get('student/teachers', [MeController::class, 'ownTeachers']);
            Route::get('student', [MeController::class, 'student']);
            Route::get('student/result-card', [MeController::class, 'ownResultCard']);
            Route::get('student/report-cards', [MeController::class, 'ownReportCards']);
            Route::get('student/report-card', [MeController::class, 'ownReportCard']);
            Route::get('student/transcript', [MeController::class, 'ownTranscript']);
            Route::get('student/timetable', [MeController::class, 'ownTimetable']);
            Route::get('student/attendance', [MeController::class, 'ownAttendance']);
            Route::get('preferences', [MeController::class, 'preferences']);
            Route::put('preferences', [MeController::class, 'updatePreferences']);

            // The in-app notification feed (ADR-018) — every account type has
            // one; rows are strictly self-scoped. Static segments first.
            Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
            Route::get('notifications', [NotificationController::class, 'index']);
            Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);

            // Transfers in the relationship lane: track every movement of the
            // family's children + file/withdraw online transfer applications.
            Route::get('transfers', [MeTransferController::class, 'index']);
            Route::get('transfer-applications/destinations', [MeTransferController::class, 'destinations']);
            Route::post('transfer-applications', [MeTransferController::class, 'store']);
            Route::post('transfer-applications/{application}/withdraw', [MeTransferController::class, 'withdraw']);

            // LMS — the student's class feed (ADR-016): materials, homework
            // turn-ins, class exams; parents get per-child summaries gated by
            // their guardian-link flags.
            Route::get('lms/overview', [MeLmsController::class, 'overview']);
            Route::get('lms/materials', [MeLmsController::class, 'materials']);
            Route::get('lms/assignments', [MeLmsController::class, 'assignments']);
            Route::get('lms/assignments/{assignment}', [MeLmsController::class, 'showAssignment']);
            Route::post('lms/assignments/{assignment}/submit', [MeLmsController::class, 'submitAssignment']);
            Route::post('lms/assignments/{assignment}/remove-file', [MeLmsController::class, 'removeSubmissionFile']);
            // The teacher thread is a chat CONTEXT conversation (ADR-019);
            // this resolves it, then /me/chat/* drives the messages.
            Route::get('lms/assignments/{assignment}/thread', [MeLmsController::class, 'assignmentThread']);
            Route::post('lms/uploads', [MeLmsController::class, 'upload']);
            Route::get('lms/exams', [MeLmsController::class, 'exams']);
            Route::get('lms/attempts', [MeLmsController::class, 'myAttempts']);

            // Lesson plans, family side: the approved syllabus roadmap +
            // this week's approved topics per subject. Approved-only —
            // drafts and the review trail never leave the staff lane.
            Route::get('student/lesson-plans', [MeLessonPlanController::class, 'own']);
            Route::get('children/{student}/lesson-plans', [MeLessonPlanController::class, 'child']);

            // Courses — every authenticated user (platform lane is open).
            Route::get('courses', [MeCourseController::class, 'index']);
            Route::get('courses/{course}', [MeCourseController::class, 'show']);
            Route::get('lessons/{courseLesson}', [MeCourseController::class, 'lesson']);
            Route::post('lessons/{courseLesson}/progress', [MeCourseController::class, 'saveProgress']);
            Route::get('children/{student}/lms', [MeLmsController::class, 'childLms']);

            // The attempt engine — one lane for class exams AND platform
            // exam prep; access decided at start per quiz kind.
            Route::post('exams/{quiz}/start', [MeLmsController::class, 'startExam']);
            Route::get('exam-attempts/{attempt}', [MeLmsController::class, 'attempt']);
            Route::post('exam-attempts/{attempt}/answer', [MeLmsController::class, 'answer']);
            Route::post('exam-attempts/{attempt}/events', [MeLmsController::class, 'logEvent']);
            Route::post('exam-attempts/{attempt}/submit', [MeLmsController::class, 'submitExam']);
            Route::get('exam-attempts/{attempt}/result', [MeLmsController::class, 'result']);

            // National exam prep — open to EVERY authenticated user,
            // school or no school (ADR-016).
            Route::get('exam-prep', [MeExamPrepController::class, 'index']);
            Route::get('exam-prep/facets', [MeExamPrepController::class, 'facets']);
            Route::get('exam-prep/materials', [MeExamPrepController::class, 'materials']);

            // Chat, relationship-lane aliases (ADR-019): the SAME engine +
            // controller as /chat/* — every decision is per-conversation via
            // ConversationAccess, so guardians/students need no context
            // headers. Family partner picker replaces the staff directory.
            Route::get('chat/conversations', [ChatController::class, 'index']);
            Route::post('chat/conversations', [ChatController::class, 'store']);
            Route::get('chat/unread-count', [ChatController::class, 'unreadCount']);
            Route::get('chat/search', [ChatController::class, 'search']);
            Route::get('chat/partners', [ChatController::class, 'familyPartners']);
            Route::post('chat/uploads', [ChatController::class, 'upload'])->middleware('throttle:30,1');
            Route::get('chat/conversations/{conversation}', [ChatController::class, 'show']);
            Route::get('chat/conversations/{conversation}/messages', [ChatController::class, 'messages']);
            Route::post('chat/conversations/{conversation}/messages', [ChatController::class, 'send'])->middleware('throttle:60,1');
            Route::post('chat/conversations/{conversation}/read', [ChatController::class, 'read']);
            Route::post('chat/conversations/{conversation}/mute', [ChatController::class, 'mute']);
            Route::post('chat/conversations/{conversation}/pin', [ChatController::class, 'pin']);
            Route::post('chat/conversations/{conversation}/forward', [ChatController::class, 'forward']);
            Route::put('chat/messages/{message}', [ChatMessageController::class, 'update']);
            Route::delete('chat/messages/{message}', [ChatMessageController::class, 'destroy']);
            Route::post('chat/messages/{message}/reactions', [ChatMessageController::class, 'react']);
            Route::post('chat/messages/{message}/pin', [ChatMessageController::class, 'pin']);
        });

        // Static list segments must precede the {model} routes below so they are
        // not captured as a model binding (e.g. schools/export vs schools/{school}).
        Route::get('schools/export', [SchoolController::class, 'export']);
        Route::apiResource('schools', SchoolController::class);
        Route::get('branches/export', [BranchController::class, 'exportAll']);
        Route::get('branches', [BranchController::class, 'indexAll']);
        Route::apiResource('schools.branches', BranchController::class)->shallow();

        // Aggregated profile vitals for the school/branch view pages.
        Route::get('schools/{school}/stats', [SchoolController::class, 'stats']);
        Route::get('branches/{branch}/stats', [BranchController::class, 'stats']);

        // Replace school/branch contacts (principal / IT admin / director).
        Route::put('schools/{school}/contacts', [SchoolContactController::class, 'update']);
        // Academic policy knobs — the school's own managers, not platform CRUD.
        Route::patch('schools/{school}/settings', [SchoolController::class, 'updateSettings']);
        // Official school logo — Temari.et platform staff ONLY (it prints on
        // official documents; schools request changes, never self-serve).
        Route::post('schools/{school}/logo', [SchoolController::class, 'logo']);
        Route::delete('schools/{school}/logo', [SchoolController::class, 'destroyLogo']);
        Route::put('branches/{branch}/director', [BranchContactController::class, 'update']);
        // Branch settings hub: per-branch policy overrides (director + principal).
        Route::get('branches/{branch}/settings', [BranchSettingsController::class, 'show']);
        Route::patch('branches/{branch}/settings', [BranchSettingsController::class, 'update']);

        // Platform catalog studio (Temari.et staff, `catalogs.manage`): CRUD
        // over the seed catalogs. Static `export` segments precede bindings.
        Route::prefix('catalogs')->group(function (): void {
            Route::get('overview', CatalogOverviewController::class);

            Route::get('subjects/export', [SubjectCatalogController::class, 'export']);
            Route::get('subjects', [SubjectCatalogController::class, 'index']);
            Route::post('subjects', [SubjectCatalogController::class, 'store']);
            Route::put('subjects/{subject}', [SubjectCatalogController::class, 'update']);
            Route::delete('subjects/{subject}', [SubjectCatalogController::class, 'destroy']);

            Route::get('grade-levels', [GradeLevelCatalogController::class, 'index']);
            Route::post('grade-levels', [GradeLevelCatalogController::class, 'store']);
            Route::put('grade-levels/reorder', [GradeLevelCatalogController::class, 'reorder']);
            Route::put('grade-levels/{grade_level}', [GradeLevelCatalogController::class, 'update']);
            Route::delete('grade-levels/{grade_level}', [GradeLevelCatalogController::class, 'destroy']);

            Route::get('banks/export', [BankCatalogController::class, 'export']);
            Route::get('banks', [BankCatalogController::class, 'index']);
            Route::post('banks', [BankCatalogController::class, 'store']);
            Route::put('banks/{bank}', [BankCatalogController::class, 'update']);
            Route::delete('banks/{bank}', [BankCatalogController::class, 'destroy']);

            Route::get('health-conditions/export', [HealthConditionCatalogController::class, 'export']);
            Route::get('health-conditions', [HealthConditionCatalogController::class, 'index']);
            Route::post('health-conditions', [HealthConditionCatalogController::class, 'store']);
            Route::put('health-conditions/{health_condition}', [HealthConditionCatalogController::class, 'update']);
            Route::delete('health-conditions/{health_condition}', [HealthConditionCatalogController::class, 'destroy']);

            // The notification event catalog + platform SMS whitelist: SMS is
            // metered, so which events may text is an operator decision.
            Route::get('notification-events', [NotificationEventCatalogController::class, 'index']);
            Route::put('notification-events', [NotificationEventCatalogController::class, 'update']);

            // Verify / edit / delete reuse the /school-directory routes below.
            Route::get('school-directory/export', [SchoolDirectoryCatalogController::class, 'export']);
            Route::get('school-directory/regions', [SchoolDirectoryCatalogController::class, 'regions']);
            Route::get('school-directory', [SchoolDirectoryCatalogController::class, 'index']);
            Route::post('school-directory', [SchoolDirectoryCatalogController::class, 'store']);
        });

        // Chat — staff lane (ADR-019). One engine, per-conversation access
        // via ConversationAccess; approvals = the digital communication book
        // (chat.moderate); channel creation + emergency = chat.announce.
        // Static segments precede the {conversation}/{message} bindings.
        Route::prefix('chat')->group(function (): void {
            // Preset messages: picker lane (?conversation_id=) for any posting
            // staff member; studio CRUD for chat.moderate.
            Route::get('templates', [ChatTemplateController::class, 'index']);
            Route::post('templates', [ChatTemplateController::class, 'store']);
            Route::put('templates/{template}', [ChatTemplateController::class, 'update']);
            Route::delete('templates/{template}', [ChatTemplateController::class, 'destroy']);
            Route::get('unread-count', [ChatController::class, 'unreadCount']);
            Route::get('search', [ChatController::class, 'search']);
            Route::get('partners', [ChatController::class, 'partners']);
            Route::get('channel-options', [ChatController::class, 'channelOptions']);
            Route::get('approvals', [ChatController::class, 'approvals']);
            Route::post('uploads', [ChatController::class, 'upload'])->middleware('throttle:30,1');
            Route::get('conversations', [ChatController::class, 'index']);
            Route::post('conversations', [ChatController::class, 'store']);
            Route::get('conversations/{conversation}', [ChatController::class, 'show']);
            Route::get('conversations/{conversation}/messages', [ChatController::class, 'messages']);
            Route::post('conversations/{conversation}/messages', [ChatController::class, 'send'])->middleware('throttle:60,1');
            Route::post('conversations/{conversation}/read', [ChatController::class, 'read']);
            Route::post('conversations/{conversation}/mute', [ChatController::class, 'mute']);
            Route::post('conversations/{conversation}/pin', [ChatController::class, 'pin']);
            Route::post('conversations/{conversation}/participants', [ChatController::class, 'addParticipants']);
            Route::delete('conversations/{conversation}/participants/{member}', [ChatController::class, 'removeParticipant']);
            Route::post('conversations/{conversation}/archive', [ChatController::class, 'archive']);
            Route::post('conversations/{conversation}/forward', [ChatController::class, 'forward']);
            Route::put('messages/{message}', [ChatMessageController::class, 'update']);
            Route::delete('messages/{message}', [ChatMessageController::class, 'destroy']);
            Route::post('messages/{message}/reactions', [ChatMessageController::class, 'react']);
            Route::post('messages/{message}/pin', [ChatMessageController::class, 'pin']);
            Route::post('messages/{message}/approve', [ChatMessageController::class, 'approve']);
            Route::post('messages/{message}/reject', [ChatMessageController::class, 'reject']);
        });

        // Branch employees — scoped to the active branch.
        Route::get('employees/export', [EmployeeController::class, 'export']);
        Route::post('employees/bulk/delete', [EmployeeController::class, 'bulkDestroy']);
        Route::post('employees/bulk/restore', [EmployeeController::class, 'bulkRestore']);
        Route::get('employees/account-policy', [EmployeeController::class, 'accountPolicy']);
        Route::apiResource('employees', EmployeeController::class);
        Route::post('employees/{employee}/attachments', [EmployeeAttachmentController::class, 'store']);
        Route::delete('employees/{employee}/attachments/{attachment}', [EmployeeAttachmentController::class, 'destroy']);
        Route::post('employees/{employee}/photo', [EmployeeAttachmentController::class, 'photo']);

        // Teacher unavailability windows (timetable hard constraints).
        Route::get('employees/{employee}/availability', [TeacherAvailabilityController::class, 'index']);
        Route::put('employees/{employee}/availability', [TeacherAvailabilityController::class, 'replace']);

        // The teacher's per-term workload (assignments + homeroom + published week).
        Route::get('employees/{employee}/teaching', [EmployeeTeachingController::class, 'index']);

        // HR operations (employee attendance + leave + holidays + reports).
        // Static segments precede parameterised ones (see the note above).
        Route::prefix('hr')->group(function (): void {
            // Daily employee register — overlays approved leave + holidays.
            Route::get('attendance', [EmployeeAttendanceController::class, 'register']);
            Route::post('attendance', [EmployeeAttendanceController::class, 'store']);
            Route::get('attendance/mine', [EmployeeAttendanceController::class, 'mine']);

            // Leave policy (school-owned catalog, auto-provisioned) + holidays.
            Route::get('leave-types', [LeaveTypeController::class, 'index']);
            Route::post('leave-types', [LeaveTypeController::class, 'store']);
            Route::put('leave-types/{leave_type}', [LeaveTypeController::class, 'update']);
            Route::delete('leave-types/{leave_type}', [LeaveTypeController::class, 'destroy']);
            Route::get('holidays', [HolidayController::class, 'index']);
            Route::post('holidays', [HolidayController::class, 'store']);
            Route::put('holidays/{holiday}', [HolidayController::class, 'update']);
            Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy']);

            // Leave workflow: submit (own or on-behalf) → approve/reject/cancel.
            Route::get('leave-balances', [LeaveRequestController::class, 'balances']);
            Route::apiResource('leave-requests', LeaveRequestController::class)->except(['update']);
            Route::post('leave-requests/bulk/decide', [LeaveRequestController::class, 'bulkDecide']);
            Route::post('leave-requests/{leave_request}/approve', [LeaveRequestController::class, 'approve']);
            Route::post('leave-requests/{leave_request}/reject', [LeaveRequestController::class, 'reject']);
            Route::post('leave-requests/{leave_request}/cancel', [LeaveRequestController::class, 'cancel']);

            // HR analytics.
            Route::get('reports/overview', [HrReportController::class, 'overview']);
            Route::get('reports/attendance', [HrReportController::class, 'attendance']);
            Route::get('reports/trends', [HrReportController::class, 'trends']);

            // Teacher performance appraisals (MoE continuous appraisal).
            // Rubric template (auto-provisioned) + the evaluation workflow:
            // draft → submitted (teacher notified) → acknowledged (signed).
            Route::get('evaluation-template', [TeacherEvaluationController::class, 'template']);
            Route::put('evaluation-templates/{template}', [TeacherEvaluationController::class, 'updateTemplate']);
            Route::get('evaluations', [TeacherEvaluationController::class, 'index']);
            Route::post('evaluations', [TeacherEvaluationController::class, 'store']);
            Route::get('evaluations/{evaluation}', [TeacherEvaluationController::class, 'show']);
            Route::put('evaluations/{evaluation}', [TeacherEvaluationController::class, 'update']);
            Route::post('evaluations/{evaluation}/submit', [TeacherEvaluationController::class, 'submit']);
            Route::post('evaluations/{evaluation}/acknowledge', [TeacherEvaluationController::class, 'acknowledge']);
            Route::delete('evaluations/{evaluation}', [TeacherEvaluationController::class, 'destroy']);
        });

        // Payroll — branch-scoped HR. Draft runs recompute freely; approved runs
        // are frozen; paid is terminal.
        Route::apiResource('payroll-runs', PayrollController::class);
        Route::post('payroll-runs/{payroll_run}/recompute', [PayrollController::class, 'recompute']);
        Route::post('payroll-runs/{payroll_run}/approve', [PayrollController::class, 'approve']);
        Route::post('payroll-runs/{payroll_run}/mark-paid', [PayrollController::class, 'markPaid']);

        // Academic structure — scoped to the active branch (X-Branch-Id).
        Route::get('grade-levels', [GradeLevelController::class, 'index']);
        Route::apiResource('academic-years', AcademicYearController::class);
        Route::patch('academic-years/{academic_year}/status', [AcademicYearController::class, 'setStatus']);

        // Semesters/terms — auto-provisioned with their year, manageable standalone.
        Route::get('terms', [TermController::class, 'index']);
        Route::get('terms/{term}', [TermController::class, 'show']);
        Route::post('academic-years/{academic_year}/terms', [TermController::class, 'store']);
        Route::put('terms/{term}', [TermController::class, 'update']);
        Route::delete('terms/{term}', [TermController::class, 'destroy']);
        // Lifecycle transitions (one active semester per year + program) + clone.
        Route::patch('terms/{term}/status', [TermController::class, 'setStatus']);
        Route::post('terms/{term}/clone', [TermController::class, 'clone']);
        // The semester teaching grid: section → subject → teacher.
        Route::get('terms/{term}/assignment-matrix', [TermAssignmentController::class, 'matrix']);
        Route::post('terms/{term}/generate-assignments', [TermAssignmentController::class, 'generate']);
        Route::post('terms/{term}/copy-assignments', [TermAssignmentController::class, 'copy']);
        Route::post('terms/{term}/autofill-assignments', [TermAssignmentController::class, 'autofill']);
        // The bell schedule: class periods + breaks; slots derive their times here.
        Route::get('terms/{term}/periods', [TermPeriodController::class, 'index']);
        Route::put('terms/{term}/periods', [TermPeriodController::class, 'replace']);
        Route::post('terms/{term}/periods/defaults', [TermPeriodController::class, 'defaults']);
        // Frozen semester report cards (computed on term close, recomputable)
        // + the homeroom's conduct/comment overlay.
        Route::get('terms/{term}/results', [TermResultController::class, 'index']);
        Route::post('terms/{term}/compute-results', [TermResultController::class, 'compute']);
        Route::post('terms/{term}/conduct', [TermResultController::class, 'saveConduct']);
        // One enrollment's frozen results — the profile's academic-history modal.
        Route::get('student-enrollments/{enrollment}/results', [TermResultController::class, 'forEnrollment']);
        // Grading analytics dashboard (frozen-rows aggregates + marklist progress).
        Route::get('terms/{term}/grading-report', [GradingReportController::class, 'overview']);
        // Per-subject cohort drill-down for the marklist-analysis tab.
        Route::get('terms/{term}/marklist-analysis', [GradingReportController::class, 'marklistAnalysis']);
        // Submission monitor: per-teacher marklist state + entry completeness.
        Route::get('terms/{term}/marklist-status', [GradingReportController::class, 'submissionStatus']);
        // Nudge the teachers who are behind (one folded reminder per teacher).
        Route::post('terms/{term}/marklist-reminders', [GradingReportController::class, 'remindPending']);
        // Ethiopian roster sheets over the frozen results (term + yearly).
        Route::get('terms/{term}/roster', [RosterController::class, 'term']);
        Route::get('academic-years/{academic_year}/roster', [RosterController::class, 'year']);
        // Transcript register: the year's enrolled students + readiness.
        Route::get('academic-years/{academic_year}/transcript-register', [TranscriptController::class, 'register']);

        // Education programs of the active branch (+ full catalog).
        Route::get('programs', [ProgramController::class, 'index']);
        Route::apiResource('sections', SectionController::class);
        // Class profile: one semester's roster + frozen marks + homeroom.
        Route::get('sections/{section}/roster', [SectionController::class, 'roster']);

        // Daily attendance per section.
        Route::get('sections/{section}/attendance', [AttendanceController::class, 'register']);
        Route::post('sections/{section}/attendance', [AttendanceController::class, 'store']);

        // Parent-filed absence excuses: the branch review queue + decision.
        Route::get('absence-excuses', [AbsenceExcuseController::class, 'index']);
        // Static path FIRST: {absence_excuse} would otherwise match "bulk".
        Route::post('absence-excuses/bulk/decide', [AbsenceExcuseController::class, 'bulkDecide']);
        Route::post('absence-excuses/{absence_excuse}/decide', [AbsenceExcuseController::class, 'decide']);

        // Student attendance analytics — scope follows the caller's lane
        // (platform → system-wide, school → school-wide, branch → branch,
        // teacher → own homerooms); see AttendanceReportController.
        Route::get('attendance-reports/overview', [AttendanceReportController::class, 'overview']);
        Route::get('attendance-reports/trends', [AttendanceReportController::class, 'trends']);
        Route::get('attendance-reports/students', [AttendanceReportController::class, 'students']);
        Route::get('attendance-reports/students/export', [AttendanceReportController::class, 'studentsExport']);

        // RFID attendance hardware: terminal registry (token shown once at
        // creation/rotation), the MIFARE card register with its lost/replace
        // lifecycle, the raw scan log, and the guardian-alert ledger (which
        // doubles as the school's SMS meter).
        // Docs share-link management (platform only) — its own prefix so the
        // literal segments never collide with the devices/{device} binding.
        Route::get('device-docs-share', [DeviceController::class, 'docsShare']);
        Route::post('device-docs-share/rotate', [DeviceController::class, 'rotateDocsShare']);
        Route::delete('device-docs-share', [DeviceController::class, 'revokeDocsShare']);
        Route::apiResource('devices', DeviceController::class)->except(['show']);
        Route::post('devices/{device}/rotate-token', [DeviceController::class, 'rotateToken']);
        Route::get('device-events', [DeviceEventController::class, 'index']);
        // Static card segments precede the {card} binding.
        Route::get('cards/candidates', [IdCardController::class, 'candidates']);
        Route::post('cards/bulk', [IdCardController::class, 'bulkStore']);
        Route::get('cards', [IdCardController::class, 'index']);
        Route::post('cards', [IdCardController::class, 'store']);
        Route::post('cards/{card}/report-lost', [IdCardController::class, 'reportLost']);
        Route::post('cards/{card}/deactivate', [IdCardController::class, 'deactivate']);
        Route::post('cards/{card}/replace', [IdCardController::class, 'replace']);
        // Card fulfilment pipeline: schools open + follow, Temari.et staff drive.
        Route::get('card-requests', [CardRequestController::class, 'index']);
        Route::patch('card-requests/{cardRequest}', [CardRequestController::class, 'update']);
        Route::post('card-requests/{cardRequest}/issue', [CardRequestController::class, 'issue']);
        Route::get('attendance-notifications', [AttendanceNotificationLogController::class, 'index']);

        // Platform-wide Ethiopian school directory ("previous school" catalog).
        Route::get('school-directory', [SchoolDirectoryController::class, 'index']);
        Route::post('school-directory', [SchoolDirectoryController::class, 'store']);
        Route::put('school-directory/{entry}', [SchoolDirectoryController::class, 'update']);
        Route::patch('school-directory/{entry}/verify', [SchoolDirectoryController::class, 'verify']);
        Route::delete('school-directory/{entry}', [SchoolDirectoryController::class, 'destroy']);

        // Bulk student import (the studio's API): browser-parsed rows arrive
        // in chunks, validate in place, then a queued job registers them.
        // Static paths must precede the students/{student} bindings.
        Route::get('student-imports', [StudentImportController::class, 'index']);
        Route::post('student-imports', [StudentImportController::class, 'store']);
        Route::get('student-imports/{import}', [StudentImportController::class, 'show']);
        Route::post('student-imports/{import}/rows', [StudentImportController::class, 'appendRows']);
        Route::get('student-imports/{import}/rows', [StudentImportController::class, 'rows']);
        Route::patch('student-imports/{import}/rows/{row}', [StudentImportController::class, 'updateRow']);
        Route::post('student-imports/{import}/commit', [StudentImportController::class, 'commit']);
        Route::delete('student-imports/{import}', [StudentImportController::class, 'destroy']);

        // Students & enrollment — scoped to the active branch. Static `export`
        // must precede the {student} bindings.
        Route::get('students/export', [StudentController::class, 'export']);
        Route::post('students/bulk/delete', [StudentController::class, 'bulkDestroy']);
        Route::post('students/bulk/restore', [StudentController::class, 'bulkRestore']);
        Route::apiResource('students', StudentController::class);
        Route::post('students/{student}/enrollments', [StudentEnrollmentController::class, 'store']);
        // Registration-fee gate: activate a pending enrollment (soft-gate schools).
        // Static path FIRST: {enrollment} would otherwise match "bulk".
        Route::post('enrollments/bulk/activate', [StudentEnrollmentController::class, 'bulkActivate']);
        Route::post('enrollments/{enrollment}/activate', [StudentEnrollmentController::class, 'activate']);
        // Fix a mistaken grade/section/program on a live enrollment.
        Route::patch('enrollments/{enrollment}', [StudentEnrollmentController::class, 'update']);
        // Mid-year withdrawal (leaving school / moving outside Temari) + its
        // printable QR-verified clearance letter.
        Route::post('enrollments/{enrollment}/withdraw', [StudentEnrollmentController::class, 'withdraw']);
        Route::get('enrollments/{enrollment}/withdrawal-letter', [StudentEnrollmentController::class, 'withdrawalLetter']);

        // Year-end promotion board: read → decide → execute (rollover).
        Route::get('promotions/board', [PromotionController::class, 'board']);
        Route::post('promotions/decisions', [PromotionController::class, 'saveDecisions']);
        Route::post('promotions/rollover', [PromotionController::class, 'rollover']);
        Route::post('promotions/revert', [PromotionController::class, 'revert']);

        // Class formation: pool + balanced auto-proposal + reviewed commit.
        Route::get('section-assignments/board', [SectionAssignmentController::class, 'board']);
        Route::post('section-assignments/propose', [SectionAssignmentController::class, 'propose']);
        Route::post('section-assignments/commit', [SectionAssignmentController::class, 'commit']);
        Route::post('section-assignments/students', [SectionAssignmentController::class, 'assignStudents']);

        // In-platform transfers: receiving branch requests, sending decides.
        // Static `candidate` precedes the {transfer} bindings.
        Route::get('transfer-requests/candidate', [TransferController::class, 'candidate']);
        Route::get('transfer-requests', [TransferController::class, 'index']);
        Route::post('transfer-requests', [TransferController::class, 'store']);
        Route::post('transfer-requests/{transfer}/approve', [TransferController::class, 'approve']);
        Route::post('transfer-requests/{transfer}/reject', [TransferController::class, 'reject']);
        Route::post('transfer-requests/{transfer}/cancel', [TransferController::class, 'cancel']);
        Route::post('transfer-requests/bulk/decide', [TransferController::class, 'bulkDecide']);
        Route::get('transfer-requests/{transfer}/letter', [TransferController::class, 'letter']);
        // Parent/student-initiated applications: the DESTINATION school's inbox.
        Route::get('transfer-applications', [TransferApplicationController::class, 'index']);
        Route::post('transfer-applications/{application}/accept', [TransferApplicationController::class, 'accept']);
        Route::post('transfer-applications/{application}/decline', [TransferApplicationController::class, 'decline']);

        // Student documents + photo (signed URLs, StudentPolicy@update).
        Route::post('students/{student}/attachments', [StudentAttachmentController::class, 'store']);
        Route::delete('students/{student}/attachments/{attachment}', [StudentAttachmentController::class, 'destroy']);
        Route::post('students/{student}/photo', [StudentAttachmentController::class, 'photo']);

        // Guardians (parents) of a student. The static `search` segment must
        // precede the {guardian} bindings.
        Route::get('guardians/search', [GuardianController::class, 'search']);
        Route::get('students/{student}/guardians', [GuardianController::class, 'index']);
        Route::post('students/{student}/guardians', [GuardianController::class, 'store']);
        Route::put('guardians/{guardian}', [GuardianController::class, 'update']);
        Route::delete('guardians/{guardian}', [GuardianController::class, 'destroy']);

        // Staff parents register (global persons scoped through their children).
        // Static `export` must precede the {parent} binding.
        Route::get('parents/export', [ParentController::class, 'export']);
        Route::get('parents', [ParentController::class, 'index']);
        Route::get('parents/{parent}', [ParentController::class, 'show']);

        // Guardian documents + photo (authority flows through linked children).
        Route::post('parents/{parent}/attachments', [ParentAttachmentController::class, 'store']);
        Route::delete('parents/{parent}/attachments/{attachment}', [ParentAttachmentController::class, 'destroy']);
        Route::post('parents/{parent}/photo', [ParentAttachmentController::class, 'photo']);

        // Family portal logins: provision a student's own account, re-send
        // unused setup links, and review self-signup student-ID claims.
        Route::post('students/{student}/portal-account', [PortalAccountController::class, 'storeForStudent']);
        Route::post('students/{student}/portal-account/invite', [PortalAccountController::class, 'inviteStudent']);
        Route::post('parents/{parent}/portal-account/invite', [PortalAccountController::class, 'inviteParent']);
        Route::post('parents/bulk/invite', [PortalAccountController::class, 'bulkInviteParents']);
        Route::post('students/bulk/invite', [PortalAccountController::class, 'bulkInviteStudents']);
        Route::get('account-link-requests', [AccountLinkRequestController::class, 'index']);
        Route::post('account-link-requests/{account_link_request}/approve', [AccountLinkRequestController::class, 'approve']);
        Route::post('account-link-requests/{account_link_request}/reject', [AccountLinkRequestController::class, 'reject']);

        // Health condition catalog (platform seed data).
        Route::get('health-conditions', [HealthConditionController::class, 'index']);

        // Payment collection accounts (school-owned, shared across branches)
        // + the static bank/wallet catalog.
        Route::get('banks', [BankAccountController::class, 'banks']);
        Route::get('bank-accounts', [BankAccountController::class, 'index']);
        Route::post('bank-accounts', [BankAccountController::class, 'store']);
        Route::get('bank-accounts/{bank_account}/payments', [BankAccountController::class, 'payments']);
        Route::get('bank-accounts/{bank_account}/stats', [BankAccountController::class, 'stats']);
        Route::put('bank-accounts/{bank_account}', [BankAccountController::class, 'update']);
        Route::delete('bank-accounts/{bank_account}', [BankAccountController::class, 'destroy']);

        // Fees, invoices & payments (branch-scoped). Static `applicable` must
        // precede the {fee_structure} bindings.
        Route::get('fee-structures/applicable', [FeeStructureController::class, 'applicable']);
        Route::apiResource('fee-structures', FeeStructureController::class)->except(['show']);
        Route::post('fee-structures/{fee_structure}/generate-invoices', [FeeStructureController::class, 'generateInvoices']);
        Route::patch('fee-structures/{fee_structure}/notifications', [FeeStructureController::class, 'setNotifications']);
        // On-demand payment notices for a fee's open invoices (preview counts,
        // then queue the send) — the catch-up when notifications were off.
        Route::get('fee-structures/{fee_structure}/notify-preview', [FeeStructureController::class, 'notifyPreview']);
        Route::post('fee-structures/{fee_structure}/notify', [FeeStructureController::class, 'notify']);
        // Static paths precede the {invoice} binding.
        Route::get('invoices/export', [InvoiceController::class, 'export']);
        Route::get('invoices/stats', [InvoiceController::class, 'stats']);
        Route::post('invoices/bulk/delete', [InvoiceController::class, 'bulkDestroy']);
        Route::apiResource('invoices', InvoiceController::class)->except(['update']);
        Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store']);
        Route::post('invoices/{invoice}/discount', [InvoiceController::class, 'applyDiscount']);
        // Forgive an accrued late penalty (and stop its daily re-accrual).
        Route::post('invoices/{invoice}/waive-penalty', [InvoiceController::class, 'waivePenalty']);
        // Printable official receipt for one payment (QR → public route).
        Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt']);
        Route::get('invoices/{invoice}/verifications', [InvoiceController::class, 'verifications']);
        // Manual review resolution for parked (needs_review) submissions.
        Route::post('invoices/{invoice}/verifications/{verification}/confirm', [InvoiceController::class, 'confirmVerification']);
        Route::post('invoices/{invoice}/verifications/{verification}/reject', [InvoiceController::class, 'rejectVerification']);

        // Receivables analytics (fees.reports.view): overview + aging,
        // daily collections, defaulters register, per-student statement.
        Route::get('fee-reports/overview', [FeeReportController::class, 'overview']);
        Route::get('fee-reports/daily-collections', [FeeReportController::class, 'dailyCollections']);
        Route::get('fee-reports/defaulters', [FeeReportController::class, 'defaulters']);
        Route::get('fee-reports/statement', [FeeReportController::class, 'statement']);

        // The books (finance.books.*): cashbook categories, expenses with
        // the four-eyes approval lane, other income, budget vs actual, the
        // unified cashbook ledger and the income–expense statement.
        Route::get('finance/categories', [FinanceCategoryController::class, 'index']);
        Route::post('finance/categories', [FinanceCategoryController::class, 'store']);
        Route::put('finance/categories/{finance_category}', [FinanceCategoryController::class, 'update']);
        Route::delete('finance/categories/{finance_category}', [FinanceCategoryController::class, 'destroy']);
        Route::get('finance/expenses/stats', [ExpenseController::class, 'stats']);
        Route::get('finance/expenses', [ExpenseController::class, 'index']);
        Route::post('finance/expenses', [ExpenseController::class, 'store']);
        Route::put('finance/expenses/{expense}', [ExpenseController::class, 'update']);
        Route::delete('finance/expenses/{expense}', [ExpenseController::class, 'destroy']);
        Route::post('finance/expenses/{expense}/approve', [ExpenseController::class, 'approve']);
        Route::post('finance/expenses/{expense}/reject', [ExpenseController::class, 'reject']);
        Route::post('finance/expenses/bulk/decide', [ExpenseController::class, 'bulkDecide']);
        Route::get('finance/other-incomes', [OtherIncomeController::class, 'index']);
        Route::post('finance/other-incomes', [OtherIncomeController::class, 'store']);
        Route::put('finance/other-incomes/{other_income}', [OtherIncomeController::class, 'update']);
        Route::delete('finance/other-incomes/{other_income}', [OtherIncomeController::class, 'destroy']);
        Route::get('finance/budgets', [FinanceBookController::class, 'budgets']);
        Route::put('finance/budgets', [FinanceBookController::class, 'saveBudgets']);
        Route::get('finance/cashbook', [FinanceBookController::class, 'cashbook']);
        Route::get('finance/statement', [FinanceBookController::class, 'statement']);

        // Inventory & school property. The item master + the append-only
        // stock ledger (StockLedger is the only writer), the requisition
        // workflow (request → countersign → issue), the OPTIONAL purchase
        // order lane and physical stock takes. Static paths precede bindings.
        Route::get('inventory/categories', [InventoryCategoryController::class, 'index']);
        Route::post('inventory/categories', [InventoryCategoryController::class, 'store']);
        Route::put('inventory/categories/{inventory_category}', [InventoryCategoryController::class, 'update']);
        Route::delete('inventory/categories/{inventory_category}', [InventoryCategoryController::class, 'destroy']);
        Route::get('inventory/items/stats', [InventoryItemController::class, 'stats']);
        Route::get('inventory/items/next-code', [InventoryItemController::class, 'nextCode']);
        Route::get('inventory/items', [InventoryItemController::class, 'index']);
        Route::post('inventory/items/quick-add', [InventoryItemController::class, 'quickAdd']);
        Route::post('inventory/items', [InventoryItemController::class, 'store']);
        Route::put('inventory/items/{inventory_item}', [InventoryItemController::class, 'update']);
        Route::delete('inventory/items/{inventory_item}', [InventoryItemController::class, 'destroy']);
        Route::get('inventory/movements', [StockMovementController::class, 'index']);
        Route::post('inventory/movements/receive', [StockMovementController::class, 'receive']);
        Route::post('inventory/movements/issue', [StockMovementController::class, 'issue']);
        Route::post('inventory/movements/return', [StockMovementController::class, 'returnStock']);
        Route::post('inventory/movements/adjust', [StockMovementController::class, 'adjust']);
        Route::post('inventory/movements/write-off', [StockMovementController::class, 'writeOff']);
        Route::get('inventory/requisitions', [RequisitionController::class, 'index']);
        Route::post('inventory/requisitions', [RequisitionController::class, 'store']);
        Route::put('inventory/requisitions/{requisition}', [RequisitionController::class, 'update']);
        Route::post('inventory/requisitions/{requisition}/cancel', [RequisitionController::class, 'cancel']);
        Route::post('inventory/requisitions/{requisition}/approve', [RequisitionController::class, 'approve']);
        Route::post('inventory/requisitions/{requisition}/decline', [RequisitionController::class, 'decline']);
        Route::post('inventory/requisitions/{requisition}/issue', [RequisitionController::class, 'issue']);
        Route::get('inventory/purchase-orders', [PurchaseOrderController::class, 'index']);
        Route::post('inventory/purchase-orders', [PurchaseOrderController::class, 'store']);
        Route::put('inventory/purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'update']);
        Route::post('inventory/purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel']);
        Route::post('inventory/purchase-orders/{purchase_order}/approve', [PurchaseOrderController::class, 'approve']);
        Route::post('inventory/purchase-orders/{purchase_order}/decline', [PurchaseOrderController::class, 'decline']);
        Route::post('inventory/purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive']);
        // Phase 2: the property register (tag-tracked units + custody chain)
        // and the scoped holder picker (id + label only — never the HR or
        // student registers). Phase 3: MoE textbook lending per student.
        Route::get('inventory/holders', [AssetController::class, 'holders']);
        Route::get('inventory/assets', [AssetController::class, 'index']);
        Route::post('inventory/assets', [AssetController::class, 'store']);
        Route::put('inventory/assets/{asset}', [AssetController::class, 'update']);
        Route::delete('inventory/assets/{asset}', [AssetController::class, 'destroy']);
        Route::post('inventory/assets/{asset}/status', [AssetController::class, 'setStatus']);
        Route::post('inventory/assets/{asset}/assign', [AssetController::class, 'assign']);
        Route::post('inventory/assets/{asset}/return', [AssetController::class, 'returnUnit']);
        Route::get('inventory/textbooks', [TextbookController::class, 'index']);
        Route::post('inventory/textbooks/issue', [TextbookController::class, 'issue']);
        Route::post('inventory/textbooks/return', [TextbookController::class, 'returnLoans']);
        Route::post('inventory/textbooks/{textbook}/lost', [TextbookController::class, 'lost']);
        Route::get('inventory/stock-takes', [StockTakeController::class, 'index']);
        Route::post('inventory/stock-takes', [StockTakeController::class, 'store']);
        Route::get('inventory/stock-takes/{stock_take}', [StockTakeController::class, 'show']);
        Route::put('inventory/stock-takes/{stock_take}/counts', [StockTakeController::class, 'saveCounts']);
        Route::post('inventory/stock-takes/{stock_take}/post', [StockTakeController::class, 'post']);
        Route::post('inventory/stock-takes/{stock_take}/cancel', [StockTakeController::class, 'cancel']);

        // Official PDFs (receipts, letters, transcripts, report cards,
        // statements, payslips): POST answers instantly from the R2 cache or
        // queues a render; GET polls until ready. Authorization is per
        // document type, row-anchored inside the type class.
        Route::post('documents', [DocumentController::class, 'store']);
        Route::get('documents/{document}', [DocumentController::class, 'show']);
        Route::post('documents/{document}/revoke', [DocumentController::class, 'revoke']);

        // Standing discounts/scholarships (concessions): manual grants +
        // the sibling/staff policy review lane. Static paths precede bindings.
        Route::get('fee-concessions/export', [FeeConcessionController::class, 'export']);
        Route::get('fee-concessions/stats', [FeeConcessionController::class, 'stats']);
        Route::apiResource('fee-concessions', FeeConcessionController::class)->except(['update']);
        Route::post('fee-concessions/{fee_concession}/approve', [FeeConcessionController::class, 'approve']);
        Route::post('fee-concessions/{fee_concession}/reject', [FeeConcessionController::class, 'reject']);
        Route::post('fee-concessions/{fee_concession}/revoke', [FeeConcessionController::class, 'revoke']);
        Route::post('fee-concessions/bulk/decide', [FeeConcessionController::class, 'bulkDecide']);

        // Subjects (platform + school-custom).
        Route::apiResource('subjects', SubjectController::class);

        // Teaching grid: section → subject assignments.
        Route::get('sections/{section}/subject-assignments', [SubjectAssignmentController::class, 'index']);
        Route::post('sections/{section}/subject-assignments', [SubjectAssignmentController::class, 'store']);
        Route::put('subject-assignments/{subjectAssignment}', [SubjectAssignmentController::class, 'update']);
        Route::delete('subject-assignments/{subjectAssignment}', [SubjectAssignmentController::class, 'destroy']);

        // Timetable: rooms + versioned grids (draft → generate → tune → publish).
        Route::get('rooms', [RoomController::class, 'index']);
        Route::post('rooms', [RoomController::class, 'store']);
        Route::put('rooms/{room}', [RoomController::class, 'update']);
        Route::delete('rooms/{room}', [RoomController::class, 'destroy']);
        Route::get('my-timetable', [TimetableController::class, 'mine']);
        Route::get('terms/{term}/timetable-versions', [TimetableController::class, 'index']);
        Route::post('terms/{term}/timetable-versions', [TimetableController::class, 'store']);
        Route::get('timetable-versions/{version}', [TimetableController::class, 'show']);
        Route::delete('timetable-versions/{version}', [TimetableController::class, 'destroy']);
        Route::post('timetable-versions/{version}/generate', [TimetableController::class, 'generate']);
        Route::post('timetable-versions/{version}/publish', [TimetableController::class, 'publish']);
        Route::post('timetable-versions/{version}/slots', [TimetableController::class, 'storeSlot']);
        Route::put('timetable-versions/{version}/slots/{slot}', [TimetableController::class, 'updateSlot']);
        Route::delete('timetable-versions/{version}/slots/{slot}', [TimetableController::class, 'destroySlot']);

        // ContinuousAssessment: subject assignment → assessments → results.
        Route::get('subject-assignments/{subjectAssignment}/assessments', [AssessmentController::class, 'index']);
        Route::post('subject-assignments/{subjectAssignment}/assessments', [AssessmentController::class, 'store']);
        Route::put('assessments/{assessment}', [AssessmentController::class, 'update']);
        Route::delete('assessments/{assessment}', [AssessmentController::class, 'destroy']);
        Route::get('assessments/{assessment}/results', [AssessmentController::class, 'results']);
        Route::post('assessments/{assessment}/results', [AssessmentController::class, 'upsertResults']);

        // Grading policy: scales (platform + school-owned) and per-branch /
        // per-grade-window display rules (numeric / letter / both).
        Route::get('grading-scales', [GradingScaleController::class, 'index']);
        Route::post('grading-scales', [GradingScaleController::class, 'store']);
        Route::put('grading-scales/{grading_scale}', [GradingScaleController::class, 'update']);
        Route::delete('grading-scales/{grading_scale}', [GradingScaleController::class, 'destroy']);
        Route::get('grading-policies', [GradingPolicyController::class, 'index']);
        Route::post('grading-policies', [GradingPolicyController::class, 'store']);
        Route::put('grading-policies/{grading_policy}', [GradingPolicyController::class, 'update']);
        Route::delete('grading-policies/{grading_policy}', [GradingPolicyController::class, 'destroy']);

        // Grade book templates (principal/director-defined assessment plans).
        Route::get('continuous-assessments', [ContinuousAssessmentController::class, 'index']);
        Route::post('continuous-assessments', [ContinuousAssessmentController::class, 'store']);
        Route::put('continuous-assessments/{continuous_assessment}', [ContinuousAssessmentController::class, 'update']);
        Route::delete('continuous-assessments/{continuous_assessment}', [ContinuousAssessmentController::class, 'destroy']);

        // LMS staff lane (ADR-016): question banks + questions (school AND
        // platform via ?platform=1), quizzes/exams with monitoring + manual
        // grading, homework + submission grading, learning materials.
        Route::get('question-banks', [QuestionBankController::class, 'index']);
        Route::post('question-banks', [QuestionBankController::class, 'store']);
        Route::get('question-banks/{question_bank}', [QuestionBankController::class, 'show']);
        Route::put('question-banks/{question_bank}', [QuestionBankController::class, 'update']);
        Route::delete('question-banks/{question_bank}', [QuestionBankController::class, 'destroy']);
        Route::get('question-banks/{question_bank}/questions', [QuestionController::class, 'index']);
        Route::post('question-banks/{question_bank}/questions', [QuestionController::class, 'store']);
        Route::post('question-banks/{question_bank}/questions/bulk', [QuestionController::class, 'bulkStore']);
        Route::post('question-banks/{question_bank}/uploads', [QuestionController::class, 'upload']);
        // Cross-bank browse for the exam paper picker (ADR-016): one request
        // instead of N when a quiz draws from several banks at once.
        Route::get('questions', [QuestionController::class, 'indexMany']);
        Route::put('questions/{question}', [QuestionController::class, 'update']);
        Route::post('questions/{question}/reorder', [QuestionController::class, 'reorder']);
        Route::patch('questions/{question}/status', [QuestionController::class, 'setStatus']);
        Route::delete('questions/{question}', [QuestionController::class, 'destroy']);

        Route::post('lms/uploads', [QuizController::class, 'upload']);
        Route::get('quizzes', [QuizController::class, 'index']);
        Route::post('quizzes', [QuizController::class, 'store']);
        Route::get('quizzes/{quiz}', [QuizController::class, 'show']);
        Route::get('quizzes/{quiz}/preview', [QuizController::class, 'preview']);
        Route::put('quizzes/{quiz}', [QuizController::class, 'update']);
        Route::delete('quizzes/{quiz}', [QuizController::class, 'destroy']);
        Route::post('quizzes/{quiz}/publish', [QuizController::class, 'publish']);
        Route::post('quizzes/{quiz}/close', [QuizController::class, 'close']);
        Route::post('quizzes/{quiz}/sync', [QuizController::class, 'sync']);
        Route::get('quizzes/{quiz}/attempts', [QuizController::class, 'attempts']);
        Route::get('quizzes/{quiz}/attempts/{attempt}', [QuizController::class, 'showAttempt']);
        Route::post('quizzes/{quiz}/attempts/{attempt}/grade', [QuizController::class, 'gradeAttempt']);
        Route::post('quizzes/{quiz}/attempts/{attempt}/invalidate', [QuizController::class, 'invalidateAttempt']);

        Route::get('assignments', [LmsAssignmentController::class, 'index']);
        Route::post('assignments', [LmsAssignmentController::class, 'store']);
        Route::get('assignments/{assignment}', [LmsAssignmentController::class, 'show']);
        Route::put('assignments/{assignment}', [LmsAssignmentController::class, 'update']);
        Route::delete('assignments/{assignment}', [LmsAssignmentController::class, 'destroy']);
        Route::post('assignments/{assignment}/publish', [LmsAssignmentController::class, 'publish']);
        Route::post('assignments/{assignment}/close', [LmsAssignmentController::class, 'close']);
        Route::post('assignments/{assignment}/sync', [LmsAssignmentController::class, 'sync']);
        Route::get('assignments/{assignment}/submissions', [LmsAssignmentController::class, 'submissions']);
        Route::post('assignments/{assignment}/submissions/{submission}/grade', [LmsAssignmentController::class, 'gradeSubmission']);
        Route::get('assignments/{assignment}/threads', [LmsAssignmentController::class, 'threads']);
        // Per-student context conversation (chat engine, ADR-019).
        Route::get('assignments/{assignment}/thread', [LmsAssignmentController::class, 'thread']);
        Route::get('subject-assignments/{subjectAssignment}/students', [LmsAssignmentController::class, 'classStudents']);

        // Courses: modules → lessons → progress (the course studio).
        Route::get('courses', [CourseController::class, 'index']);
        Route::post('courses', [CourseController::class, 'store']);
        Route::get('courses/{course}', [CourseController::class, 'show']);
        Route::put('courses/{course}', [CourseController::class, 'update']);
        Route::delete('courses/{course}', [CourseController::class, 'destroy']);
        Route::post('courses/{course}/publish', [CourseController::class, 'publish']);
        Route::post('courses/{course}/archive', [CourseController::class, 'archive']);
        Route::post('courses/{course}/reorder', [CourseController::class, 'reorder']);
        Route::post('courses/{course}/modules', [CourseController::class, 'storeModule']);
        Route::put('course-modules/{courseModule}', [CourseController::class, 'updateModule']);
        Route::delete('course-modules/{courseModule}', [CourseController::class, 'destroyModule']);
        Route::post('course-modules/{courseModule}/lessons', [CourseController::class, 'storeLesson']);
        Route::put('course-lessons/{courseLesson}', [CourseController::class, 'updateLesson']);
        Route::delete('course-lessons/{courseLesson}', [CourseController::class, 'destroyLesson']);

        Route::get('course-materials', [CourseMaterialController::class, 'index']);
        Route::post('course-materials', [CourseMaterialController::class, 'store']);
        Route::put('course-materials/{course_material}', [CourseMaterialController::class, 'update']);
        Route::delete('course-materials/{course_material}', [CourseMaterialController::class, 'destroy']);

        // Marklists: the teacher's marks grid + the submit → approve workflow.
        Route::get('marklists', [MarklistController::class, 'index']);
        Route::get('marklists/{subjectAssignment}', [MarklistController::class, 'show']);
        Route::post('marklists/{subjectAssignment}/submit', [MarklistController::class, 'submit']);
        // On-behalf declaration: the only way a supervisor types into a teacher-owned draft.
        Route::post('marklists/{subjectAssignment}/assist', [MarklistController::class, 'assist']);
        Route::post('marklists/{subjectAssignment}/approve', [MarklistController::class, 'approve']);
        Route::post('marklists/{subjectAssignment}/reopen', [MarklistController::class, 'reopen']);

        // Lesson planning: the annual roadmap (MoE grid: units with rationale,
        // prerequisites, aids, assessment, pages) → weekly containers → DAILY
        // lesson plans (MoE daily format, timetable-driven via my-day) → the
        // review inbox + pacing dashboard. Static segments precede the
        // {lessonPlan} binding.
        Route::get('lesson-plans/my-day', [DailyLessonPlanController::class, 'myDay']);
        Route::get('lesson-plans/review', [LessonPlanController::class, 'review']);
        Route::get('lesson-plans/pacing', [LessonPlanController::class, 'pacing']);
        Route::get('lesson-plans/options', [LessonPlanController::class, 'options']);
        Route::get('lesson-plans', [LessonPlanController::class, 'index']);
        Route::post('lesson-plans', [LessonPlanController::class, 'store']);
        Route::get('lesson-plans/{lessonPlan}', [LessonPlanController::class, 'show']);
        Route::put('lesson-plans/{lessonPlan}', [LessonPlanController::class, 'update']);
        Route::delete('lesson-plans/{lessonPlan}', [LessonPlanController::class, 'destroy']);
        Route::post('lesson-plans/{lessonPlan}/submit', [LessonPlanController::class, 'submit']);
        Route::post('lesson-plans/{lessonPlan}/approve', [LessonPlanController::class, 'approve']);
        Route::post('lesson-plans/{lessonPlan}/decline', [LessonPlanController::class, 'decline']);
        Route::post('lesson-plans/{lessonPlan}/reopen', [LessonPlanController::class, 'reopen']);
        Route::post('lesson-plans/{lessonPlan}/units', [LessonPlanController::class, 'storeUnit']);
        Route::put('plan-units/{unit}', [LessonPlanController::class, 'updateUnit']);
        Route::delete('plan-units/{unit}', [LessonPlanController::class, 'destroyUnit']);
        Route::get('lesson-plans/{lessonPlan}/weeks/prefill', [WeeklyLessonPlanController::class, 'prefill']);
        Route::get('lesson-plans/{lessonPlan}/weeks', [WeeklyLessonPlanController::class, 'index']);
        Route::post('lesson-plans/{lessonPlan}/weeks', [WeeklyLessonPlanController::class, 'store']);
        Route::get('weekly-plans/{weeklyPlan}', [WeeklyLessonPlanController::class, 'show']);
        Route::put('weekly-plans/{weeklyPlan}', [WeeklyLessonPlanController::class, 'update']);
        Route::delete('weekly-plans/{weeklyPlan}', [WeeklyLessonPlanController::class, 'destroy']);
        Route::post('weekly-plans/{weeklyPlan}/submit', [WeeklyLessonPlanController::class, 'submit']);
        Route::post('weekly-plans/{weeklyPlan}/approve', [WeeklyLessonPlanController::class, 'approve']);
        Route::post('weekly-plans/{weeklyPlan}/decline', [WeeklyLessonPlanController::class, 'decline']);
        Route::post('weekly-plans/{weeklyPlan}/reopen', [WeeklyLessonPlanController::class, 'reopen']);
        // Daily lesson plans: created under the annual plan (the weekly
        // container is auto-resolved from the date), edited by autosave from
        // the studio, coverage marked per sitting from My Day.
        Route::post('lesson-plans/{lessonPlan}/days', [DailyLessonPlanController::class, 'store']);
        Route::get('daily-plans/{dailyPlan}', [DailyLessonPlanController::class, 'show']);
        Route::put('daily-plans/{dailyPlan}', [DailyLessonPlanController::class, 'update']);
        Route::delete('daily-plans/{dailyPlan}', [DailyLessonPlanController::class, 'destroy']);
        Route::post('daily-plans/{dailyPlan}/coverage', [DailyLessonPlanController::class, 'coverage']);
        Route::post('daily-plans/{dailyPlan}/duplicate', [DailyLessonPlanController::class, 'duplicate']);

        // Reports.
        // Batch transcripts (≤60 per call) for bulk print / Excel export.
        Route::get('reports/transcripts', [TranscriptController::class, 'batch']);
        Route::get('reports/students/{student}/result-card', [ReportController::class, 'resultCard']);
        Route::get('reports/students/{student}/report-card', [ReportController::class, 'reportCard']);
        Route::get('reports/students/{student}/transcript', [ReportController::class, 'transcript']);
        Route::get('reports/students/{student}/attendance-summary', [ReportController::class, 'attendanceSummary']);
        Route::get('reports/subject-assignments/{subjectAssignment}/continuous assessment', [ReportController::class, 'sectionContinuousAssessment']);

        // Users — scoped by role hierarchy (Temari Admin > Principal > Director).
        Route::get('users/export', [UserController::class, 'export']);
        // Bulk row actions — every one authorizes PER USER and reports what it
        // skipped (App\Http\Controllers\Concerns\HandlesBulkActions).
        Route::post('users/bulk/status', [UserController::class, 'bulkStatus']);
        Route::post('users/bulk/reset-password', [UserController::class, 'bulkResetPassword']);
        Route::post('users/bulk/delete', [UserController::class, 'bulkDestroy']);
        Route::post('users/bulk/restore', [UserController::class, 'bulkRestore']);
        Route::post('users/bulk/memberships', [MembershipController::class, 'bulkStore']);
        Route::post('users/bulk/branch-access', [MembershipController::class, 'bulkStatus']);
        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::get('users/{user}', [UserController::class, 'show']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);
        // Resolves soft-deleted models — the target is trashed by definition.
        Route::post('users/{user}/restore', [UserController::class, 'restore'])->withTrashed();
        Route::patch('users/{user}/status', [UserController::class, 'setStatus']);
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);
        Route::post('users/{user}/avatar', [UserController::class, 'uploadAvatar']);
        Route::post('users/{user}/impersonate', [UserController::class, 'impersonate']);

        // Branch/school membership assignment (Principal / Director scope).
        Route::post('users/{user}/memberships', [MembershipController::class, 'store']);
        Route::patch('memberships/{membership}/status', [MembershipController::class, 'updateStatus']);
        Route::delete('memberships/{membership}', [MembershipController::class, 'destroy']);

        // ── Tutoring marketplace ─────────────────────────────────────────
        // TUTOR LANE (ADR-012 relationship access: the tutor_profiles row is
        // the credential — no membership, no context headers).
        Route::prefix('tutoring')->group(function (): void {
            Route::get('profile', [TutorProfileController::class, 'show']);
            Route::put('profile', [TutorProfileController::class, 'upsert']);
            Route::post('profile/submit', [TutorProfileController::class, 'submit']);
            Route::post('profile/attachments', [TutorProfileController::class, 'storeAttachment']);
            Route::delete('profile/attachments/{attachment}', [TutorProfileController::class, 'destroyAttachment']);
            // The teacher shortcut: import documents from my own employee file.
            Route::get('profile/employee-attachments', [TutorProfileController::class, 'employeeAttachments']);
            Route::post('profile/import-employee-attachments', [TutorProfileController::class, 'importEmployeeAttachments']);
            Route::put('profile/payout-account', [TutorProfileController::class, 'updatePayoutAccount']);
            Route::get('dashboard', [TutorProfileController::class, 'dashboard']);

            Route::get('requests', [TutoringRequestController::class, 'inbox']);
            Route::post('requests/{tutoringRequest}/accept', [TutoringRequestController::class, 'accept']);
            Route::post('requests/{tutoringRequest}/decline', [TutoringRequestController::class, 'decline']);

            Route::get('engagements', [TutoringEngagementController::class, 'index']);
            Route::get('engagements/{engagement}', [TutoringEngagementController::class, 'show']);
            Route::post('engagements/{engagement}/pause', [TutoringEngagementController::class, 'pause']);
            Route::post('engagements/{engagement}/resume', [TutoringEngagementController::class, 'resume']);
            Route::post('engagements/{engagement}/end', [TutoringEngagementController::class, 'end']);

            Route::get('engagements/{engagement}/thread', [TutoringEngagementController::class, 'thread']);
            Route::get('engagements/{engagement}/sessions', [TutoringSessionController::class, 'index']);
            Route::post('engagements/{engagement}/sessions', [TutoringSessionController::class, 'store']);
            Route::post('sessions/{session}/log', [TutoringSessionController::class, 'log']);
            Route::post('sessions/{session}/cancel', [TutoringSessionController::class, 'cancel']);
            Route::post('sessions/{session}/confirm', [TutoringSessionController::class, 'confirm']);
            Route::post('sessions/{session}/dispute', [TutoringSessionController::class, 'dispute']);

            Route::get('earnings', [TutorPayoutController::class, 'mine']);
            Route::post('payouts', [TutorPayoutController::class, 'store']);

            Route::get('boosts/plans', [TutorBoostController::class, 'plans']);
            Route::get('boosts', [TutorBoostController::class, 'mine']);
            Route::post('boosts', [TutorBoostController::class, 'store']);

            Route::post('cycles/{cycle}/review', [TutorReviewController::class, 'store']);
        });

        // FAMILY LANE (/me): hiring, monthly escrow payments, confirmations.
        Route::prefix('me/tutoring')->group(function (): void {
            Route::get('requests', [TutoringRequestController::class, 'mine']);
            Route::post('requests', [TutoringRequestController::class, 'store'])->middleware('throttle:10,1');
            Route::post('requests/{tutoringRequest}/withdraw', [TutoringRequestController::class, 'withdraw']);
            Route::get('cycles', [TutoringCycleController::class, 'mine']);
            Route::post('cycles/{cycle}/pay', [TutoringCycleController::class, 'pay'])->middleware('throttle:10,1');
            // Family aliases over the same relationship-gated controllers
            // (ADR-012: families act through /me/*, never tutor-lane paths).
            Route::get('engagements', [TutoringEngagementController::class, 'index']);
            Route::get('engagements/{engagement}', [TutoringEngagementController::class, 'show']);
            Route::get('engagements/{engagement}/thread', [TutoringEngagementController::class, 'thread']);
            Route::post('engagements/{engagement}/end', [TutoringEngagementController::class, 'end']);
            Route::get('engagements/{engagement}/sessions', [TutoringSessionController::class, 'index']);
            Route::post('sessions/{session}/confirm', [TutoringSessionController::class, 'confirm']);
            Route::post('sessions/{session}/dispute', [TutoringSessionController::class, 'dispute']);
            Route::post('sessions/{session}/cancel', [TutoringSessionController::class, 'cancel']);
            Route::post('cycles/{cycle}/review', [TutorReviewController::class, 'store']);
        });

        // TEMARI AI: the /ai chat surface (agents in app/Ai). Conversations
        // are strictly self-scoped; the lane + workspace context freeze at
        // creation and every prompt re-checks entitlement/quota. The message
        // endpoint streams SSE (Vercel AI data protocol).
        Route::prefix('ai')->group(function (): void {
            Route::get('context', [AiConversationController::class, 'context']);
            Route::get('conversations', [AiConversationController::class, 'index']);
            Route::post('conversations', [AiConversationController::class, 'store'])->middleware('throttle:30,1');
            Route::patch('conversations/{conversation}', [AiConversationController::class, 'update']);
            Route::delete('conversations/{conversation}', [AiConversationController::class, 'destroy']);
            Route::get('conversations/{conversation}/messages', [AiConversationController::class, 'messages']);
            Route::post('conversations/{conversation}/messages', [AiChatController::class, 'send'])->middleware('throttle:20,1');
            Route::post('conversations/{conversation}/messages/regenerate', [AiChatController::class, 'regenerate'])->middleware('throttle:20,1');
            Route::get('conversations/{conversation}/messages/{message}/attachments/{index}', [AiConversationController::class, 'attachment'])
                ->whereNumber('index');
            Route::post('feedback', [AiFeedbackController::class, 'store'])->middleware('throttle:60,1');
            // Embedded ✨ generators (question studio, planner, comments, letters).
            Route::post('actions', [AiActionController::class, 'run'])->middleware('throttle:20,1');
        });

        // The B2C parent/student AI upgrade — Temari's own subscription,
        // collected via gateway (never school fee money, CLAUDE.md §11).
        Route::prefix('me/ai')->group(function (): void {
            Route::get('plans', [AiSubscriptionController::class, 'plans']);
            Route::get('subscription', [AiSubscriptionController::class, 'mine']);
            Route::post('subscribe', [AiSubscriptionController::class, 'subscribe'])->middleware('throttle:10,1');
        });

        // School Plan AI entitlement — platform staff grant/revoke (schools
        // pay the plan offline; never self-service).
        Route::get('schools/{school}/ai-plan', [AiPlanController::class, 'show']);
        Route::post('schools/{school}/ai-plan', [AiPlanController::class, 'grant']);
        Route::delete('schools/{school}/ai-plan', [AiPlanController::class, 'revoke']);

        // PAYER LANE: gateway checkout return-page poll + the simulator.
        Route::get('payments/transactions/{txRef}', [GatewayTransactionController::class, 'show']);
        Route::post('payments/simulate/{txRef}', [GatewayTransactionController::class, 'simulate']);

        // PLATFORM LANE: tutor vetting (`tutors.review`), the money console
        // (`marketplace.manage`) and the gateway matrix (`gateways.manage`).
        Route::prefix('marketplace')->group(function (): void {
            Route::get('tutors', [TutorAdminController::class, 'index']);
            Route::get('tutors/{tutorProfile}', [TutorAdminController::class, 'show']);
            Route::post('tutors/{tutorProfile}/approve', [TutorAdminController::class, 'approve']);
            Route::post('tutors/{tutorProfile}/decline', [TutorAdminController::class, 'decline']);
            Route::post('tutors/{tutorProfile}/suspend', [TutorAdminController::class, 'suspend']);
            Route::post('tutors/{tutorProfile}/reinstate', [TutorAdminController::class, 'reinstate']);
            Route::patch('tutors/{tutorProfile}/commission', [TutorAdminController::class, 'setCommission']);

            Route::get('cycles/stats', [TutoringCycleController::class, 'stats']);
            Route::get('cycles', [TutoringCycleController::class, 'console']);
            Route::post('cycles/{cycle}/release', [TutoringCycleController::class, 'release']);
            Route::post('cycles/{cycle}/refund', [TutoringCycleController::class, 'refund']);
            Route::post('sessions/{session}/resolve', [TutoringSessionController::class, 'resolve']);

            Route::get('payouts', [TutorPayoutController::class, 'index']);
            Route::post('payouts/{payout}/approve', [TutorPayoutController::class, 'approve']);
            Route::post('payouts/{payout}/pay', [TutorPayoutController::class, 'pay']);
            Route::post('payouts/{payout}/reverse', [TutorPayoutController::class, 'reverse']);
        });

        // Payment gateway operator settings + transaction register.
        Route::get('payment-gateways', [PaymentGatewayController::class, 'show']);
        Route::put('payment-gateways', [PaymentGatewayController::class, 'update']);
        Route::get('payment-gateways/transactions', [PaymentGatewayController::class, 'transactions']);
    });
});
