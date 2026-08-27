<?php

namespace Database\Seeders;

use App\Actions\ComputePayrollAction;
use App\Actions\ComputeTermResultsAction;
use App\Actions\SaveAcademicYearAction;
use App\Actions\SyncPositionMembershipsAction;
use App\Enums\AccountStatus;
use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\ConcessionCategory;
use App\Enums\ConcessionStatus;
use App\Enums\CycleStatus;
use App\Enums\DiscountType;
use App\Enums\EnrollmentStatus;
use App\Enums\FeeType;
use App\Enums\InvoiceStatus;
use App\Enums\LessonCoverage;
use App\Enums\LessonPlanStatus;
use App\Enums\LessonStage;
use App\Enums\PurchaseOrderStatus;
use App\Enums\QuestionType;
use App\Enums\RequisitionStatus;
use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Enums\StockTakeStatus;
use App\Enums\TermStatus;
use App\Enums\TextbookLoanStatus;
use App\Enums\TimetableVersionStatus;
use App\Enums\TutorStatus;
use App\Models\AbsenceExcuse;
use App\Models\AcademicYear;
use App\Models\AiConversation;
use App\Models\AiSubscription;
use App\Models\AnnualLessonPlan;
use App\Models\AssessmentResult;
use App\Models\AssetAssignment;
use App\Models\AssetUnit;
use App\Models\Assignment;
use App\Models\AttendanceRecord;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Budget;
use App\Models\CardRequest;
use App\Models\ChatMessage;
use App\Models\ChatMessageTemplate;
use App\Models\ContinuousAssessment;
use App\Models\Conversation;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Device;
use App\Models\DeviceEvent;
use App\Models\Employee;
use App\Models\EmployeeAttendanceRecord;
use App\Models\Expense;
use App\Models\FeeConcession;
use App\Models\FeeStructure;
use App\Models\FinanceCategory;
use App\Models\GatewayTransaction;
use App\Models\GradeLevel;
use App\Models\GradingPolicy;
use App\Models\GradingScale;
use App\Models\HealthCondition;
use App\Models\Holiday;
use App\Models\IdCard;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Marklist;
use App\Models\Membership;
use App\Models\Notification;
use App\Models\OtherIncome;
use App\Models\ParentProfile;
use App\Models\Payment;
use App\Models\PaymentVerification;
use App\Models\PayrollRun;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Quiz;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Room;
use App\Models\School;
use App\Models\SchoolDirectoryEntry;
use App\Models\SchoolProgram;
use App\Models\Section;
use App\Models\SectionHomeroom;
use App\Models\StockTake;
use App\Models\StockTakeLine;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Models\StudentImport;
use App\Models\StudentPromotion;
use App\Models\StudentTermResult;
use App\Models\StudentTransferRequest;
use App\Models\StudentWithdrawal;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\TeacherAvailability;
use App\Models\TeacherEvaluation;
use App\Models\Term;
use App\Models\TermPeriod;
use App\Models\TextbookLoan;
use App\Models\TimetableSlot;
use App\Models\TransferApplication;
use App\Models\TutoringEngagement;
use App\Models\TutoringRequest;
use App\Models\TutoringSession;
use App\Models\TutorProfile;
use App\Models\TutorReview;
use App\Models\User;
use App\Services\Chat\ChannelProvisioner;
use App\Services\FeeConcessionResolver;
use App\Services\Inventory\StockLedger;
use App\Services\Timetable\TimetableSolver;
use App\Services\Tutoring\CycleBiller;
use App\Services\Tutoring\CycleReleaser;
use App\Services\Tutoring\PayoutService;
use App\Services\Tutoring\TutorRating;
use App\Support\EthiopianDate;
use App\Support\EvaluationPolicy;
use App\Support\GradeOffering;
use App\Support\LeavePolicy;
use App\Support\PublicId;
use App\Support\ReportCardSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Realistic Ethiopian demo data for manual testing — NOT part of the default
 * seed. Preferred entry point (also resets the DB and, in production, gates
 * everything behind ADMIN_PASSWORD from .env):
 *
 *     php artisan temari:seed-demo [--fresh]
 *
 * Builds 10 schools (1–6 branches each) with staff in every role, 1–4
 * academic years of data per school (completed years carry promoted
 * enrollments + frozen report cards · 2018 E.C. active with Semester 1
 * closed · 2019 E.C. planned), sections, ~2,000 students with guardians,
 * registration + tuition fees (some unpaid → pending enrollments), a full
 * Semester-1 continuous assessment with computed report cards, two weeks of attendance,
 * a published timetable for Unity Academy's Main branch and a couple of live
 * transfer requests.
 *
 * Billing is fully fleshed out: school-owned payment accounts (CBE, Telebirr
 * wallet, sometimes a third bank) attached per branch and to each fee as
 * collection accounts; semester tuition invoices in every state (paid /
 * partial / unpaid / OVERDUE) with payments that snapshot their collection
 * account, carry references and a recording finance officer, spread over
 * months for the per-account reports; and fee concessions in every lifecycle
 * state — policy suggestions (sibling / employee-child, pending + approved),
 * manual merit/hardship/full-scholarship grants, a guardian-level lifetime
 * discount, plus revoked/rejected rows on the showcase branch. Tuition
 * invoices run through FeeConcessionResolver, so stamped discounts are real.
 *
 * Every seeded account signs in with PIN `123456` — numeric, because the
 * login/signup/reset forms all enforce the digits-only PIN standard.
 */
class DemoSeeder extends Seeder
{
    /** Set by the temari:seed-demo command once the ADMIN_PASSWORD check passes. */
    public static bool $authorized = false;

    private const PASSWORD_PLAIN = '123456';

    private const SCHOOLS = [
        'Unity Academy', 'Addis Raey Academy', 'Rift Valley Academy',
        'Hibret Primary & Secondary School', 'Lem Academy', 'Abugida Academy',
        'Selam International School', 'Entoto Vision Academy',
        'Awash Academy', 'Fana Community School',
    ];

    private const BRANCH_NAMES = [
        'Main', 'Bole', 'CMC', 'Ayat', 'Sarbet', 'Megenagna', 'Piassa',
        'Kality', 'Summit', 'Gerji', 'Adama', 'Hawassa', 'Bahir Dar', 'Bishoftu',
    ];

    private const CITIES = ['Addis Ababa', 'Addis Ababa', 'Addis Ababa', 'Adama', 'Hawassa', 'Bahir Dar', 'Bishoftu'];

    private const SUB_CITIES = ['Bole', 'Yeka', 'Kirkos', 'Arada', 'Nifas Silk-Lafto', 'Gullele', 'Lideta', 'Akaki Kality'];

    private const MALE_NAMES = [
        'Abel', 'Bereket', 'Dawit', 'Elias', 'Fitsum', 'Girma', 'Henok', 'Kaleb',
        'Mikias', 'Nahom', 'Robel', 'Samuel', 'Tewodros', 'Yared', 'Yonas', 'Biruk',
        'Natnael', 'Amanuel', 'Eyob', 'Kirubel', 'Gemechu', 'Tolessa', 'Abdi', 'Lelisa',
    ];

    private const FEMALE_NAMES = [
        'Betelhem', 'Blen', 'Eden', 'Feven', 'Hanna', 'Kalkidan', 'Liya', 'Mahlet',
        'Meron', 'Rahel', 'Saron', 'Selam', 'Tsion', 'Winta', 'Hiwot', 'Marta',
        'Ruth', 'Sara', 'Lidya', 'Bethel', 'Chaltu', 'Gadise', 'Lensa', 'Bontu',
    ];

    private const FATHER_NAMES = [
        'Abebe', 'Alemu', 'Bekele', 'Desta', 'Gebre', 'Girma', 'Haile', 'Kebede',
        'Lemma', 'Mekonnen', 'Mulugeta', 'Tadesse', 'Tesfaye', 'Worku', 'Yohannes',
        'Assefa', 'Demissie', 'Fikre', 'Getachew', 'Negash', 'Gemeda', 'Fufa', 'Dinsa',
    ];

    private string $passwordHash;

    private int $phoneCounter = 12000001;

    private int $branchCodeCounter = 1;

    /** @var array<int, GradeLevel> keyed by code */
    private array $grades = [];

    /** @var array<string, Collection<int, Subject>> per-grade curriculum cache */
    private array $curriculum = [];

    /** @var list<array{label: string, phone: string}> */
    private array $sampleLogins = [];

    /** @var array<int, list<BankAccount>> collection accounts per school id */
    private array $schoolAccounts = [];

    /** The current branch's finance officer — payments record who took them. */
    private ?int $financeUserId = null;

    public function run(): void
    {
        if (app()->isProduction() && ! self::$authorized) {
            throw new \RuntimeException(
                'DemoSeeder is blocked in production — run `php artisan temari:seed-demo` with the admin password.',
            );
        }

        mt_srand(20260710); // deterministic — re-runs build the same world

        $this->passwordHash = Hash::make(self::PASSWORD_PLAIN);
        $this->grades = GradeLevel::orderBy('sort_order')->get()->keyBy('code')->all();

        foreach (self::SCHOOLS as $index => $name) {
            $this->command?->info(sprintf('[%d/10] %s', $index + 1, $name));
            $this->buildSchool($index, $name);
        }

        $this->createTransferRequests();
        $this->buildTransferApplication();
        $this->buildStudentImports();
        $this->buildInventory();
        $this->buildTutoringMarketplace();
        $this->buildTemariAi();
        $this->buildNotifications();
        $this->printSummary();
    }

    // ───────────────────────── Inventory ─────────────────────────

    /**
     * The showcase school's store in demo-worthy states: a stocked item
     * master (one item deliberately low), a bin-card ledger with receives /
     * issues / a write-off, requisitions in every workflow state, a pending
     * and a partially received purchase order, and a posted stock take.
     */
    private function buildInventory(): void
    {
        $branch = Branch::where('school_id', School::query()->min('id'))->orderBy('id')->first();

        if ($branch === null) {
            return;
        }

        $roleUser = fn (Role $role) => User::query()
            ->whereHas('memberships', fn ($q) => $q
                ->where('branch_id', $branch->id)->where('role', $role->value)->where('is_active', true))
            ->orderBy('id')
            ->first();

        $director = $roleUser(Role::Director);
        $teacher = $roleUser(Role::Teacher);

        if ($director === null || $teacher === null) {
            return;
        }

        if (InventoryCategory::query()->whereNull('school_id')->doesntExist()) {
            $this->call(InventoryCategorySeeder::class);
        }

        $categories = InventoryCategory::query()->whereNull('school_id')->pluck('id', 'name');

        // A custom school category alongside the platform seeds.
        InventoryCategory::create([
            'school_id' => $branch->school_id,
            'name' => 'Graduation & events',
            'icon' => 'party-popper',
        ]);

        // name, category, unit, reorder level, is_asset, unit cost, opening qty
        $catalog = [
            ['Chalk (box of 100)', 'Stationery & office supplies', 'box', 20, false, 180, 25],
            ['A4 paper', 'Stationery & office supplies', 'ream', 30, false, 450, 120],
            ['Whiteboard marker', 'Stationery & office supplies', 'piece', 40, false, 35, 200],
            ['Exercise book (48 pages)', 'Stationery & office supplies', 'piece', 100, false, 30, 800],
            ['Student desk (two-seater)', 'Furniture', 'piece', null, true, 3800, 60],
            ['Teacher chair', 'Furniture', 'piece', null, true, 1500, 18],
            ['Projector', 'ICT & electronics', 'piece', null, true, 28000, 4],
            ['Desktop computer', 'ICT & electronics', 'piece', null, true, 35000, 12],
            ['Microscope', 'Laboratory equipment & supplies', 'piece', null, true, 12500, 6],
            ['Beaker set', 'Laboratory equipment & supplies', 'set', 5, false, 900, 14],
            ['Football', 'Sports & physical education', 'piece', 3, false, 850, 8],
            ['Liquid soap (5L)', 'Cleaning & sanitation', 'bottle', 10, false, 600, 30],
        ];

        $ledger = app(StockLedger::class);
        $items = [];

        foreach ($catalog as [$name, $category, $unit, $reorder, $isAsset, $cost, $opening]) {
            $item = InventoryItem::create([
                'school_id' => $branch->school_id,
                'inventory_category_id' => $categories[$category],
                'name' => $name,
                'unit' => $unit,
                'is_asset' => $isAsset,
                'reorder_level' => $reorder,
            ]);

            $ledger->post($branch->school_id, $branch->id, $item, StockMovementType::Receive, $opening, [
                'unit_cost' => $cost,
                'supplier_name' => 'Opening stock',
                'note' => 'Opening balance',
            ], $director->id);

            $items[$name] = $item;
        }

        // Everyday ledger traffic: issues, a return, a write-off — and the
        // chalk issue drops it below its reorder level (the low-stock tile).
        $ledger->post($branch->school_id, $branch->id, $items['Chalk (box of 100)'], StockMovementType::Issue, 10, [
            'recipient' => $teacher->name,
        ], $director->id);
        $ledger->post($branch->school_id, $branch->id, $items['A4 paper'], StockMovementType::Issue, 15, [
            'recipient' => 'Registrar office',
        ], $director->id);
        $ledger->post($branch->school_id, $branch->id, $items['Whiteboard marker'], StockMovementType::Return, 5, [
            'recipient' => $teacher->name,
            'note' => 'Unused after exam week',
        ], $director->id);
        $ledger->post($branch->school_id, $branch->id, $items['Beaker set'], StockMovementType::WriteOff, 2, [
            'note' => 'Broken during grade 10 lab session',
        ], $director->id);

        // Requisitions in every state of the Model-22 workflow.
        $makeRequisition = function (User $requester, array $lines, RequisitionStatus $status, ?string $purpose = null) use ($branch, $director, $items): Requisition {
            $requisition = Requisition::create([
                'school_id' => $branch->school_id,
                'branch_id' => $branch->id,
                'status' => $status,
                'requested_by' => $requester->id,
                'purpose' => $purpose,
                'decided_by' => $status === RequisitionStatus::Pending ? null : $director->id,
                'decided_at' => $status === RequisitionStatus::Pending ? null : now()->subDays(mt_rand(1, 5)),
                'decline_reason' => $status === RequisitionStatus::Declined ? 'Stock is reserved for the national exam week — re-request after.' : null,
                'fulfilled_at' => $status === RequisitionStatus::Issued ? now()->subDays(1) : null,
            ]);

            foreach ($lines as [$name, $qty]) {
                RequisitionItem::create([
                    'requisition_id' => $requisition->id,
                    'inventory_item_id' => $items[$name]->id,
                    'quantity_requested' => $qty,
                    'quantity_approved' => $status === RequisitionStatus::Pending ? null : $qty,
                    'quantity_issued' => $status === RequisitionStatus::Issued ? $qty : 0,
                ]);
            }

            return $requisition;
        };

        $makeRequisition($teacher, [['Whiteboard marker', 12], ['Chalk (box of 100)', 2]], RequisitionStatus::Pending, 'Grade 9 classrooms — second semester restock');
        $makeRequisition($teacher, [['Exercise book (48 pages)', 45]], RequisitionStatus::Approved, 'Replacement books for grade 1 section A');
        $makeRequisition($teacher, [['Football', 4]], RequisitionStatus::Declined, 'Inter-class tournament');

        $issued = $makeRequisition($teacher, [['A4 paper', 10]], RequisitionStatus::Issued, 'Exam paper printing');
        $ledger->post($branch->school_id, $branch->id, $items['A4 paper'], StockMovementType::Issue, 10, [
            'requisition_id' => $issued->id,
            'recipient' => $teacher->name,
        ], $director->id);

        // Purchase orders: one awaiting countersignature, one approved and
        // half-landed (the receive is real ledger history).
        $pending = PurchaseOrder::create([
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'supplier_name' => 'Mega Stationery PLC',
            'supplier_phone' => '0911223344',
            'status' => PurchaseOrderStatus::Pending,
            'expected_on' => now()->addDays(10)->toDateString(),
            'note' => 'Second-semester stationery restock',
            'ordered_by' => $director->id,
        ]);
        PurchaseOrderItem::create(['purchase_order_id' => $pending->id, 'inventory_item_id' => $items['A4 paper']->id, 'quantity' => 100, 'unit_cost' => 440]);
        PurchaseOrderItem::create(['purchase_order_id' => $pending->id, 'inventory_item_id' => $items['Chalk (box of 100)']->id, 'quantity' => 40, 'unit_cost' => 175]);
        $pending->refreshTotalCost();

        $landed = PurchaseOrder::create([
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'supplier_name' => 'Blue Nile Lab Supplies',
            'status' => PurchaseOrderStatus::Approved,
            'note' => 'Lab consumables for the new science block',
            'ordered_by' => $director->id,
            // The countersigner is the school's principal (school-scoped
            // membership), honouring the four-eyes rule even in demo data.
            'decided_by' => User::query()
                ->whereHas('memberships', fn ($q) => $q
                    ->where('school_id', $branch->school_id)->where('role', Role::Principal->value)->where('is_active', true))
                ->orderBy('id')->value('id') ?? $director->id,
            'decided_at' => now()->subDays(4),
        ]);
        PurchaseOrderItem::create(['purchase_order_id' => $landed->id, 'inventory_item_id' => $items['Beaker set']->id, 'quantity' => 10, 'unit_cost' => 880, 'received_quantity' => 6]);
        $landed->refreshTotalCost();
        $ledger->post($branch->school_id, $branch->id, $items['Beaker set'], StockMovementType::Receive, 6, [
            'purchase_order_id' => $landed->id,
            'unit_cost' => 880,
            'supplier_name' => $landed->supplier_name,
            'reference' => 'DN-4821',
        ], $director->id);

        // A posted stock take over the sports store: one surplus, one loss.
        $stockTake = StockTake::create([
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'inventory_category_id' => $categories['Sports & physical education'],
            'status' => StockTakeStatus::Posted,
            'note' => 'Semester-end count',
            'started_by' => $director->id,
            'posted_by' => $director->id,
            'posted_at' => now()->subDays(2),
        ]);
        StockTakeLine::create([
            'stock_take_id' => $stockTake->id,
            'inventory_item_id' => $items['Football']->id,
            'expected_quantity' => 8,
            'counted_quantity' => 7,
        ]);
        $ledger->post($branch->school_id, $branch->id, $items['Football'], StockMovementType::Adjustment, -1, [
            'stock_take_id' => $stockTake->id,
            'note' => 'Stock take reconciliation',
        ], $director->id);

        // ── Phase 2: the property register ──
        $makeUnit = fn (InventoryItem $item, array $attrs = []) => AssetUnit::create([
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'inventory_item_id' => $item->id,
            'tag' => PublicId::generate('asset_units', 'tag'),
            'condition' => AssetCondition::Good,
            'status' => AssetStatus::InStore,
            'acquired_on' => now()->subMonths(mt_rand(2, 10))->toDateString(),
            'unit_cost' => $item->is_asset ? 28000 : null,
            ...$attrs,
        ]);

        $teacherEmployee = Employee::query()
            ->where('branch_id', $branch->id)->where('user_id', $teacher->id)->first();
        $room = Room::query()->where('branch_id', $branch->id)->first();

        // Projectors: one with its teacher, one in repair, two on the shelf.
        $held = $makeUnit($items['Projector'], ['serial_number' => 'EPSON-EB-X06-411', 'status' => AssetStatus::Assigned]);
        if ($teacherEmployee !== null) {
            AssetAssignment::create([
                'school_id' => $branch->school_id, 'branch_id' => $branch->id,
                'asset_unit_id' => $held->id, 'holder_type' => 'employee',
                'employee_id' => $teacherEmployee->id, 'assigned_on' => now()->subDays(20)->toDateString(),
                'note' => 'Grade 9 physics classes', 'assigned_by' => $director->id,
            ]);
        } else {
            $held->update(['status' => AssetStatus::InStore]);
        }
        $makeUnit($items['Projector'], ['serial_number' => 'EPSON-EB-X06-412', 'status' => AssetStatus::UnderRepair, 'condition' => AssetCondition::Poor, 'note' => 'Lamp replacement pending']);
        $makeUnit($items['Projector'], ['serial_number' => 'EPSON-EB-X06-413']);
        $makeUnit($items['Projector'], ['serial_number' => 'EPSON-EB-X06-414', 'condition' => AssetCondition::New]);

        // A desktop lives in a room (fixed installation custody).
        $desktop = $makeUnit($items['Desktop computer'], ['unit_cost' => 35000, 'status' => $room !== null ? AssetStatus::Assigned : AssetStatus::InStore]);
        if ($room !== null) {
            AssetAssignment::create([
                'school_id' => $branch->school_id, 'branch_id' => $branch->id,
                'asset_unit_id' => $desktop->id, 'holder_type' => 'room',
                'room_id' => $room->id, 'assigned_on' => now()->subMonths(3)->toDateString(),
                'note' => 'ICT lab workstation', 'assigned_by' => $director->id,
            ]);
        }

        // ── Phase 3: textbook lending ──
        $enrollment = StudentEnrollment::query()
            ->where('branch_id', $branch->id)->where('status', 'active')
            ->whereNotNull('section_id')
            ->orderBy('id')->first();

        if ($enrollment !== null) {
            $book = InventoryItem::create([
                'school_id' => $branch->school_id,
                'inventory_category_id' => $categories['Textbooks & reference books'],
                'name' => 'Mathematics Student Textbook',
                'unit' => 'piece',
                'reorder_level' => 20,
            ]);
            $ledger->post($branch->school_id, $branch->id, $book, StockMovementType::Receive, 120, [
                'unit_cost' => 260, 'supplier_name' => 'MoE distribution', 'note' => 'Annual textbook allocation',
            ], $director->id);

            $classmates = StudentEnrollment::query()
                ->where('section_id', $enrollment->section_id)
                ->where('academic_year_id', $enrollment->academic_year_id)
                ->where('status', 'active')
                ->orderBy('id')->limit(8)->get();

            $ledger->post($branch->school_id, $branch->id, $book, StockMovementType::Issue, $classmates->count(), [
                'recipient' => 'Section textbooks',
            ], $director->id);

            foreach ($classmates as $i => $mate) {
                $loan = TextbookLoan::create([
                    'school_id' => $branch->school_id, 'branch_id' => $branch->id,
                    'academic_year_id' => $mate->academic_year_id,
                    'inventory_item_id' => $book->id, 'student_id' => $mate->student_id,
                    'section_id' => $mate->section_id, 'quantity' => 1,
                    'status' => TextbookLoanStatus::Out, 'issued_by' => $director->id,
                ]);

                if ($i === 0) { // one returned early (transfer student)
                    $ledger->post($branch->school_id, $branch->id, $book, StockMovementType::Return, 1, [
                        'recipient' => 'Textbook returns',
                    ], $director->id);
                    $loan->update(['status' => TextbookLoanStatus::Returned, 'returned_at' => now()->subDays(5)]);
                } elseif ($i === 1) { // one lost — no ledger movement (issue already took it off the shelf)
                    $loan->update(['status' => TextbookLoanStatus::Lost, 'lost_at' => now()->subDays(2)]);
                }
            }
        }
    }

    // ───────────────────────── Temari AI ─────────────────────────

    /**
     * The AI feature in demo-worthy states: the showcase school holds an
     * active School Plan (leadership/teacher AI unlocked), the demo parent
     * carries an active B2C subscription, and both demo accounts own a few
     * believable chat sessions (metadata + transcripts in the SDK tables).
     */
    private function buildTemariAi(): void
    {
        $school = School::query()->orderBy('id')->first();
        $school?->forceFill(['ai_plan_until' => now()->addMonths(6)->toDateString()])->save();

        $parentLogin = collect($this->sampleLogins)->firstWhere('label', 'Parent (child at Unity Academy)');
        $parent = $parentLogin === null ? null : User::query()->where('phone', $parentLogin['phone'])->first();

        if ($parent !== null) {
            AiSubscription::create([
                'user_id' => $parent->id, 'plan' => 'monthly', 'amount' => 199,
                'status' => 'active', 'starts_at' => now()->subDays(10), 'ends_at' => now()->addDays(20),
            ]);

            $this->seedAiConversation($parent, 'parent', 'How is Liya doing this term?', [
                ['user', 'How is my child doing this term?'],
                ['assistant', "Liya is doing well overall — her average is 78 and she ranks 6th of 42 in her section. Mathematics (62) is her weakest subject this term; English (88) is her strongest. Attendance is strong: only one absence.\n\n**Where to focus**\n- 20 minutes of maths practice on school nights\n- Ask her maths teacher about extra exercises\n- Praise the English progress — it is real"],
            ]);
        }

        $leader = User::query()
            ->whereHas('memberships', fn ($q) => $q->where('role', Role::Principal->value)->where('school_id', $school?->id))
            ->first();

        if ($leader !== null && $school !== null) {
            $this->seedAiConversation($leader, 'leadership', 'Weakest subjects this term', [
                ['user', 'Which subjects are weakest this term?'],
                ['assistant', "Based on the frozen term results, the three weakest subjects school-wide are:\n\n| Subject | Average | Pass rate |\n| --- | --- | --- |\n| Mathematics | 58.4 | 61% |\n| Physics | 61.2 | 66% |\n| Chemistry | 64.9 | 72% |\n\nRecommended next steps: a re-teaching block for Mathematics in grades 9–10, and peer support between the two physics teachers."],
            ], $school->id);
        }
    }

    /**
     * @param  list<array{0: string, 1: string}>  $turns
     */
    private function seedAiConversation(User $user, string $lane, string $title, array $turns, ?int $schoolId = null): void
    {
        $uuid = (string) Str::uuid7();

        DB::table('agent_conversations')->insert([
            'id' => $uuid, 'user_id' => $user->id, 'title' => $title,
            'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
        ]);

        foreach ($turns as $index => [$role, $content]) {
            DB::table('agent_conversation_messages')->insert([
                'id' => (string) Str::uuid7(), 'conversation_id' => $uuid,
                'user_id' => $user->id, 'agent' => 'demo', 'role' => $role,
                'content' => $content, 'attachments' => '[]', 'tool_calls' => '[]',
                'tool_results' => '[]', 'usage' => '[]', 'meta' => '[]',
                'created_at' => now()->subDay()->addMinutes($index),
                'updated_at' => now()->subDay()->addMinutes($index),
            ]);
        }

        AiConversation::create([
            'uuid' => $uuid, 'user_id' => $user->id, 'lane' => $lane,
            'school_id' => $schoolId, 'title' => $title, 'last_message_at' => now()->subDay(),
        ]);
    }

    // ───────────────────────── tutoring marketplace ─────────────────────────

    /**
     * A lived-in marketplace: approved tutors in the public directory (some
     * boosted), a pending application in the review queue, a declined and a
     * suspended profile, and one full money story with the demo parent —
     * released month (wallet + review + paid payout), funded current month
     * with sessions, plus an unpaid bill and a pending hire request.
     * Everything is created directly (no Notifier — a seeder must not text).
     */
    private function buildTutoringMarketplace(): void
    {
        $subjects = Subject::query()->whereNull('school_id')->orderBy('id')->limit(30)->get();
        if ($subjects->isEmpty()) {
            return;
        }

        $headlines = [
            'Mathematics made simple — Grade 7–12 & EUEE prep',
            'Physics and Chemistry specialist, 8 years of classroom experience',
            'English fluency and writing coach for primary students',
            'Grade 8 national exam intensive — proven results',
            'Biology & Chemistry for preparatory students',
            'Patient KG–Grade 4 all-subjects home tutor',
            'ICT and Mathematics — modern, practical lessons',
        ];
        $cities = ['Addis Ababa', 'Addis Ababa', 'Addis Ababa', 'Adama', 'Hawassa', 'Bahir Dar', 'Addis Ababa'];
        $statuses = ['approved', 'approved', 'approved', 'approved', 'pending', 'declined', 'suspended'];

        $profiles = [];

        foreach ($statuses as $i => $status) {
            // Mix real teachers (employee file → the import shortcut story)
            // with standalone applicants.
            $teacherUser = $i < 3
                ? User::query()->whereHas('employee')->whereDoesntHave('tutorProfile')->orderBy('id')->skip($i * 3)->first()
                : null;
            $user = $teacherUser ?? $this->makeUser();

            $profile = TutorProfile::create([
                'user_id' => $user->id,
                'headline' => $headlines[$i],
                'bio' => 'I am a dedicated Ethiopian educator who believes every student can excel with the right guidance. '
                    .'My lessons are structured, friendly and focused on real exam results. I tailor each month\'s plan to the learner.',
                'hourly_rate' => [350, 450, 250, 500, 300, 280, 320][$i],
                'additional_child_rate' => $i % 2 === 0 ? [250, null, 180, null, 200, null, 220][$i] : null,
                'mode' => ['both', 'online', 'in_person', 'both', 'online', 'both', 'in_person'][$i],
                'region' => 'Addis Ababa',
                'city' => $cities[$i],
                'sub_city' => ['Bole', 'Yeka', 'Kirkos', null, null, 'Arada', 'Lideta'][$i],
                'languages' => [['am', 'en'], ['am', 'en'], ['en'], ['am', 'en', 'om'], ['am'], ['am', 'en'], ['om', 'en']][$i],
                'education_level' => ['BSc Mathematics', 'MSc Physics', 'BA English', 'BEd Mathematics', 'BSc Biology', 'Diploma in Education', 'BSc Computer Science'][$i],
                'experience_years' => [6, 8, 4, 10, 3, 5, 4][$i],
                'fayda_id' => $fayda = '61402'.str_pad((string) (1000000 + $i * 137), 7, '0', STR_PAD_LEFT),
                'fayda_hash' => TutorProfile::hashFayda($fayda),
                'status' => $status,
                'submitted_at' => now()->subDays(20 - $i),
                'reviewed_at' => $status === 'pending' ? null : now()->subDays(15 - $i),
                'decline_reason' => $status === 'declined' ? 'The uploaded degree scan is unreadable — please re-upload a clear copy.' : null,
                'suspend_reason' => $status === 'suspended' ? 'Repeated no-shows reported by two families.' : null,
                'payout_bank_code' => '855',
                'payout_bank_name' => 'telebirr',
                'payout_account_number' => $user->phone !== null ? '0'.$user->phone : '0911000000',
                'payout_account_name' => $user->name,
                'boosted_until' => $i === 0 || $i === 3 ? now()->addDays(12) : null,
            ]);

            if ($profile->status === TutorStatus::Approved) {
                $profile->update(['slug' => $profile->allocateSlug()]);
            }

            foreach ($subjects->random(min(3, $subjects->count())) as $subject) {
                $profile->subjects()->firstOrCreate(['subject_id' => $subject->id], ['grade_sorts' => []]);
            }

            $profiles[] = $profile;

            if ($i === 0) {
                $this->sampleLogins[] = ['label' => 'Tutor (approved, boosted)', 'phone' => $user->phone];
            }
        }

        // Public ratings on the approved tutors need released cycles — build
        // the full money story on tutor #1 with the demo parent.
        $parentLogin = collect($this->sampleLogins)->firstWhere('label', 'Parent (child at Unity Academy)');
        $parent = $parentLogin === null ? null : User::query()->where('phone', $parentLogin['phone'])->first();
        $tutor = $profiles[0];

        if ($parent === null || $tutor->status !== TutorStatus::Approved) {
            return;
        }

        $student = Student::query()
            ->whereHas('guardians.parentProfile', fn ($q) => $q->where('user_id', $parent->id))
            ->first();

        $subjectPair = $tutor->subjects()->with('subject:id,name')->limit(2)->get()
            ->map(fn ($ts) => ['id' => $ts->subject_id, 'name' => $ts->subject?->name])->values()->all();

        $engagement = TutoringEngagement::create([
            'tutor_profile_id' => $tutor->id,
            'payer_user_id' => $parent->id,
            'student_id' => $student?->id,
            'subjects' => $subjectPair,
            'grade_label' => 'Grade 7',
            'mode' => 'both',
            'sessions_per_week' => 2,
            'hours_per_session' => 1.5,
            'hourly_rate' => $tutor->hourly_rate,
            'commission_percent' => 10,
            'status' => 'active',
            'started_on' => now()->subDays(45)->toDateString(),
        ]);

        $biller = app(CycleBiller::class);
        $now = CarbonImmutable::now('Africa/Addis_Ababa');
        $ec = EthiopianDate::fromGregorian($now);
        if ($ec['month'] === 13) {
            $ec = ['year' => $ec['year'] + 1, 'month' => 1];
        }
        $prev = EthiopianDate::addMonths($ec['year'], $ec['month'], -1);

        // Last month: funded, taught, confirmed, RELEASED → wallet + review.
        $lastCycle = $biller->ensurePeriodCycle($engagement, $prev['year'], $prev['month']);
        $lastCycle->update(['status' => 'funded', 'funded_at' => $lastCycle->starts_on]);
        GatewayTransaction::create([
            'tx_ref' => GatewayTransaction::allocateRef(),
            'gateway' => 'fake', 'purpose' => 'tutoring_cycle',
            'payable_type' => $lastCycle->getMorphClass(), 'payable_id' => $lastCycle->id,
            'user_id' => $parent->id, 'amount' => $lastCycle->amount_due,
            'status' => 'paid', 'paid_at' => $lastCycle->starts_on,
        ]);
        foreach (range(0, 5) as $n) {
            TutoringSession::create([
                'cycle_id' => $lastCycle->id, 'engagement_id' => $engagement->id,
                'scheduled_at' => CarbonImmutable::parse($lastCycle->starts_on)->addDays(2 + $n * 4)->setTime(17, 0),
                'duration_hours' => 1.5, 'topic' => 'Unit '.($n + 1).' — guided practice',
                'status' => 'confirmed', 'logged_at' => now()->subDays(30 - $n), 'confirmed_at' => now()->subDays(29 - $n),
                'confirmed_by' => $parent->id,
            ]);
        }
        app(CycleReleaser::class)->release($lastCycle);

        TutorReview::create([
            'tutor_profile_id' => $tutor->id, 'engagement_id' => $engagement->id,
            'cycle_id' => $lastCycle->id, 'reviewer_user_id' => $parent->id,
            'direction' => TutorReview::FAMILY_TO_TUTOR,
            'rating' => 5, 'comment' => 'Wonderful tutor — my daughter finally enjoys mathematics. Punctual and very patient.',
            'is_public' => true,
        ]);
        app(TutorRating::class)->recompute($tutor);

        // A paid payout so the earnings screen tells the whole story.
        $payoutService = app(PayoutService::class);
        $superAdmin = User::query()->whereHas('memberships', fn ($q) => $q->where('role', Role::SuperAdmin->value))->first();
        if ($superAdmin !== null && (float) $tutor->fresh()->wallet_balance >= 500) {
            $payout = $payoutService->request($tutor->fresh(), 500);
            $payout = $payoutService->approve($payout, $superAdmin);
            $payoutService->markPaidManually($payout, $superAdmin, 'Demo payout — manual transfer');
        }

        // This month: funded, sessions in flight (one awaiting confirmation).
        $currentCycle = $biller->ensurePeriodCycle($engagement, $ec['year'], $ec['month']);
        if ($currentCycle->status === CycleStatus::AwaitingPayment) {
            $currentCycle->update(['status' => 'funded', 'funded_at' => now()->subDays(5)]);
        }
        TutoringSession::create([
            'cycle_id' => $currentCycle->id, 'engagement_id' => $engagement->id,
            'scheduled_at' => now()->subDays(2)->setTime(17, 0), 'duration_hours' => 1.5,
            'topic' => 'Fractions review', 'status' => 'logged', 'logged_at' => now()->subDay(),
            'meeting_url' => 'https://meet.jit.si/temari-tut-'.$engagement->id.'-demo',
        ]);
        TutoringSession::create([
            'cycle_id' => $currentCycle->id, 'engagement_id' => $engagement->id,
            'scheduled_at' => now()->addDays(2)->setTime(17, 0), 'duration_hours' => 1.5,
            'topic' => 'Linear equations', 'status' => 'scheduled',
            'meeting_url' => 'https://meet.jit.si/temari-tut-'.$engagement->id.'-demo2',
        ]);

        // A second engagement whose month is still UNPAID (the family's
        // payments screen shows a due bill) + a pending hire request for the
        // tutor inbox.
        $tutor2 = $profiles[1];
        $engagement2 = TutoringEngagement::create([
            'tutor_profile_id' => $tutor2->id, 'payer_user_id' => $parent->id,
            'student_id' => $student?->id,
            'subjects' => [['id' => $subjects[0]->id, 'name' => $subjects[0]->name]],
            'grade_label' => 'Grade 7', 'mode' => 'online',
            'sessions_per_week' => 1, 'hours_per_session' => 1,
            'hourly_rate' => $tutor2->hourly_rate, 'commission_percent' => 10,
            'status' => 'active', 'started_on' => now()->subDays(3)->toDateString(),
        ]);
        $biller->ensureCycleFor($engagement2);

        TutoringRequest::create([
            'tutor_profile_id' => $tutor->id,
            'requester_user_id' => $this->makeUser()->id,
            'student_id' => null,
            'subject_ids' => [$subjects[0]->id],
            'grade_label' => 'Grade 12',
            'message' => 'I am preparing for the EUEE and need intensive weekend support in mathematics.',
            'mode' => 'online', 'sessions_per_week' => 2, 'hours_per_session' => 2,
        ]);
    }

    /**
     * A lived-in notification feed for every sample login — the bell must
     * have something to show the moment a demo account signs in. Rows are
     * inserted directly (never through the Notifier: a seeder must not text
     * anyone); a mix of read and unread so the badge and the feed both demo.
     */
    private function buildNotifications(): void
    {
        $rowsByLabel = [
            'Parent (child at Unity Academy)' => [
                ['finance.invoice_issued', 'finance', ['student' => 'your child', 'fee' => 'Monthly Tuition — Meskerem', 'amount' => '1,500.00'], '/me/payments', false],
                ['attendance.absent', 'attendance', ['student' => 'your child', 'date' => now()->subDay()->toDateString()], '/me/attendance', false],
                ['finance.payment_received', 'finance', ['student' => 'your child', 'amount' => '1,500.00'], '/me/payments', true],
                ['academics.term_results_published', 'academics', ['student' => 'your child', 'term' => 'Semester 1'], '/me/children', true],
            ],
            'Director (Unity Academy — Main)' => [
                ['academics.marklist_submitted', 'approvals', ['teacher' => 'a teacher', 'subject' => 'Mathematics', 'section' => 'Grade 7A'], '/marklists', false],
                ['academics.weekly_plan_submitted', 'approvals', ['teacher' => 'a teacher', 'subject' => 'Mathematics', 'grade' => 'Grade 7', 'week' => now()->startOfWeek()->toDateString()], '/lesson-plans?tab=review', false],
                ['hr.leave_submitted', 'approvals', ['name' => 'a staff member', 'type' => 'Annual', 'days' => '3'], '/hr/leave', false],
                ['system.timetable_generated', 'system', ['term' => 'Semester 2'], '/timetable', true],
            ],
            'Teacher (Unity Academy — Main)' => [
                ['lms.submission_received', 'lms', ['title' => 'Chapter review questions', 'count' => 3], '/lms/assignments', false],
                ['academics.timetable_published', 'academics', ['term' => 'Semester 2'], '/timetable', true],
                ['hr.payslip_ready', 'hr', ['period' => 'Sene payroll'], '/hr/me?tab=payslips', true],
            ],
            'Student (Unity Academy)' => [
                ['lms.assignment_published', 'lms', ['title' => 'Chapter review questions', 'subject' => 'Mathematics', 'due' => now()->addDays(3)->toDateString()], '/me/learn', false],
                ['lms.assignment_graded', 'lms', ['title' => 'Lab report'], '/me/learn', true],
                ['academics.timetable_published', 'academics', ['term' => 'Semester 2'], '/me/timetable', true],
            ],
        ];

        foreach ($this->sampleLogins as $login) {
            $rows = $rowsByLabel[$login['label']] ?? null;
            $user = $rows === null ? null : User::where('phone', $login['phone'])->first();

            if ($rows === null || $user === null) {
                continue;
            }

            foreach ($rows as $i => [$event, $category, $data, $link, $read]) {
                (new Notification)->forceFill([
                    'user_id' => $user->id,
                    'event' => $event,
                    'category' => $category,
                    'data' => $data,
                    'link' => $link,
                    'read_at' => $read ? now()->subDays($i + 1) : null,
                    'created_at' => now()->subHours($i * 7 + 2),
                ])->save();
            }
        }
    }

    /**
     * The bulk-import studio in both of its demo-worthy states: a finished
     * run (report with imported/skipped/failed) and a draft mid-review
     * (ready + duplicate + fix-me rows). Rows are inserted directly — the
     * imported ones point at students the seeder already registered, so
     * nothing double-registers and nothing texts anyone.
     */
    private function buildStudentImports(): void
    {
        $branch = Branch::where('school_id', School::query()->min('id'))->orderBy('id')->first();
        $year = $branch ? AcademicYear::where('branch_id', $branch->id)->where('status', 'active')->first() : null;
        $creator = User::whereHas('memberships', fn ($q) => $q->where('branch_id', $branch?->id)->where('is_active', true))->first();

        if ($branch === null || $year === null || $creator === null) {
            return;
        }

        $gradeId = $this->grades['G1']->id;
        $students = Student::query()
            ->whereHas('enrollments', fn ($q) => $q->where('branch_id', $branch->id))
            ->orderBy('id')
            ->limit(5)
            ->get();

        $payload = fn (string $first, string $father, string $gender, array $extra = []): array => [
            'first_name' => $first,
            'father_name' => $father,
            'grandfather_name' => null,
            'gender' => $gender,
            'date_of_birth' => '2018-0'.mt_rand(1, 9).'-1'.mt_rand(0, 9),
            'grade_level_id' => $gradeId,
            'section_id' => null,
            'guardians' => [[
                'first_name' => 'Almaz',
                'father_name' => 'Bekele',
                'phone' => '09'.mt_rand(10000000, 99999999),
                'relationship' => 'mother',
                'is_primary' => true,
            ]],
            ...$extra,
        ];

        // 1. A completed run — the report the director shows the owner.
        $completed = StudentImport::create([
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'academic_year_id' => $year->id,
            'file_name' => 'gc-register-2018.xlsx',
            'status' => 'completed',
            'options' => ['send_sms' => false, 'create_student_accounts' => false],
            'total_rows' => 5,
            'imported_count' => 3,
            'skipped_count' => 1,
            'failed_count' => 1,
            'created_by' => $creator->id,
            'committed_at' => now()->subDays(3),
            'finished_at' => now()->subDays(3)->addMinutes(2),
        ]);

        foreach ($students->take(3)->values() as $i => $student) {
            $completed->rows()->create([
                'row_number' => $i + 1,
                'data' => $payload($student->first_name, $student->father_name, $student->gender->value),
                'status' => 'imported',
                'student_id' => $student->id,
            ]);
        }

        $completed->rows()->create([
            'row_number' => 4,
            'data' => $payload('Kaleb', 'Worku', 'male'),
            'status' => 'skipped',
            'duplicate_student_id' => $students->first()?->id,
            'resolution' => 'skip',
            'issues' => [[
                'field' => 'first_name', 'level' => 'warning', 'code' => 'duplicate_in_school',
                'message' => 'A student with the same identity already exists at this school.',
            ]],
        ]);
        $completed->rows()->create([
            'row_number' => 5,
            'data' => $payload('Saron', 'Demissie', 'female'),
            'status' => 'failed',
            'error' => 'This section is full.',
        ]);

        // 2. A draft mid-review — ready, duplicate and fix-me rows for the grid.
        $draft = StudentImport::create([
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'academic_year_id' => $year->id,
            'file_name' => 'new-intake-grade1.xlsx',
            'status' => 'draft',
            'total_rows' => 3,
            'created_by' => $creator->id,
        ]);

        $draft->rows()->create([
            'row_number' => 1,
            'data' => $payload('Lelisa', 'Gemeda', 'male'),
            'status' => 'ready',
        ]);
        $draft->rows()->create([
            'row_number' => 2,
            'data' => $payload($students->first()?->first_name ?? 'Abel', $students->first()?->father_name ?? 'Tesfaye', 'male'),
            'status' => 'duplicate',
            'duplicate_student_id' => $students->first()?->id,
            'resolution' => 'skip',
            'issues' => [[
                'field' => 'first_name', 'level' => 'warning', 'code' => 'duplicate_in_school',
                'message' => 'A student with the same identity already exists at this school.',
            ]],
        ]);
        $draft->rows()->create([
            'row_number' => 3,
            'data' => $payload('Bontu', '', 'female', ['father_name' => null]),
            'status' => 'error',
            'issues' => [[
                'field' => 'father_name', 'level' => 'error', 'code' => 'required',
                'message' => 'The father name field is required.',
            ]],
        ]);
    }

    // ───────────────────────── school + branches ─────────────────────────

    private function buildSchool(int $index, string $name): void
    {
        $school = School::create([
            'name' => $name,
            // School #1 demos the concession policy (sibling + employee-child
            // suggestions); #2 the HARD registration gate; #3 a stricter pass
            // mark; #4 an employee-child-only discount; #7 (Selam International)
            // runs on the GREGORIAN calendar + standard clock — the one
            // display-mode outlier (everyone else gets the Ethiopian defaults).
            // School #1 also showcases the full report-card policy (skill
            // checklist + per-subject ranks + criteria legend); #2 the
            // compact 2-per-page card.
            'settings' => match ($index) {
                0 => [
                    'sibling_discount_percent' => 10, 'sibling_min_children' => 2, 'staff_child_discount_percent' => 50,
                    'report_card_subject_ranks' => true,
                    'report_card_grading_criteria' => true,
                    'report_card_skills' => array_slice(ReportCardSettings::suggestedSkills(), 0, 8),
                ],
                1 => ['registration_gate' => 'hard', 'report_card_per_page' => 2],
                2 => ['promotion_threshold' => 60],
                3 => ['staff_child_discount_percent' => 25],
                6 => ['calendar_mode' => 'gregorian', 'clock_mode' => 'standard'],
                default => null,
            },
            'is_active' => true,
        ]);

        $this->buildBankAccounts($school, $index);

        SchoolDirectoryEntry::firstOrCreate(
            ['school_id' => $school->id],
            ['name' => $school->name, 'region' => 'Addis Ababa', 'city' => 'Addis Ababa', 'is_verified' => true],
        );

        $this->buildHolidays($school);
        $this->buildGradingPolicy($school, $index);

        // Who runs the school: a principal always, an IT admin for most.
        $this->schoolContact($school, Role::Principal, $index === 0 ? 'Principal (Unity Academy)' : null);

        if (mt_rand(1, 100) <= 70) {
            $this->schoolContact($school, Role::SchoolAdmin);
        }

        $branchCount = mt_rand(1, 6);
        $names = ['Main', ...collect(self::BRANCH_NAMES)->slice(1)->shuffle()->take($branchCount - 1)->all()];

        // 1–4 years of academic data; the showcase school gets the full run.
        $historyDepth = $index === 0 ? 4 : mt_rand(1, 4);

        foreach (array_slice($names, 0, $branchCount) as $branchIndex => $branchName) {
            $this->buildBranch($school, $branchName, $index === 0 && $branchIndex === 0, $historyDepth);
        }
    }

    private function schoolContact(School $school, Role $role, ?string $loginLabel = null): User
    {
        $user = $this->makeUser();

        Membership::create([
            'user_id' => $user->id,
            'school_id' => $school->id,
            'branch_id' => null,
            'role' => $role->value,
            'scope' => $role->scope()->value,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        if ($loginLabel !== null) {
            $this->sampleLogins[] = ['label' => $loginLabel, 'phone' => $user->phone];
        }

        return $user;
    }

    private function buildBranch(School $school, string $branchName, bool $showcase, int $historyDepth): void
    {
        $branch = $school->branches()->create([
            'name' => $branchName,
            'code' => sprintf('AA-%04d', $this->branchCodeCounter++),
            'country' => 'Ethiopia',
            'city' => self::CITIES[array_rand(self::CITIES)],
            'sub_city' => self::SUB_CITIES[array_rand(self::SUB_CITIES)],
            'woreda' => (string) mt_rand(1, 14),
            'is_active' => true,
        ]);

        // Grade span: lower primary, upper primary, or secondary.
        $span = match (mt_rand(1, 3)) {
            1 => ['G1', 'G2', 'G3', 'G4'],
            2 => ['G5', 'G6', 'G7', 'G8'],
            default => ['G9', 'G10', 'G11', 'G12'],
        };

        // Grade × program offering: Regular over the branch's span; the
        // showcase branch (and some others) also runs a second program on the
        // upper half of the span — the matrix + scoped filters are demoable.
        $spanIds = array_map(fn (string $code) => $this->grades[$code]->id, $span);
        $programs = [['type' => SchoolProgram::TYPE_REGULAR, 'grade_level_ids' => $spanIds]];
        if ($showcase || mt_rand(1, 100) <= 40) {
            $programs[] = [
                'type' => $showcase || mt_rand(1, 100) <= 70 ? 'night' : 'distance',
                'grade_level_ids' => array_slice($spanIds, 2),
            ];
        }
        GradeOffering::sync($branch, $programs);

        // Every school account collects at this branch (the alt account joins
        // only some branches — the per-branch switch is worth demoing).
        foreach ($this->schoolAccounts[$school->id] ?? [] as $i => $account) {
            $account->branches()->attach($branch->id, ['is_active' => $i < 2 || mt_rand(1, 100) <= 60]);
        }

        $teachers = $this->buildStaff($branch, $span, $showcase);
        [$activeYear, $closedTerm, $activeTerm, $pastYears] = $this->buildYears($branch, $historyDepth);
        $sections = $this->buildSections($branch, $span);
        $this->buildRooms($branch, $span);
        $fees = $this->buildFees($branch, $activeYear);

        $students = $this->buildStudents($branch, $activeYear, $sections, $fees['registration'], $showcase);
        $this->buildConcessions($branch, $activeYear, $teachers, $students, $showcase);
        $this->buildTuitionInvoices($branch, $closedTerm, $activeTerm, $fees['monthly'], $students);
        $this->buildHistory($branch, $span, $pastYears, $sections, $students);
        $assignments = $this->buildTeachingGrid($branch, $activeYear, [$closedTerm, $activeTerm], $sections, $teachers);
        $this->buildContinuousAssessment($closedTerm, $assignments, $students);
        $this->buildAssessmentPlans($branch, $activeTerm, $sections, $span);

        app(ComputeTermResultsAction::class)->execute($closedTerm);

        $this->buildAttendance($branch, $activeYear, $activeTerm, $students);
        $this->buildStaffRegister($branch);
        $this->buildHomerooms($branch, $activeYear, $sections, $teachers);

        if ($showcase) {
            $this->buildTimetable($branch, $activeTerm);
            $this->buildLms($branch, $assignments[$activeTerm->id] ?? [], $students);
            $this->buildChat($branch, $assignments[$activeTerm->id] ?? [], $students);
            $this->buildLessonPlans($branch, $assignments[$activeTerm->id] ?? [], $activeYear);
            $this->buildHrExtras($branch);
            $this->buildPayroll($branch);
            $this->buildFinanceOps($branch, $activeYear);
            $this->buildNfc($branch, $students);
            $this->buildMarklists($branch, $closedTerm);
            $this->buildEvaluations($branch, $closedTerm, $activeTerm);
            $this->buildPaymentVerifications($branch);
            $this->buildStudentHealth($students);
            $this->buildTeacherAvailabilities($teachers);
            $this->buildWithdrawal($branch, $students);
            $this->buildPromotionLedger($branch, $activeYear);
        }
    }

    // ───────────────────────── people ─────────────────────────

    private function makeUser(?string $fullName = null): User
    {
        [$first, $father] = $this->personName();

        // Mix Ethio Telecom (09) and Safaricom Ethiopia (07) numbers so both
        // operators — and the shared phone standard — are visible on staging.
        $prefix = $this->phoneCounter % 3 === 0 ? '07' : '09';

        return User::create([
            'name' => $fullName ?? "{$first} {$father}",
            'phone' => $prefix.$this->phoneCounter++,
            'email' => null,
            'password' => $this->passwordHash,
            'preferred_language' => ['en', 'am', 'am', 'om'][mt_rand(0, 3)],
            'status' => AccountStatus::Active,
        ]);
    }

    /** @return array{0: string, 1: string, 2: string} first, father, gender */
    private function personName(): array
    {
        $female = mt_rand(0, 1) === 1;
        $first = $female
            ? self::FEMALE_NAMES[array_rand(self::FEMALE_NAMES)]
            : self::MALE_NAMES[array_rand(self::MALE_NAMES)];

        return [$first, self::FATHER_NAMES[array_rand(self::FATHER_NAMES)], $female ? 'female' : 'male'];
    }

    /** Varied tenure: newcomers through 10-year veterans (Art. 77 ladder). */
    private function hireDate(): string
    {
        $dates = ['2015-09-01', '2018-09-01', '2020-09-01', '2022-09-01', '2023-09-01', '2025-11-01'];

        return $dates[mt_rand(0, count($dates) - 1)];
    }

    /**
     * Director + registrar + finance officer + 8–12 teachers, each with a
     * proper HR file, a position (which grants the branch role) and, for
     * teachers, teaching-capability rows the assignment autofill reads.
     *
     * @param  list<string>  $span
     * @return list<Employee> the teachers
     */
    private function buildStaff(Branch $branch, array $span, bool $showcase): array
    {
        $sync = app(SyncPositionMembershipsAction::class);

        $makeEmployee = function (string $jobTitle, int $salary) use ($branch, $sync): Employee {
            [$first, $father, $gender] = $this->personName();
            $user = $this->makeUser("{$first} {$father}");

            $employee = Employee::create([
                'user_id' => $user->id,
                'school_id' => $branch->school_id,
                'branch_id' => $branch->id,
                'first_name' => $first,
                'father_name' => $father,
                'gender' => $gender,
                'phone' => $user->phone,
                'nationality' => 'Ethiopian',
                'country' => 'Ethiopia',
                'city' => 'Addis Ababa',
                'is_active' => true,
            ]);

            $employee->positions()->create([
                'job_title' => $jobTitle,
                'employment_type' => 'full_time',
                'salary' => $salary,
                // Spread tenure so the Art. 77 leave ladder (16 + 1 day per
                // 2 extra service years) shows real variation on staging.
                'hired_on' => $this->hireDate(),
                'is_primary' => true,
            ]);

            $sync->execute($employee);

            return $employee;
        };

        $director = $makeEmployee('director', mt_rand(18, 28) * 1000);
        $makeEmployee('registrar', mt_rand(9, 14) * 1000);
        $finance = $makeEmployee('finance_officer', mt_rand(10, 16) * 1000);
        $this->financeUserId = $finance->user_id;

        // Support staff outside the account policy: an HR file, no login —
        // demos the settings-gated provisioning (user_id null is first-class).
        foreach ([['security_guard', 4], ['janitor', 3]] as [$jobTitle, $salaryK]) {
            [$first, $father, $gender] = $this->personName();
            $noLogin = Employee::create([
                'school_id' => $branch->school_id,
                'branch_id' => $branch->id,
                'first_name' => $first,
                'father_name' => $father,
                'gender' => $gender,
                'phone' => '09'.$this->phoneCounter++,
                'nationality' => 'Ethiopian',
                'country' => 'Ethiopia',
                'city' => 'Addis Ababa',
                'is_active' => true,
            ]);
            $noLogin->positions()->create([
                'job_title' => $jobTitle,
                'employment_type' => 'full_time',
                'salary' => $salaryK * 1000,
                'hired_on' => $this->hireDate(),
                'is_primary' => true,
            ]);
        }

        if ($showcase) {
            $this->sampleLogins[] = ['label' => 'Director (Unity Academy — Main)', 'phone' => $director->phone];
        }

        $sortOrders = array_map(fn (string $code) => $this->grades[$code]->sort_order, $span);
        $subjects = Subject::query()
            ->whereNull('school_id')
            ->forGradeSorts(range(min($sortOrders), max($sortOrders)))
            ->get();

        $teachers = [];
        $teacherCount = mt_rand(8, 12);

        for ($i = 0; $i < $teacherCount; $i++) {
            $teacher = $makeEmployee('teacher', mt_rand(7, 12) * 1000);

            // Each teacher can teach 1–2 subjects across the branch's grades.
            foreach ($subjects->shuffle()->take(mt_rand(1, 2)) as $subject) {
                foreach ($span as $code) {
                    $teacher->teacherSubjects()->firstOrCreate([
                        'subject_id' => $subject->id,
                        'grade_level_id' => $this->grades[$code]->id,
                    ]);
                }
            }

            if ($showcase && $i === 0) {
                $this->sampleLogins[] = ['label' => 'Teacher (Unity Academy — Main)', 'phone' => $teacher->phone];
            }

            $teachers[] = $teacher;
        }

        return $teachers;
    }

    // ───────────────────────── academic structure ─────────────────────────

    /** Ethiopian years behind the active 2018 E.C., newest first (offset ⇒ name + Gregorian dates). */
    private const PAST_YEARS = [
        1 => ['2017 E.C.', '2024-09-09', '2025-01-31', '2025-02-03', '2025-06-30'],
        2 => ['2016 E.C.', '2023-09-11', '2024-01-31', '2024-02-01', '2024-06-28'],
        3 => ['2015 E.C.', '2022-09-11', '2023-01-31', '2023-02-01', '2023-06-30'],
    ];

    /**
     * The active 2018 E.C. year (Semester 1 closed, Semester 2 running), a
     * planned 2019 E.C., and `depth − 1` completed years behind it — each
     * with two closed semesters.
     *
     * @return array{0: AcademicYear, 1: Term, 2: Term, 3: array<int, AcademicYear>}
     *                                                                               active year, closed sem 1, active sem 2, offset (years back) ⇒ completed year
     */
    private function buildYears(Branch $branch, int $depth): array
    {
        $save = app(SaveAcademicYearAction::class);

        $pastYears = [];

        for ($offset = min(3, $depth - 1); $offset >= 1; $offset--) {
            [$name, $startsOn, $semOneEnd, $semTwoStart, $endsOn] = self::PAST_YEARS[$offset];

            $year = $save->execute($branch, [
                'name' => $name, 'status' => 'completed',
                'starts_on' => $startsOn, 'ends_on' => $endsOn,
            ]);

            [$semesterOne, $semesterTwo] = $year->terms()->orderBy('sequence')->get();
            $semesterOne->update([
                'starts_on' => $startsOn, 'ends_on' => $semOneEnd,
                'status' => TermStatus::Closed, 'is_current' => false,
            ]);
            $semesterTwo->update([
                'starts_on' => $semTwoStart, 'ends_on' => $endsOn,
                'status' => TermStatus::Closed, 'is_current' => false,
            ]);

            $pastYears[$offset] = $year;
        }

        $active = $save->execute($branch, [
            'name' => '2018 E.C.', 'status' => 'active',
            'starts_on' => '2025-09-08', 'ends_on' => '2026-06-30',
        ]);

        $save->execute($branch, [
            'name' => '2019 E.C.', 'status' => 'planned',
            'starts_on' => '2026-09-08', 'ends_on' => '2027-06-30',
        ]);

        [$semesterOne, $semesterTwo] = $active->terms()->orderBy('sequence')->get();

        $semesterOne->update([
            'starts_on' => '2025-09-08', 'ends_on' => '2026-01-30',
            'status' => TermStatus::Closed, 'is_current' => false,
        ]);
        $semesterTwo->update([
            'starts_on' => '2026-02-02', 'ends_on' => '2026-06-30',
            'status' => TermStatus::Active, 'is_current' => true,
        ]);

        return [$active, $semesterOne, $semesterTwo, $pastYears];
    }

    /**
     * @param  list<string>  $span
     * @return array<string, list<Section>> grade code → sections
     */
    private function buildSections(Branch $branch, array $span): array
    {
        $sections = [];

        foreach ($span as $code) {
            $count = mt_rand(1, 2);

            foreach (array_slice(['A', 'B'], 0, $count) as $letter) {
                $sections[$code][] = $branch->sections()->create([
                    'school_id' => $branch->school_id,
                    'grade_level_id' => $this->grades[$code]->id,
                    'name' => $letter,
                    'capacity' => 40,
                    'room_number' => 'R'.mt_rand(101, 320),
                ]);
            }
        }

        return $sections;
    }

    /** @param  list<string>  $span */
    private function buildRooms(Branch $branch, array $span): void
    {
        Room::create([
            'school_id' => $branch->school_id, 'branch_id' => $branch->id,
            'name' => 'Sports field', 'type' => 'gym',
        ]);

        // Labs + ICT rooms where the curriculum needs them (Grade 7 and up).
        if ($this->grades[end($span)]->sort_order >= 10) {
            Room::create([
                'school_id' => $branch->school_id, 'branch_id' => $branch->id,
                'name' => 'Science lab', 'type' => 'lab', 'capacity' => 44,
            ]);
            Room::create([
                'school_id' => $branch->school_id, 'branch_id' => $branch->id,
                'name' => 'ICT room', 'type' => 'ict', 'capacity' => 40,
            ]);
        }
    }

    /** @return array{registration: FeeStructure, monthly: FeeStructure} */
    private function buildFees(Branch $branch, AcademicYear $year): array
    {
        $registration = FeeStructure::create([
            'school_id' => $branch->school_id, 'branch_id' => $branch->id,
            'academic_year_id' => $year->id,
            'name' => 'Registration fee '.$year->name,
            'type' => FeeType::Registration,
            'amount' => mt_rand(5, 15) * 100,
        ]);

        $monthly = FeeStructure::create([
            'school_id' => $branch->school_id, 'branch_id' => $branch->id,
            'academic_year_id' => $year->id,
            'name' => 'Monthly tuition',
            'type' => FeeType::Monthly,
            'amount' => mt_rand(8, 25) * 100,
            'starts_on' => '2025-09-08',
            'due_on' => '2025-10-10',
            'notify_parents' => true,
            'penalty_type' => 'incremental',
            'penalty_amount' => 50,
            'penalty_increment_days' => 7,
        ]);

        // Collection accounts: registration takes the bank + wallet; tuition
        // takes everything the school has (multi-account fees demo the
        // payment-sheet account picker).
        $accounts = collect($this->schoolAccounts[$branch->school_id] ?? []);
        $registration->bankAccounts()->sync($accounts->take(2)->pluck('id'));
        $monthly->bankAccounts()->sync($accounts->pluck('id'));

        return ['registration' => $registration, 'monthly' => $monthly];
    }

    /**
     * The school's collection accounts: a CBE account, a Telebirr wallet and —
     * for most schools — one more commercial bank. Feeds the payment-accounts
     * page, the record-payment account picker and per-account reports.
     */
    private function buildBankAccounts(School $school, int $index): void
    {
        $catalog = Bank::query()->pluck('id', 'code');
        $accounts = [];

        $rows = [
            ['cbe', $school->name.' — Main', sprintf('1000%06d', 100000 + $index * 7)],
            ['telebirr', $school->name.' — Telebirr', '09'.(30000000 + $index * 11)],
        ];

        if ($index % 2 === 0) {
            $alt = ['awash', 'boa', 'dashen', 'coop', 'zemen'][$index % 5];
            $rows[] = [$alt, $school->name.' — Ops', sprintf('01%08d', 5200000 + $index * 13)];
        }

        foreach ($rows as [$code, $accountName, $number]) {
            if (! isset($catalog[$code])) {
                continue;
            }

            $accounts[] = BankAccount::create([
                'school_id' => $school->id,
                'bank_id' => $catalog[$code],
                'account_name' => $accountName,
                'account_number' => $number,
                'is_active' => true,
            ])->load('bank');
        }

        $this->schoolAccounts[$school->id] = $accounts;
    }

    /**
     * The account a payment of this method landed in — wallets go to the
     * wallet, transfers to a bank account, cash nowhere.
     */
    private function accountFor(int $schoolId, string $method): ?BankAccount
    {
        $accounts = collect($this->schoolAccounts[$schoolId] ?? []);

        return match ($method) {
            'wallet' => $accounts->first(fn (BankAccount $a) => $a->bank?->type === 'wallet'
                || str_contains($a->account_name, 'Telebirr')),
            'bank_transfer' => $accounts->first(fn (BankAccount $a) => ! str_contains($a->account_name, 'Telebirr')),
            default => null,
        };
    }

    // ───────────────────────── students + families ─────────────────────────

    /**
     * @param  array<string, list<Section>>  $sections
     * @return list<array{student: Student, enrollment: StudentEnrollment, ability: int}>
     */
    private function buildStudents(
        Branch $branch,
        AcademicYear $year,
        array $sections,
        FeeStructure $registrationFee,
        bool $showcase,
    ): array {
        $all = [];
        $previousParent = null;
        $parentLoginTaken = false;

        foreach ($sections as $code => $gradeSections) {
            foreach ($gradeSections as $section) {
                $count = mt_rand(10, 16);

                for ($i = 0; $i < $count; $i++) {
                    [$first, $father, $gender] = $this->personName();

                    $student = Student::create([
                        'school_id' => $branch->school_id,
                        'branch_id' => $branch->id,
                        'first_name' => $first,
                        'father_name' => $father,
                        'grandfather_name' => self::FATHER_NAMES[array_rand(self::FATHER_NAMES)],
                        'gender' => $gender,
                        'date_of_birth' => sprintf('%d-%02d-%02d', 2019 - $this->grades[$code]->sort_order, mt_rand(1, 12), mt_rand(1, 28)),
                        'languages' => mt_rand(1, 100) <= 20 ? ['am', 'om'] : ['am'],
                        'is_active' => true,
                    ]);

                    // 12% of families have not settled the registration fee yet.
                    $pending = mt_rand(1, 100) <= 12;

                    $enrollment = StudentEnrollment::create([
                        'student_id' => $student->id,
                        'school_id' => $branch->school_id,
                        'branch_id' => $branch->id,
                        'academic_year_id' => $year->id,
                        'school_program_id' => $year->terms()->value('school_program_id'),
                        'section_id' => $section->id,
                        'grade_level_id' => $section->grade_level_id,
                        'status' => $pending ? EnrollmentStatus::Pending : EnrollmentStatus::Active,
                        'enrolled_on' => '2025-09-0'.mt_rand(1, 8),
                    ]);

                    $this->issueRegistrationInvoice($registrationFee, $student, ! $pending);

                    // Guardians: ~12% share the previous family's parent (sibling).
                    if ($previousParent !== null && mt_rand(1, 100) <= 12) {
                        $parent = $previousParent;
                    } else {
                        $parent = $this->makeParent($student, $showcase && ! $parentLoginTaken);
                        $parentLoginTaken = $parentLoginTaken || $showcase;
                        $previousParent = $parent;
                    }

                    StudentGuardian::create([
                        'student_id' => $student->id,
                        'parent_id' => $parent->id,
                        'relationship' => $parent->gender === 'female' ? 'mother' : 'father',
                        'can_view_grades' => true,
                        'can_view_attendance' => true,
                        'can_pay_fees' => true,
                        'can_receive_sms' => true,
                        'is_primary' => true,
                        'priority_order' => 1,
                        'is_active' => true,
                    ]);

                    // Ability drives semester marks: most pass, some struggle.
                    $ability = mt_rand(1, 100) <= 15 ? mt_rand(30, 48) : mt_rand(52, 96);

                    $all[] = ['student' => $student, 'enrollment' => $enrollment, 'ability' => $ability];
                }
            }
        }

        return $all;
    }

    private function makeParent(Student $student, bool $sampleLogin): ParentProfile
    {
        [$first, $father, $gender] = $this->personName();

        $user = $this->makeUser("{$first} {$father}");

        $parent = ParentProfile::create([
            'user_id' => $user->id,
            'first_name' => $first,
            'father_name' => $father,
            'gender' => $gender,
            'nationality' => 'Ethiopian',
            'occupation' => ['Merchant', 'Civil servant', 'Driver', 'Nurse', 'Farmer', 'Engineer', 'Accountant'][mt_rand(0, 6)],
            'country' => 'Ethiopia',
            'city' => 'Addis Ababa',
        ]);

        // NO membership row: the parent hat is relationship-derived (ADR-012 —
        // parents_profiles + student_guardians), never membership-backed.

        if ($sampleLogin) {
            $this->sampleLogins[] = ['label' => 'Parent (child at Unity Academy)', 'phone' => $user->phone];
        }

        return $parent;
    }

    private function issueRegistrationInvoice(FeeStructure $fee, Student $student, bool $paid): void
    {
        $invoice = $fee->invoices()->create([
            'school_id' => $fee->school_id,
            'branch_id' => $fee->branch_id,
            'student_id' => $student->id,
            'academic_year_id' => $fee->academic_year_id,
            'title' => $fee->name,
            'amount' => $fee->amount,
            'amount_paid' => $paid ? $fee->amount : 0,
            'status' => $paid ? InvoiceStatus::Paid : InvoiceStatus::Unpaid,
        ]);

        if ($paid) {
            $this->recordSeedPayment(
                $invoice,
                (float) $fee->amount,
                '2025-09-'.str_pad((string) mt_rand(1, 20), 2, '0', STR_PAD_LEFT),
            );
        }
    }

    /**
     * A payment with the full real-world shape: Ethiopian method mix, the
     * collection-account snapshot for bank/wallet channels (never for cash),
     * a reference and the finance officer as recorder.
     */
    private function recordSeedPayment(Invoice $invoice, float $amount, string $paidAt): void
    {
        $method = ['wallet', 'wallet', 'wallet', 'wallet', 'bank_transfer', 'bank_transfer', 'cash', 'cash'][mt_rand(0, 7)];
        $account = $this->accountFor($invoice->school_id, $method);

        $invoice->payments()->create([
            'school_id' => $invoice->school_id,
            'branch_id' => $invoice->branch_id,
            'student_id' => $invoice->student_id,
            'amount' => $amount,
            'method' => $method,
            'bank_account_id' => $account?->id,
            'reference' => $method === 'cash' ? null : strtoupper(substr($method, 0, 2)).mt_rand(10000000, 99999999),
            'receipt_number' => $this->nextReceiptNumber($invoice->branch_id),
            'receipt_token' => Str::random(40),
            'paid_at' => $paidAt,
            'recorded_by' => $this->financeUserId,
        ]);
    }

    /** @var array<int, Branch> */
    private array $receiptBranches = [];

    /** Same shape RecordPaymentAction allocates: RCT-{code}-{seq}. */
    private function nextReceiptNumber(int $branchId): string
    {
        $branch = $this->receiptBranches[$branchId] ??= Branch::findOrFail($branchId);
        $branch->increment('receipt_counter');

        return sprintf('RCT-%s-%06d', $branch->code, $branch->receipt_counter);
    }

    // ───────────────────────── concessions ─────────────────────────

    /**
     * Standing discounts in every state the review queue knows: policy
     * suggestions (sibling / employee-child, pending + one approved), manual
     * grants (merit / hardship / full scholarship), a guardian-level lifetime
     * discount and — on the showcase branch — revoked/rejected rows. Created
     * BEFORE tuition invoices so the resolver stamps them naturally.
     *
     * @param  list<Employee>  $teachers
     * @param  list<array{student: Student, enrollment: StudentEnrollment, ability: int}>  $students
     */
    private function buildConcessions(
        Branch $branch,
        AcademicYear $year,
        array $teachers,
        array $students,
        bool $showcase,
    ): void {
        if ($students === []) {
            return;
        }

        $school = School::find($branch->school_id);
        $studentIds = array_map(fn (array $row) => $row['student']->id, $students);

        $base = [
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'academic_year_id' => $year->id,
        ];

        // Sibling policy: families sharing a guardian, one suggestion each —
        // the first already approved so tuition bills show the discount.
        if ($school->siblingDiscountPercent() > 0) {
            $siblingParents = StudentGuardian::query()
                ->whereIn('student_id', $studentIds)
                ->where('is_active', true)
                ->select('parent_id')
                ->groupBy('parent_id')
                ->havingRaw('COUNT(*) >= ?', [$school->siblingMinChildren()])
                ->limit(3)
                ->pluck('parent_id');

            foreach ($siblingParents as $i => $parentId) {
                $childId = StudentGuardian::where('parent_id', $parentId)->value('student_id');

                FeeConcession::create([
                    ...$base,
                    'student_id' => $childId,
                    'category' => ConcessionCategory::Sibling,
                    'discount_type' => DiscountType::Percentage,
                    'discount_value' => $school->siblingDiscountPercent(),
                    'status' => $i === 0 ? ConcessionStatus::Active : ConcessionStatus::Pending,
                    'source' => 'auto_sibling',
                    'reason' => sprintf('School policy: %d+ enrolled children', $school->siblingMinChildren()),
                    'approved_by' => $i === 0 ? $this->financeUserId : null,
                    'approved_at' => $i === 0 ? now() : null,
                ]);
            }
        }

        // Employee-child policy: a teacher becomes guardian of one student —
        // the suggestion waits in the review queue.
        if ($school->staffChildDiscountPercent() > 0 && $teachers !== []) {
            $teacher = $teachers[0];

            $staffParent = ParentProfile::create([
                'user_id' => $teacher->user_id,
                'first_name' => $teacher->first_name,
                'father_name' => $teacher->father_name,
                'gender' => $teacher->gender,
                'nationality' => 'Ethiopian',
                'occupation' => 'Teacher',
                'country' => 'Ethiopia',
                'city' => 'Addis Ababa',
            ]);

            $child = $students[array_rand($students)]['student'];

            StudentGuardian::create([
                'student_id' => $child->id,
                'parent_id' => $staffParent->id,
                'relationship' => $staffParent->gender === 'female' ? 'mother' : 'father',
                'is_primary' => false,
                'priority_order' => 2,
                'is_active' => true,
            ]);

            FeeConcession::create([
                ...$base,
                'student_id' => $child->id,
                'category' => ConcessionCategory::StaffChild,
                'discount_type' => DiscountType::Percentage,
                'discount_value' => $school->staffChildDiscountPercent(),
                'status' => ConcessionStatus::Pending,
                'source' => 'auto_staff',
                'reason' => 'School policy: guardian is an employee',
            ]);
        }

        // Manual grants (finance's own decisions, active immediately).
        $manual = fn (array $attributes) => FeeConcession::create([
            ...$base,
            'status' => ConcessionStatus::Active,
            'source' => 'manual',
            'requested_by' => $this->financeUserId,
            'approved_by' => $this->financeUserId,
            'approved_at' => now(),
            ...$attributes,
        ]);

        $pick = fn () => $students[array_rand($students)]['student']->id;

        if ($showcase || mt_rand(1, 100) <= 60) {
            $manual([
                'student_id' => $pick(),
                'category' => ConcessionCategory::Merit,
                'discount_type' => DiscountType::Percentage,
                'discount_value' => 25,
                'reason' => 'Top of class '.$year->name,
            ]);
        }

        if ($showcase || mt_rand(1, 100) <= 50) {
            $manual([
                'student_id' => $pick(),
                'category' => ConcessionCategory::Hardship,
                'discount_type' => DiscountType::Fixed,
                'discount_value' => 500,
                'fee_types' => ['monthly'],
                'reason' => 'Family hardship — tuition support',
            ]);
        }

        if ($showcase || mt_rand(1, 100) <= 30) {
            $manual([
                'student_id' => $pick(),
                'category' => ConcessionCategory::Scholarship,
                'discount_type' => DiscountType::FullScholarship,
                'discount_value' => 0,
                'reason' => 'Community scholarship fund',
            ]);
        }

        if ($showcase) {
            // Guardian-level LIFETIME discount — covers all the family's
            // children, every year, until revoked.
            $lifetimeParent = StudentGuardian::query()
                ->whereIn('student_id', $studentIds)
                ->where('is_active', true)
                ->value('parent_id');

            if ($lifetimeParent !== null) {
                FeeConcession::create([
                    'school_id' => $branch->school_id,
                    'branch_id' => null,
                    'parent_id' => $lifetimeParent,
                    'category' => ConcessionCategory::Other,
                    'discount_type' => DiscountType::Percentage,
                    'discount_value' => 10,
                    'status' => ConcessionStatus::Active,
                    'source' => 'manual',
                    'reason' => 'Founding family — lifetime discount',
                    'requested_by' => $this->financeUserId,
                    'approved_by' => $this->financeUserId,
                    'approved_at' => now(),
                ]);
            }

            // Status variety for the review queue.
            $manual([
                'student_id' => $pick(),
                'category' => ConcessionCategory::Merit,
                'discount_type' => DiscountType::Percentage,
                'discount_value' => 15,
                'status' => ConcessionStatus::Revoked,
                'revoked_at' => now()->subDays(20),
                'reason' => 'Award ended',
            ]);
            $manual([
                'student_id' => $pick(),
                'category' => ConcessionCategory::Hardship,
                'discount_type' => DiscountType::Percentage,
                'discount_value' => 30,
                'status' => ConcessionStatus::Rejected,
                'reason' => 'Insufficient documentation',
            ]);
        }
    }

    /**
     * Semester tuition bills with the full status spread the invoices page
     * filters on: paid (payments spread over months, account snapshots),
     * partial, unpaid and OVERDUE — plus concession-stamped nets, since the
     * resolver runs exactly as it does in production.
     *
     * @param  list<array{student: Student, enrollment: StudentEnrollment, ability: int}>  $students
     */
    private function buildTuitionInvoices(
        Branch $branch,
        Term $closedTerm,
        Term $activeTerm,
        FeeStructure $monthly,
        array $students,
    ): void {
        $resolver = app(FeeConcessionResolver::class);

        foreach ($students as $row) {
            if ($row['enrollment']->status !== EnrollmentStatus::Active) {
                continue; // tuition is only billed to active enrollments
            }

            // Semester 1 (closed, due date long past): most families settled,
            // some paid part, the rest are overdue.
            $this->issueTuition($resolver, $monthly, $row['student'], $closedTerm, '2025-10-10', match (true) {
                mt_rand(1, 100) <= 55 => 'paid',
                mt_rand(1, 100) <= 35 => 'partial',
                default => 'unpaid',
            }, ['2025-09-15', '2025-12-30']);

            // Semester 2 (running, due at month end): fewer settled yet.
            if (mt_rand(1, 100) <= 75) {
                $this->issueTuition($resolver, $monthly, $row['student'], $activeTerm, '2026-07-30', match (true) {
                    mt_rand(1, 100) <= 40 => 'paid',
                    mt_rand(1, 100) <= 20 => 'partial',
                    default => 'unpaid',
                }, ['2026-02-10', '2026-07-05']);
            }
        }
    }

    /**
     * @param  array{0: string, 1: string}  $paymentWindow  inclusive Y-m-d range payments land in
     */
    private function issueTuition(
        FeeConcessionResolver $resolver,
        FeeStructure $fee,
        Student $student,
        Term $term,
        string $dueDate,
        string $outcome,
        array $paymentWindow,
    ): void {
        $invoice = $fee->invoices()->create([
            'school_id' => $fee->school_id,
            'branch_id' => $fee->branch_id,
            'student_id' => $student->id,
            'academic_year_id' => $fee->academic_year_id,
            'term_id' => $term->id,
            'title' => $fee->name.' — '.$term->name,
            'amount' => $fee->amount,
            'amount_paid' => 0,
            'status' => InvoiceStatus::Unpaid,
            'due_date' => $dueDate,
        ]);

        // Same code path as production billing — concessions stamp the net.
        $invoice = $resolver->apply($invoice, $fee->type->value);

        if ($invoice->status === InvoiceStatus::Scholarship || $outcome === 'unpaid') {
            return;
        }

        $net = $invoice->netAmount();
        $amount = $outcome === 'paid' ? $net : round($net * mt_rand(30, 70) / 100, 2);

        if ($amount <= 0) {
            return;
        }

        $paidAt = date('Y-m-d', mt_rand(strtotime($paymentWindow[0]), strtotime($paymentWindow[1])));
        $this->recordSeedPayment($invoice, $amount, $paidAt);

        $invoice->update([
            'amount_paid' => $amount,
            'status' => $amount >= $net ? InvoiceStatus::Paid : InvoiceStatus::Partial,
        ]);
    }

    // ───────────────────────── history ─────────────────────────

    /**
     * Completed-year records: every student who was already at the branch back
     * then gets a PROMOTED enrollment one grade lower per year back plus frozen
     * report cards (student_term_results) for both semesters — exactly what the
     * promotion board and annual-average views read. Students whose back-grade
     * falls below the branch's span simply joined later (no rows).
     *
     * @param  list<string>  $span
     * @param  array<int, AcademicYear>  $pastYears  offset (years back) ⇒ year
     * @param  array<string, list<Section>>  $sections
     * @param  list<array{student: Student, enrollment: StudentEnrollment, ability: int}>  $students
     */
    private function buildHistory(Branch $branch, array $span, array $pastYears, array $sections, array $students): void
    {
        if ($pastYears === []) {
            return;
        }

        $codeBySort = [];
        foreach ($span as $code) {
            $codeBySort[$this->grades[$code]->sort_order] = $code;
        }

        $sortByGradeId = [];
        foreach ($this->grades as $grade) {
            $sortByGradeId[$grade->id] = $grade->sort_order;
        }

        $now = now();
        $grouped = []; // term_id → section_id → result rows, ranked below

        // Behavioral skill ratings where the school configured a checklist —
        // most students rated E/VG/S, the odd NI, so the yearly card's panel
        // has something real to print.
        $skillKeys = array_column($branch->school?->reportCardSkills() ?? [], 'key');

        foreach ($pastYears as $offset => $year) {
            $terms = $year->terms()->orderBy('sequence')->get();
            $programId = $terms->first()?->school_program_id;

            foreach ($students as $row) {
                $code = $codeBySort[$sortByGradeId[$row['enrollment']->grade_level_id] - $offset] ?? null;

                if ($code === null) {
                    continue;
                }

                $gradeSections = $sections[$code];
                $section = $gradeSections[array_rand($gradeSections)];

                $enrollment = StudentEnrollment::create([
                    'student_id' => $row['student']->id,
                    'school_id' => $branch->school_id,
                    'branch_id' => $branch->id,
                    'academic_year_id' => $year->id,
                    'school_program_id' => $programId,
                    'section_id' => $section->id,
                    'grade_level_id' => $this->grades[$code]->id,
                    'status' => EnrollmentStatus::Promoted,
                    'enrolled_on' => $year->starts_on,
                ]);

                foreach ($terms as $term) {
                    $breakdown = [];
                    $total = 0.0;

                    foreach ($this->curriculumFor($code) as $subject) {
                        $score = (float) max(25, min(99, $row['ability'] + mt_rand(-8, 8)));
                        $breakdown[] = [
                            'subject_id' => $subject->id,
                            'code' => $subject->code,
                            'name' => $subject->name,
                            'total' => $score,
                            ...$this->demoBand($score),
                        ];
                        $total += $score;
                    }

                    $average = round($total / count($breakdown), 2);

                    $grouped[$term->id][$section->id][] = [
                        'student_id' => $row['student']->id,
                        'student_enrollment_id' => $enrollment->id,
                        'term_id' => $term->id,
                        'school_id' => $branch->school_id,
                        'branch_id' => $branch->id,
                        'academic_year_id' => $year->id,
                        'section_id' => $section->id,
                        'grade_level_id' => $this->grades[$code]->id,
                        'total' => round($total, 2),
                        'average' => $average,
                        'rank' => 0,
                        'rank_of' => 0,
                        'subject_count' => count($breakdown),
                        'breakdown' => json_encode($breakdown),
                        'grading' => json_encode([
                            'scale' => ['id' => null, 'code' => 'et-percentage', 'name' => 'Percentage (0–100)'],
                            'display' => 'numeric',
                            'overall' => [
                                'letter' => null,
                                'label' => $this->demoBand($average)['band_label'],
                                'grade_points' => null,
                                'is_passing' => $average >= 50,
                            ],
                        ]),
                        'conduct' => ['A', 'A', 'B', 'B', 'C'][mt_rand(0, 4)],
                        'skills' => $skillKeys === [] ? null : json_encode(
                            collect($skillKeys)->mapWithKeys(fn (string $key) => [
                                $key => ['E', 'E', 'VG', 'VG', 'S', 'NI'][mt_rand(0, 5)],
                            ])->all(),
                        ),
                        'absence_days' => mt_rand(0, 6),
                        'computed_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        $insert = [];

        foreach ($grouped as $bySection) {
            foreach ($bySection as $rows) {
                usort($rows, fn (array $a, array $b) => $b['average'] <=> $a['average']);

                foreach ($rows as $index => $resultRow) {
                    $resultRow['rank'] = $index + 1;
                    $resultRow['rank_of'] = count($rows);
                    $insert[] = $resultRow;
                }
            }
        }

        foreach (array_chunk($insert, 1000) as $chunk) {
            StudentTermResult::insert($chunk);
        }
    }

    /**
     * The Ethiopian percentage-scale band for a demo score — mirrors the
     * grading snapshot ComputeTermResultsAction would have frozen.
     *
     * @return array{letter: null, band_label: string, is_passing: bool}
     */
    private function demoBand(float $score): array
    {
        $label = match (true) {
            $score >= 90 => 'Excellent',
            $score >= 80 => 'Very Good',
            $score >= 60 => 'Good',
            $score >= 50 => 'Satisfactory',
            default => 'Needs Improvement',
        };

        return ['letter' => null, 'band_label' => $label, 'is_passing' => $score >= 50];
    }

    // ───────────────────────── teaching + marks ─────────────────────────

    /**
     * The grade's 7 heaviest platform subjects — the set a real semester grid
     * carries. Cached per grade; shared by the teaching grid and the history.
     *
     * @return Collection<int, Subject>
     */
    private function curriculumFor(string $code): Collection
    {
        $sort = $this->grades[$code]->sort_order;

        return $this->curriculum[$code] ??= Subject::query()
            ->whereNull('school_id')
            ->forGradeSorts([$sort])
            ->orderByDesc('weight')
            ->limit(7)
            ->get();
    }

    /**
     * The semester grid for BOTH semesters: curriculum subjects per grade,
     * teachers matched from their capability rows.
     *
     * @param  list<Term>  $terms
     * @param  array<string, list<Section>>  $sections
     * @param  list<Employee>  $teachers
     * @return array<int, list<SubjectAssignment>> term_id → assignments
     */
    private function buildTeachingGrid(Branch $branch, AcademicYear $year, array $terms, array $sections, array $teachers): array
    {
        $byTerm = [];
        // Weekly load per teacher (per term) — real schools spread the grid so
        // nobody exceeds ~25 periods; without this the solver rightly gives up.
        $load = [];

        foreach ($sections as $code => $gradeSections) {
            $subjects = $this->curriculumFor($code);

            foreach ($gradeSections as $section) {
                foreach ($subjects as $subject) {
                    $periods = $subject->weight >= 4 ? mt_rand(4, 5) : mt_rand(2, 3);

                    // Least-loaded teacher capable of this subject+grade; when
                    // none has room, the least-loaded teacher overall.
                    $capable = collect($teachers)->filter(
                        fn (Employee $t) => $t->teacherSubjects
                            ->contains(fn ($ts) => $ts->subject_id === $subject->id && $ts->grade_level_id === $section->grade_level_id),
                    );

                    $pick = fn ($pool) => $pool
                        ->sortBy(fn (Employee $t) => $load[$t->id] ?? 0)
                        ->first();

                    $teacher = $pick($capable->filter(fn (Employee $t) => ($load[$t->id] ?? 0) + $periods <= 25))
                        ?? $pick(collect($teachers));

                    $load[$teacher->id] = ($load[$teacher->id] ?? 0) + $periods;

                    foreach ($terms as $term) {
                        $byTerm[$term->id][] = SubjectAssignment::create([
                            'school_id' => $branch->school_id,
                            'branch_id' => $branch->id,
                            'academic_year_id' => $year->id,
                            'section_id' => $section->id,
                            'subject_id' => $subject->id,
                            'term_id' => $term->id,
                            'employee_id' => $teacher->id,
                            'periods_per_week' => $periods,
                        ]);
                    }
                }
            }
        }

        return $byTerm;
    }

    /**
     * Semester 1 continuous assessment: a mid (40%) + final (60%) per subject with marks
     * around each student's ability — realistic pass/fail spread for the
     * report cards and the promotion board.
     *
     * @param  array<int, list<SubjectAssignment>>  $assignmentsByTerm
     * @param  list<array{student: Student, enrollment: StudentEnrollment, ability: int}>  $students
     */
    private function buildContinuousAssessment(Term $closedTerm, array $assignmentsByTerm, array $students): void
    {
        $bySection = [];

        foreach ($students as $row) {
            if ($row['enrollment']->status === EnrollmentStatus::Active) {
                $bySection[$row['enrollment']->section_id][] = $row;
            }
        }

        $resultRows = [];
        $now = now();

        foreach ($assignmentsByTerm[$closedTerm->id] ?? [] as $assignment) {
            $mid = $assignment->assessments()->create([
                'type' => 'midterm', 'name' => 'Mid-semester exam',
                'max_score' => 40, 'weight' => 40, 'conducted_on' => '2025-11-20',
            ]);
            $final = $assignment->assessments()->create([
                'type' => 'final', 'name' => 'Final exam',
                'max_score' => 60, 'weight' => 60, 'conducted_on' => '2026-01-20',
            ]);

            foreach ($bySection[$assignment->section_id] ?? [] as $row) {
                foreach ([[$mid, 40], [$final, 60]] as [$assessment, $max]) {
                    $score = max(5, min($max, round($row['ability'] / 100 * $max + mt_rand(-6, 6))));
                    $resultRows[] = [
                        'assessment_id' => $assessment->id,
                        'student_id' => $row['student']->id,
                        'score' => $score,
                        'is_absent' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($resultRows, 1000) as $chunk) {
            AssessmentResult::insert($chunk);
        }
    }

    /**
     * Two live continuous-assessment plans on the active semester so the
     * targeting UI has something real to show: a branch-wide default (all
     * grades / all subjects) and a per-grade override (the top grade's
     * Mathematics, its own sections, exam-heavy weights).
     *
     * @param  array<string, list<Section>>  $sections
     * @param  list<string>  $span
     */
    private function buildAssessmentPlans(Branch $branch, Term $activeTerm, array $sections, array $span): void
    {
        $standard = ContinuousAssessment::create([
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'term_id' => $activeTerm->id,
            'name' => 'Standard continuous assessment',
            'is_active' => true,
        ]);
        $standard->targets()->create(['grade_level_id' => null, 'section_ids' => null, 'subject_ids' => null]);
        foreach ([['quiz', 'Quiz', 10], ['assignment', 'Assignment', 15], ['mid_exam', 'Mid exam', 25], ['final_exam', 'Final exam', 50]] as $i => [$type, $name, $weight]) {
            $standard->items()->create(['type' => $type, 'name' => $name, 'weight' => $weight, 'max_score' => $weight, 'sort_order' => $i + 1]);
        }

        $topCode = end($span);
        $grade = $this->grades[$topCode] ?? null;
        $math = Subject::query()->whereNull('school_id')->where('code', 'MATH')->first();

        if ($grade === null || $math === null) {
            return;
        }

        $override = ContinuousAssessment::create([
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'term_id' => $activeTerm->id,
            'name' => "{$grade->name} Mathematics plan",
            'is_active' => true,
        ]);
        $override->targets()->create([
            'grade_level_id' => $grade->id,
            'section_ids' => collect($sections[$topCode] ?? [])->pluck('id')->all() ?: null,
            'subject_ids' => [$math->id],
        ]);
        foreach ([['quiz', 'Quiz', 20], ['mid_exam', 'Mid exam', 30], ['final_exam', 'Final exam', 50]] as $i => [$type, $name, $weight]) {
            $override->items()->create(['type' => $type, 'name' => $name, 'weight' => $weight, 'max_score' => $weight, 'sort_order' => $i + 1]);
        }
    }

    /**
     * Two weeks of daily attendance in the active semester (~90% present),
     * roughly 60% captured by an RFID gate terminal (with check-in times) and
     * the rest by the manual register — so the attendance reports' source /
     * per-terminal / arrival-time views have something real to show.
     *
     * @param  list<array{student: Student, enrollment: StudentEnrollment, ability: int}>  $students
     */
    private function buildAttendance(Branch $branch, AcademicYear $year, Term $term, array $students): void
    {
        $gate = Device::create([
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'name' => 'Main gate',
            'location' => 'Front entrance',
            'audience' => 'students',
            'token_hash' => Device::hashToken(Device::mintToken()),
            'last_seen_at' => now(),
            'last_event_at' => now(),
        ]);

        // The last 10 school days ENDING TODAY, so "attendance today" tiles
        // and the dashboard's week chart land populated on staging.
        $days = [];
        $cursor = now();

        while (count($days) < 10) {
            if ($cursor->isWeekday()) {
                $days[] = $cursor->toDateString();
            }
            $cursor = $cursor->subDay();
        }

        $days = array_reverse($days);

        $rows = [];
        $now = now();

        foreach ($students as $row) {
            if ($row['enrollment']->status !== EnrollmentStatus::Active) {
                continue;
            }

            foreach ($days as $date) {
                $roll = mt_rand(1, 100);
                $status = match (true) {
                    $roll <= 90 => 'present',
                    $roll <= 94 => 'late',
                    $roll <= 98 => 'absent',
                    default => 'excused',
                };
                // Absences carry no scan; ~60% of the rest came through the gate.
                $device = in_array($status, ['present', 'late'], true) && mt_rand(1, 100) <= 60;

                $rows[] = [
                    'school_id' => $branch->school_id,
                    'branch_id' => $branch->id,
                    'section_id' => $row['enrollment']->section_id,
                    'student_id' => $row['student']->id,
                    'academic_year_id' => $year->id,
                    'term_id' => $term->id,
                    'date' => $date,
                    'status' => $status,
                    'source' => $device ? 'device' : 'manual',
                    'device_id' => $device ? $gate->id : null,
                    'check_in' => match (true) {
                        $status === 'present' && $device => sprintf('07:%02d', mt_rand(20, 59)),
                        $status === 'late' && $device => sprintf('08:%02d', mt_rand(20, 55)),
                        default => null,
                    },
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            AttendanceRecord::insert($chunk);
        }

        $this->buildAbsenceExcuses($branch);
    }

    /**
     * Parent-filed absence excuses in both demo states: one PENDING for the
     * branch to decide and one APPROVED whose absence already reads excused —
     * so the review queue and the family timeline both land populated.
     */
    private function buildAbsenceExcuses(Branch $branch): void
    {
        $absences = AttendanceRecord::query()
            ->where('branch_id', $branch->id)
            ->where('status', 'absent')
            ->orderByDesc('date')
            ->get()
            ->unique('student_id')
            ->take(2)
            ->values();

        $decider = Membership::query()
            ->where('branch_id', $branch->id)
            ->where('role', 'director')
            ->where('is_active', true)
            ->first()?->user_id;

        foreach ($absences as $i => $record) {
            $guardianUser = StudentGuardian::query()
                ->where('student_id', $record->student_id)
                ->where('is_active', true)
                ->with('parentProfile.user')
                ->first()?->parentProfile?->user;

            if ($guardianUser === null) {
                continue;
            }

            $approved = $i === 1;

            AbsenceExcuse::create([
                'school_id' => $branch->school_id,
                'branch_id' => $branch->id,
                'student_id' => $record->student_id,
                'requested_by' => $guardianUser->id,
                'starts_on' => $record->date,
                'ends_on' => $record->date,
                'reason' => $approved
                    ? 'Treated for malaria at the clinic — the doctor advised a day of rest.'
                    : 'Down with the flu; kept home to recover.',
                'status' => $approved ? 'approved' : 'pending',
                'decided_by' => $approved ? $decider : null,
                'decided_at' => $approved ? now()->subHours(3) : null,
            ]);

            if ($approved) {
                $record->update(['status' => 'excused']);
            }
        }
    }

    /**
     * Today's employee register + the leave workflow in both demo-worthy
     * states (one pending request to decide, one approved leave overlapping
     * today) — feeds the dashboard's "staff today" tile and HR queue.
     */
    private function buildStaffRegister(Branch $branch): void
    {
        LeavePolicy::provisionDefaults(School::find($branch->school_id));

        $employees = Employee::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->get();

        $today = now()->toDateString();
        $onLeave = $employees->pop(); // last one sits on approved leave today
        $pendingRequester = $employees->last();

        foreach ($employees as $employee) {
            $roll = mt_rand(1, 100);
            $status = match (true) {
                $roll <= 84 => 'present',
                $roll <= 94 => 'late',
                default => 'absent',
            };

            EmployeeAttendanceRecord::create([
                'school_id' => $branch->school_id,
                'branch_id' => $branch->id,
                'employee_id' => $employee->id,
                'date' => $today,
                'status' => $status,
                'source' => 'manual',
                'check_in' => match ($status) {
                    'present' => sprintf('07:%02d', mt_rand(30, 59)),
                    'late' => sprintf('08:%02d', mt_rand(15, 50)),
                    default => null,
                },
            ]);
        }

        $annual = LeaveType::query()
            ->where('school_id', $branch->school_id)
            ->where('code', 'annual')
            ->first();

        if ($annual === null) {
            return;
        }

        if ($onLeave !== null) {
            LeaveRequest::create([
                'school_id' => $branch->school_id, 'branch_id' => $branch->id,
                'employee_id' => $onLeave->id, 'leave_type_id' => $annual->id,
                'start_date' => now()->subDays(2)->toDateString(),
                'end_date' => now()->addDays(3)->toDateString(),
                'days' => 4, 'status' => 'approved',
                'reason' => 'Family visit to Hawassa',
                'decided_at' => now()->subDays(4),
            ]);
        }

        if ($pendingRequester !== null) {
            LeaveRequest::create([
                'school_id' => $branch->school_id, 'branch_id' => $branch->id,
                'employee_id' => $pendingRequester->id, 'leave_type_id' => $annual->id,
                'start_date' => now()->addDays(7)->toDateString(),
                'end_date' => now()->addDays(11)->toDateString(),
                'days' => 5, 'status' => 'pending',
                'reason' => 'Wedding preparations',
            ]);
        }
    }

    /** Bell schedule + a solver-generated, published timetable (showcase branches). */
    private function buildTimetable(Branch $branch, Term $term): void
    {
        foreach (TermPeriod::defaultsFor($term) as $rowAttributes) {
            $term->periods()->create($rowAttributes);
        }

        $version = $term->timetableVersions()->create([
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'name' => 'Semester 2 schedule',
            'status' => TimetableVersionStatus::Draft,
            'days' => [1, 2, 3, 4, 5],
        ]);

        $result = app(TimetableSolver::class)->solve($version);

        $version->update([
            'status' => TimetableVersionStatus::Published,
            'score' => $result['score'],
            'conflicts' => $result['conflicts'],
            'generated_at' => now(),
            'published_at' => now(),
        ]);
    }

    // ───────────────────────── cross-school extras ─────────────────────────

    /** Two live transfer requests between the first two schools. */
    private function createTransferRequests(): void
    {
        // Never hardcode ids — sequences keep counting across test seeds.
        [$firstSchoolId, $secondSchoolId] = School::query()->orderBy('id')->limit(2)->pluck('id')->all();

        $pairs = [
            [$firstSchoolId, $secondSchoolId], // school 1 requests a student from school 2
            [$secondSchoolId, $firstSchoolId],
        ];

        foreach ($pairs as [$toSchoolId, $fromSchoolId]) {
            $toBranch = Branch::where('school_id', $toSchoolId)->first();

            $enrollment = StudentEnrollment::query()
                ->where('school_id', $fromSchoolId)
                ->where('status', EnrollmentStatus::Active->value)
                ->inRandomOrder()
                ->first();

            if ($toBranch === null || $enrollment === null) {
                continue;
            }

            $toYear = AcademicYear::where('branch_id', $toBranch->id)
                ->where('status', 'active')
                ->first();

            if ($toYear === null) {
                continue;
            }

            StudentTransferRequest::create([
                'student_id' => $enrollment->student_id,
                'from_enrollment_id' => $enrollment->id,
                'from_school_id' => $enrollment->school_id,
                'from_branch_id' => $enrollment->branch_id,
                'to_school_id' => $toBranch->school_id,
                'to_branch_id' => $toBranch->id,
                'to_academic_year_id' => $toYear->id,
                'to_grade_level_id' => $enrollment->grade_level_id,
                'status' => 'requested',
                'reason' => 'Family moved to the area',
            ]);
        }
    }

    // ───────────────────────── LMS (showcase branch) ─────────────────────────

    /**
     * A living LMS demo on one class of the showcase branch: a question bank
     * with auto-gradable questions, a published multi-section exam with
     * attempts in every state (graded, in progress, not started), a homework
     * assignment with graded + pending submissions, a two-module course with
     * student progress, and course materials. The first portal student joins
     * the sample-logins table.
     *
     * @param  list<SubjectAssignment>  $classes  the active term's teaching grid
     * @param  list<array{student: Student, enrollment: StudentEnrollment, ability: int}>  $students
     */
    private function buildLms(Branch $branch, array $classes, array $students): void
    {
        if ($classes === []) {
            return;
        }

        /** @var SubjectAssignment $class */
        $class = collect($classes)->first(fn (SubjectAssignment $c) => $c->employee_id !== null) ?? $classes[0];
        $teacher = Employee::find($class->employee_id);
        $teacherUserId = $teacher?->user_id;

        // Classmates get portal accounts — quiz attempts and course progress
        // live on the USER (students are people, ADR-011).
        $classmates = collect($students)
            ->filter(fn (array $row) => $row['enrollment']->section_id === $class->section_id
                && $row['enrollment']->status === EnrollmentStatus::Active)
            ->take(8)
            ->values();

        if ($classmates->isEmpty()) {
            return;
        }

        foreach ($classmates as $i => $row) {
            /** @var Student $student */
            $student = $row['student'];

            // The second classmate demos the phone-less ID-login lane: no
            // phone of their own, signs in with student ID + PIN.
            if ($i === 1) {
                $user = User::create([
                    'name' => "{$student->first_name} {$student->father_name}",
                    'phone' => null,
                    'email' => null,
                    'password' => $this->passwordHash,
                    'preferred_language' => 'am',
                    'status' => AccountStatus::Active,
                ]);
                $student->update(['user_id' => $user->id]);
                $this->sampleLogins[] = [
                    'label' => 'Student — ID login (Unity Academy)',
                    'phone' => "student ID {$student->public_id}",
                ];

                continue;
            }

            $user = $this->makeUser("{$student->first_name} {$student->father_name}");
            $student->update(['user_id' => $user->id]);

            if ($i === 0) {
                $this->sampleLogins[] = ['label' => 'Student (Unity Academy)', 'phone' => $user->phone];
            }
        }

        $subjectName = Subject::find($class->subject_id)?->name ?? 'Subject';

        // ── Question bank + questions ────────────────────────────────────────
        $bank = QuestionBank::create([
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'subject_id' => $class->subject_id,
            'grade_level_id' => Section::find($class->section_id)?->grade_level_id,
            'name' => "{$subjectName} — Unit 1",
            'created_by' => $teacherUserId,
        ]);

        $questions = [
            $bank->questions()->create([
                'type' => 'mcq_single',
                'body' => ['stem' => 'Which option completes the pattern 2, 4, 8, …?', 'options' => [
                    ['id' => 'a', 'text' => '10'], ['id' => 'b', 'text' => '16'], ['id' => 'c', 'text' => '12'],
                ]],
                'answer_key' => ['correct' => 'b'],
                'points' => 2,
            ]),
            $bank->questions()->create([
                'type' => 'mcq_multi',
                'body' => ['stem' => 'Select every prime number.', 'options' => [
                    ['id' => 'a', 'text' => '2'], ['id' => 'b', 'text' => '9'], ['id' => 'c', 'text' => '11'],
                ]],
                'answer_key' => ['correct' => ['a', 'c']],
                'points' => 3,
            ]),
            $bank->questions()->create([
                'type' => 'numeric',
                'body' => ['stem' => 'A trader buys 3 items at 45 ETB each. What is the total in ETB?'],
                'answer_key' => ['value' => 135, 'tolerance' => 0.01],
                'points' => 3,
            ]),
            $bank->questions()->create([
                'type' => 'true_false',
                'body' => ['stem' => 'The Ethiopian calendar has 13 months.'],
                'answer_key' => ['correct' => true],
                'points' => 2,
            ]),
        ];

        // Rich math authoring: KaTeX markers in the stem AND the choices —
        // the textbook-grade look every science teacher asks for.
        $questions[] = $bank->questions()->create([
            'type' => 'mcq_single',
            'body' => [
                'stem' => '<p>Evaluate <span data-math="\int_0^1 2x\,dx"></span>.</p>',
                'options' => [
                    ['id' => 'a', 'text' => '<p><span data-math="1"></span></p>'],
                    ['id' => 'b', 'text' => '<p><span data-math="2"></span></p>'],
                    ['id' => 'c', 'text' => '<p><span data-math="\frac{1}{2}"></span></p>'],
                ],
            ],
            'answer_key' => ['correct' => 'a'],
            'points' => 3,
        ]);

        // A reading-passage GROUP: the container holds the text, its
        // sub-questions travel together on every paper.
        $passage = $bank->questions()->create([
            'type' => 'group',
            'body' => ['stem' => '<p>Read the passage and answer the questions that follow.</p><p><em>Abebe saves 150 ETB every month from his shop in Merkato. After six months he buys school supplies worth 700 ETB for his younger sister.</em></p>'],
            'points' => 1,
        ]);
        $questions[] = $bank->questions()->create([
            'parent_id' => $passage->id,
            'position' => 1,
            'type' => 'numeric',
            'body' => ['stem' => 'How much has Abebe saved after six months, in ETB?'],
            'answer_key' => ['value' => 900, 'tolerance' => 0.01],
            'points' => 2,
        ]);
        $questions[] = $bank->questions()->create([
            'parent_id' => $passage->id,
            'position' => 2,
            'type' => 'true_false',
            'body' => ['stem' => 'Abebe has money left after buying the supplies.'],
            'answer_key' => ['correct' => true],
            'points' => 2,
        ]);

        $totalPoints = collect($questions)->sum('points');

        // ── Published exam: anchor class + a sibling section of the same
        //    subject/grade (the multi-section fan-out is worth demoing) ───────
        $quiz = Quiz::create([
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'subject_assignment_id' => $class->id,
            'kind' => 'exam',
            'title' => "{$subjectName} Midterm Exam",
            'instructions' => '<p>Answer every question. You have one sitting.</p>',
            'settings' => [
                'attempts_allowed' => 1,
                'results_policy' => 'immediately',
                'duration_minutes' => 30,
                'shuffle_questions' => true,
            ],
            'total_points' => $totalPoints,
            'status' => 'published',
            'published_at' => now()->subDays(3),
            'created_by' => $teacherUserId,
        ]);

        $quiz->targets()->create(['subject_assignment_id' => $class->id]);
        $sibling = collect($classes)->first(fn (SubjectAssignment $c) => $c->id !== $class->id
            && $c->subject_id === $class->subject_id);
        if ($sibling) {
            $quiz->targets()->create(['subject_assignment_id' => $sibling->id]);
        }

        foreach (array_values($questions) as $i => $question) {
            $quiz->quizQuestions()->create(['question_id' => $question->id, 'sort_order' => $i]);
        }

        // Attempts: most classmates finished (score tracks ability), one is
        // mid-exam, the rest have not started — every state on one screen.
        foreach ($classmates as $i => $row) {
            /** @var Student $student */
            $student = $row['student']->refresh();
            if ($i >= 6) {
                continue; // never started
            }

            $inProgress = $i === 5;
            $attempt = $quiz->attempts()->create([
                'user_id' => $student->user_id,
                'student_id' => $student->id,
                'student_enrollment_id' => $row['enrollment']->id,
                'attempt_number' => 1,
                'status' => $inProgress ? 'in_progress' : 'graded',
                'started_at' => now()->subDays(2)->addMinutes($i * 7),
                'deadline_at' => now()->subDays(2)->addMinutes($i * 7 + 30),
                'submitted_at' => $inProgress ? null : now()->subDays(2)->addMinutes($i * 7 + 24),
                'graded_at' => $inProgress ? null : now()->subDays(2)->addMinutes($i * 7 + 24),
                'seed' => mt_rand(1, 999999),
                'question_ids' => collect($questions)
                    ->map(fn ($question): array => ['id' => $question->id, 'points' => (float) $question->points, 'part' => null])
                    ->all(),
                'max_score' => $totalPoints,
            ]);

            if ($inProgress) {
                continue;
            }

            $score = 0;
            foreach ($questions as $question) {
                $correct = mt_rand(1, 100) <= $row['ability'];
                $auto = $correct ? (float) $question->points : 0.0;
                $score += $auto;

                $attempt->answers()->create([
                    'question_id' => $question->id,
                    'answer' => $this->demoAnswer($question, $correct),
                    'auto_score' => $auto,
                    'answered_at' => $attempt->started_at->addMinutes(3),
                ]);
            }
            $attempt->update(['score' => $score]);
        }

        // ── Homework: graded + pending submissions, one late, plus a draft ───
        $homework = Assignment::create([
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'subject_assignment_id' => $class->id,
            'title' => "{$subjectName} — Exercise 1.3",
            'instructions' => '<p>Solve the exercise set and show your working.</p>',
            'submission_types' => ['text'],
            'max_score' => 10,
            'due_at' => now()->addDays(3),
            'status' => 'published',
            'published_at' => now()->subDays(4),
            'created_by' => $teacherUserId,
        ]);

        foreach ($classmates->take(5) as $i => $row) {
            $graded = $i < 3;
            $late = $i === 4;
            $homework->submissions()->create([
                'student_id' => $row['student']->id,
                'student_enrollment_id' => $row['enrollment']->id,
                'body' => 'x = 4 because 2x = 8. Working attached in class notebook.',
                'submitted_at' => $late ? now()->addDays(4) : now()->subDays($i),
                'is_late' => $late,
                'status' => $graded ? 'graded' : 'submitted',
                'score' => $graded ? round($row['ability'] / 10, 1) : null,
                'feedback' => $graded ? 'Neat work — check step 2 next time.' : null,
                'graded_by' => $graded ? $teacherUserId : null,
                'graded_at' => $graded ? now()->subDays($i)->addHours(6) : null,
            ]);
        }

        Assignment::create([
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'subject_assignment_id' => $class->id,
            'title' => "{$subjectName} — Group project (draft)",
            'submission_types' => ['text', 'file'],
            'max_score' => 20,
            'status' => 'draft',
            'created_by' => $teacherUserId,
        ]);

        // ── Course: two modules, reading + video lessons, real progress ──────
        $course = Course::create([
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'subject_id' => $class->subject_id,
            'title' => "{$subjectName} — Semester Companion",
            'description' => 'Follow-along lessons for this semester, unit by unit.',
            'language' => 'en',
            'status' => 'published',
            'published_at' => now()->subWeek(),
            'created_by' => $teacherUserId,
        ]);
        $course->targets()->create(['subject_assignment_id' => $class->id]);

        $lessons = [];
        foreach (['Unit 1: Foundations', 'Unit 2: Applications'] as $m => $moduleTitle) {
            $module = $course->modules()->create(['title' => $moduleTitle, 'sort_order' => $m]);
            $lessons[] = $module->lessons()->create([
                'course_id' => $course->id, 'type' => 'reading',
                'title' => "Reading — {$moduleTitle}",
                'content' => ['body' => '<p>Key ideas of this unit, with worked examples.</p>'],
                'sort_order' => 0,
            ]);
            $lessons[] = $module->lessons()->create([
                'course_id' => $course->id, 'type' => 'video',
                'title' => "Worked examples — {$moduleTitle}",
                'content' => ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
                'sort_order' => 1,
            ]);
        }

        foreach ($classmates->take(4) as $i => $row) {
            $userId = $row['student']->refresh()->user_id;
            foreach (array_slice($lessons, 0, 4 - $i) as $lesson) {
                DB::table('lesson_progress')->insert([
                    'user_id' => $userId,
                    'course_lesson_id' => $lesson->id,
                    'course_id' => $course->id,
                    'status' => 'completed',
                    'completed_at' => now()->subDays($i + 1),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // ── Materials: a pinned class link + a school-wide study note ────────
        $linked = CourseMaterial::create([
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'subject_id' => $class->subject_id,
            'title' => "{$subjectName} formula sheet",
            'type' => 'link',
            'content' => ['url' => 'https://example.et/formula-sheet'],
            'is_pinned' => true,
            'created_by' => $teacherUserId,
        ]);
        $linked->targets()->create(['subject_assignment_id' => $class->id]);

        CourseMaterial::create([
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'title' => 'How to prepare for exam week',
            'type' => 'text',
            'content' => ['body' => '<p>Sleep well, review your notes daily, and practise past questions.</p>'],
            'created_by' => $teacherUserId,
        ]);
    }

    /** A plausible stored answer for a demo attempt — right or deliberately wrong. */
    /**
     * Lesson planning on the showcase branch, in every demo-worthy state:
     * approved annual plans with unit timelines and a run of weekly plans
     * (approved weeks with coverage marked, one teacher LAGGING with a
     * justified week awaiting review, a declined week with the reason), plus
     * one submitted and one draft annual plan for the review inbox.
     *
     * @param  list<SubjectAssignment>  $classes  the active term's teaching grid
     */
    private function buildLessonPlans(Branch $branch, array $classes, AcademicYear $year): void
    {
        // One plan per teacher × subject × grade (the annual-plan identity).
        $groups = collect($classes)
            ->filter(fn (SubjectAssignment $c) => $c->employee_id !== null)
            ->groupBy(fn (SubjectAssignment $c) => $c->employee_id.':'.$c->subject_id.':'.$c->section->grade_level_id)
            ->take(6)
            ->values();

        $reviewerId = Membership::query()
            ->where('branch_id', $branch->id)
            ->where('role', Role::Director->value)
            ->where('is_active', true)
            ->value('user_id');

        $yearStart = $year->terms()->orderBy('sequence')->first()?->starts_on ?? now()->subMonths(4);

        foreach ($groups as $g => $group) {
            /** @var SubjectAssignment $class */
            $class = $group->first();
            $teacher = Employee::find($class->employee_id);
            // The last two groups stay in the review pipeline: one submitted
            // (inbox demo), one still drafting.
            $status = match (true) {
                $g === $groups->count() - 1 => LessonPlanStatus::Draft,
                $g === $groups->count() - 2 => LessonPlanStatus::Submitted,
                default => LessonPlanStatus::Approved,
            };

            $plan = AnnualLessonPlan::create([
                'school_id' => $branch->school_id,
                'branch_id' => $branch->id,
                'academic_year_id' => $year->id,
                'subject_id' => $class->subject_id,
                'grade_level_id' => $class->section->grade_level_id,
                'employee_id' => $teacher->id,
                'goals' => '<p>Complete the national syllabus with continuous assessment mastery above 80% and exam-readiness by year end.</p>',
                'methods' => '<p>Explanation, guided practice, peer work, weekly quizzes and remedial sessions for struggling students.</p>',
                'periods_per_week' => $class->periods_per_week ?? 5,
                'total_periods' => 130,
                'status' => $status,
                'submitted_at' => $status === LessonPlanStatus::Draft ? null : now()->subWeeks(mt_rand(6, 10)),
                'submitted_by' => $status === LessonPlanStatus::Draft ? null : $teacher->user_id,
                'decided_at' => $status === LessonPlanStatus::Approved ? now()->subWeeks(mt_rand(4, 6)) : null,
                'decided_by' => $status === LessonPlanStatus::Approved ? $reviewerId : null,
                'created_by' => $teacher->user_id,
            ]);

            // Six units spread across the year, ~5 weeks each.
            $cursor = Carbon::parse($yearStart)->startOfWeek();
            foreach (range(1, 6) as $i) {
                $plan->units()->create([
                    'school_id' => $branch->school_id,
                    'branch_id' => $branch->id,
                    'term_id' => $i <= 3 ? null : $class->term_id,
                    'sequence' => $i,
                    'title' => "Unit {$i} — ".['Foundations', 'Core concepts', 'Applications', 'Problem solving', 'Advanced topics', 'Revision & exams'][$i - 1],
                    'objectives' => 'Master the unit competencies and pass the unit test.',
                    'rationale' => 'The unit anchors what the rest of the semester builds on.',
                    'prerequisite_knowledge' => $i === 1 ? 'Last year\'s core skills.' : 'Competencies from unit '.($i - 1).'.',
                    'teaching_aids' => 'Textbook, blackboard diagrams, local materials.',
                    'assessment_techniques' => 'Oral questions, exercises, unit test.',
                    'page_from' => ($i - 1) * 30 + 1,
                    'page_to' => $i * 30,
                    'starts_on' => $cursor->toDateString(),
                    'ends_on' => $cursor->copy()->addWeeks(5)->subDay()->toDateString(),
                    'planned_periods' => mt_rand(18, 25),
                ]);
                $cursor = $cursor->addWeeks(5);
            }

            if ($status !== LessonPlanStatus::Approved) {
                continue;
            }

            // Weekly run: three past weeks + the current one. The SECOND
            // approved plan demos the lagging teacher (uncovered lessons +
            // a justified current week awaiting review); the third demos a
            // declined week.
            $lagging = $g === 1;
            $units = $plan->units()->get();

            foreach ([3, 2, 1, 0] as $offset) {
                $weekStart = now()->subWeeks($offset)->startOfWeek();
                $unit = $units->first(fn ($u) => $weekStart->betweenIncluded($u->starts_on, $u->ends_on)) ?? $units->last();

                $weekStatus = match (true) {
                    $offset === 0 && $lagging => LessonPlanStatus::Submitted,
                    $offset === 0 => [LessonPlanStatus::Draft, LessonPlanStatus::Submitted][$g % 2],
                    $offset === 1 && $g === 2 => LessonPlanStatus::Declined,
                    default => LessonPlanStatus::Approved,
                };

                $week = $plan->weeklyPlans()->create([
                    'school_id' => $branch->school_id,
                    'branch_id' => $branch->id,
                    'term_id' => $class->term_id,
                    'week_starts_on' => $weekStart->toDateString(),
                    'status' => $weekStatus,
                    'lag_justification' => $offset === 0 && $lagging
                        ? 'Two periods were lost to the inter-school sports day; carrying the remaining lessons into this week.'
                        : null,
                    'submitted_at' => $weekStatus === LessonPlanStatus::Draft ? null : $weekStart->copy()->subDays(2),
                    'submitted_by' => $weekStatus === LessonPlanStatus::Draft ? null : $teacher->user_id,
                    'decided_at' => in_array($weekStatus, [LessonPlanStatus::Approved, LessonPlanStatus::Declined], true) ? $weekStart->copy()->subDay() : null,
                    'decided_by' => in_array($weekStatus, [LessonPlanStatus::Approved, LessonPlanStatus::Declined], true) ? $reviewerId : null,
                    'decline_reason' => $weekStatus === LessonPlanStatus::Declined
                        ? 'The plan repeats last week\'s topics — align it with the annual plan and resubmit.'
                        : null,
                ]);

                $sections = $group->pluck('section')->filter()->unique('id')->values();

                foreach ([1, 2, 4] as $j => $day) {
                    $covered = match (true) {
                        $offset === 0 => LessonCoverage::Pending,
                        $lagging && $offset === 1 => [LessonCoverage::Covered, LessonCoverage::Missed, LessonCoverage::Partial][$j],
                        default => LessonCoverage::Covered,
                    };

                    $teachesOn = $weekStart->copy()->addDays($day - 1);

                    $dayPlan = $week->dailyPlans()->create([
                        'annual_plan_unit_id' => $unit->id,
                        'teaches_on' => $teachesOn->toDateString(),
                        'topic' => $unit->title.' — lesson '.($j + 1 + (3 - $offset) * 3),
                        'subtopic' => $j === 1 ? 'Worked examples' : null,
                        'rationale' => 'The skill is the base for the next lesson and the unit test.',
                        'prerequisite_knowledge' => 'The previous lesson\'s concept.',
                        'objectives' => 'Students can apply the concept independently.',
                        'support_slow' => 'Extra guided example with the teacher during practice time.',
                        'support_medium' => 'Pair practice with the standard exercise set.',
                        'support_fast' => 'Challenge exercise from the end of the chapter.',
                        'homework' => $j === 2 ? 'Exercise set '.(4 - $offset).' from the textbook.' : null,
                        'sequence' => 0,
                    ]);

                    foreach ([
                        ['stage' => LessonStage::Intro, 'learning_contents' => 'Recap and today\'s question', 'teacher_activity' => 'Ask review questions; pose the opening problem.', 'student_activity' => 'Answer, discuss in pairs.', 'assessment_techniques' => 'Oral questioning', 'teaching_aids' => 'Blackboard'],
                        ['stage' => LessonStage::Main, 'learning_contents' => 'The core concept with worked examples', 'teacher_activity' => 'Demonstrate two worked examples; circulate during practice.', 'student_activity' => 'Solve guided exercises, then independent practice.', 'assessment_techniques' => 'Exercise check', 'teaching_aids' => 'Textbook, chart'],
                        ['stage' => LessonStage::Conclusion, 'learning_contents' => 'Summary and exit check', 'teacher_activity' => 'Summarise the rule; give the exit question.', 'student_activity' => 'Answer the exit question individually.', 'assessment_techniques' => 'Exit ticket'],
                    ] as $stageRow) {
                        $dayPlan->stages()->create([...$stageRow, 'page' => $unit->page_from !== null ? (string) ($unit->page_from + $j) : null]);
                    }

                    foreach ($sections as $s => $section) {
                        $dayPlan->deliveries()->create([
                            'section_id' => $section->id,
                            'teaches_on' => $teachesOn->toDateString(),
                            'period_number' => min(8, $j + 1 + $s),
                            'coverage' => $covered,
                            'coverage_note' => $covered === LessonCoverage::Missed ? 'Class cancelled for the sports day.' : null,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * A lived-in chat world on the showcase branch (ADR-019): the system
     * channels with traffic (staff room chatter with a mention + reactions,
     * a director announcement, a classroom post), one teacher↔family direct
     * thread in every approval state (sent both ways, one PENDING in the
     * communication book, one rejected with a note), and unread state for
     * the sample logins. Rows are inserted directly — a seeder must never
     * notify or text anyone.
     *
     * @param  list<SubjectAssignment>  $classes
     * @param  list<array{student: Student, enrollment: StudentEnrollment, ability: int}>  $students
     */
    private function buildChat(Branch $branch, array $classes, array $students): void
    {
        if ($classes === []) {
            return;
        }

        app(ChannelProvisioner::class)->ensureForSchool($branch->school);

        // Preset messages the composer's template picker offers (tri-language,
        // placeholder-driven) — the library a real school would curate.
        foreach ([
            ['Absence today', 'attendance', [
                'en' => 'Dear parent, {student_name} was absent today. Please share the reason with us.',
                'am' => 'ውድ ወላጅ፣ {student_name} ዛሬ ከትምህርት ቀርቷል። እባክዎ ምክንያቱን ያሳውቁን።',
                'om' => 'Kabajamaa maatii, {student_name} har’a barumsa irraa hafeera. Maaloo sababa isaa nuuf himaa.',
            ]],
            ['Homework reminder', 'homework', [
                'en' => 'Dear parent, {student_name} has homework due tomorrow. Kindly make sure it is completed.',
                'am' => 'ውድ ወላጅ፣ {student_name} ለነገ የቤት ስራ አለበት። እባክዎ መጠናቀቁን ያረጋግጡ።',
                'om' => 'Kabajamaa maatii, {student_name} hojii manaa boriif qaba. Akka xumuramu mirkaneessaa.',
            ]],
            ['Great work', 'praise', [
                'en' => '{student_name} did excellent work in class today — we are proud of the progress!',
                'am' => '{student_name} ዛሬ በክፍል ውስጥ ጥሩ ስራ ሰርቷል — በእድገቱ ኩራት ይሰማናል!',
                'om' => '{student_name} har’a daree keessatti hojii cimaa hojjete — guddina isaatti boonna!',
            ]],
            ['Meeting request', 'meeting', [
                'en' => 'Dear parent, we would like to meet you about {student_name}. Please visit the school this week.',
                'am' => 'ውድ ወላጅ፣ ስለ {student_name} ልናገኝዎ እንፈልጋለን። እባክዎ በዚህ ሳምንት ትምህርት ቤት ይምጡ።',
                'om' => 'Kabajamaa maatii, waa’ee {student_name} isin arguu barbaanna. Maaloo torban kana mana barumsaa koottaa.',
            ]],
        ] as [$name, $category, $body]) {
            ChatMessageTemplate::updateOrCreate(
                ['school_id' => $branch->school_id, 'branch_id' => null, 'name' => $name],
                ['category' => $category, 'body' => $body, 'is_active' => true],
            );
        }

        $class = collect($classes)->first(fn (SubjectAssignment $c) => $c->employee_id !== null) ?? $classes[0];
        $teacher = Employee::find($class->employee_id);
        $teacherUser = $teacher?->user_id === null ? null : User::find($teacher->user_id);

        $director = User::query()
            ->whereIn('id', Membership::query()
                ->where('branch_id', $branch->id)
                ->where('role', Role::Director->value)
                ->where('is_active', true)
                ->select('user_id'))
            ->first();

        if ($teacherUser === null || $director === null) {
            return;
        }

        $say = function (Conversation $c, ?User $author, string $body, array $extra = [], int $minutesAgo = 0): ChatMessage {
            $message = $c->messages()->create([
                'user_id' => $author?->id,
                'body' => $body,
                ...$extra,
            ]);
            $message->forceFill(['created_at' => now()->subMinutes($minutesAgo)])->save();
            if (($extra['status'] ?? 'sent') === 'sent') {
                $c->forceFill(['last_message_at' => $message->created_at])->save();
            }

            return $message;
        };

        // ── Staff room: chatter, a mention, reactions ────────────────────
        $staffRoom = Conversation::where('system_key', "branch:{$branch->id}:staff_room")->first();
        if ($staffRoom !== null) {
            $say($staffRoom, $director, 'Reminder: staff meeting Thursday at 3:30 in the library.', [], 26 * 60);
            $m = $say($staffRoom, $teacherUser, "Noted. @[user:{$director->id}] should we bring the semester marklists?", [], 25 * 60);
            $say($staffRoom, $director, 'Yes please — and the continuous assessment plans.', [], 24 * 60);
            $m->reactions()->create(['user_id' => $director->id, 'emoji' => '👍']);
        }

        // ── Branch announcements: one admin post with reactions ─────────
        $announcements = Conversation::where('system_key', "branch:{$branch->id}:announcements")->first();
        if ($announcements !== null) {
            $post = $say($announcements, $director, 'School closes at noon this Friday for staff development. Classes resume Monday as usual.', [], 8 * 60);
            $post->reactions()->create(['user_id' => $teacherUser->id, 'emoji' => '👍']);
        }

        // ── Classroom channel: the teacher posts homework ────────────────
        $classroom = Conversation::where('system_key', "classroom:{$class->section_id}")->first();
        if ($classroom !== null) {
            $say($classroom, $teacherUser, 'Homework: exercise 4.2, questions 1–6. Due Thursday. Ask here if anything is unclear.', [], 5 * 60);
        }

        // ── One family direct thread in every approval state ─────────────
        $family = collect($students)->first(function (array $row): bool {
            return StudentGuardian::query()
                ->where('student_id', $row['student']->id)
                ->where('is_active', true)
                ->whereHas('parentProfile', fn ($q) => $q->whereNotNull('user_id'))
                ->exists();
        });

        if ($family !== null) {
            $student = $family['student'];
            $guardianUser = StudentGuardian::query()
                ->where('student_id', $student->id)->where('is_active', true)
                ->with('parentProfile.user')->get()
                ->map(fn (StudentGuardian $g) => $g->parentProfile?->user)
                ->filter()->first();

            $thread = Conversation::firstOrCreate(
                ['direct_key' => Conversation::directKeyFor($branch->school_id, $student->id, $teacherUser->id)],
                [
                    'school_id' => $branch->school_id,
                    'branch_id' => $branch->id,
                    'kind' => 'direct',
                    'student_id' => $student->id,
                    'created_by' => $teacherUser->id,
                ],
            );
            $thread->participants()->firstOrCreate(['user_id' => $teacherUser->id], []);

            $say($thread, $teacherUser, "Selam! {$student->first_name} did very well on this week's algebra quiz — 9 out of 10.", [], 3 * 24 * 60);
            if ($guardianUser !== null) {
                $say($thread, $guardianUser, 'Thank you for letting us know! We are proud of her. Anything we should practice at home?', [], 3 * 24 * 60 - 30);
            }
            $say($thread, $teacherUser, 'Fractions could use a little practice — ten minutes a day is plenty.', [], 2 * 24 * 60);
            $say($thread, $teacherUser, 'Also — please make sure the exercise book is covered before Monday.', [
                'status' => ChatMessage::STATUS_REJECTED,
                'reviewed_by' => $director->id,
                'reviewed_at' => now()->subDay(),
                'review_note' => 'Please mention which subject the book is for, then resend.',
            ], 26 * 60);
            $say($thread, $teacherUser, 'Please make sure the mathematics exercise book is covered before Monday.', [
                'status' => ChatMessage::STATUS_PENDING,
            ], 60);
        }
    }

    /** Raw answer shapes, exactly as the exam player submits them (ADR-016). */
    private function demoAnswer(Question $question, bool $correct): mixed
    {
        return match ($question->type) {
            QuestionType::McqSingle => $correct ? $question->answer_key['correct'] : 'a',
            QuestionType::McqMulti => $correct ? $question->answer_key['correct'] : ['b'],
            QuestionType::Numeric => $correct ? $question->answer_key['value'] : 99,
            QuestionType::TrueFalse => $correct ? $question->answer_key['correct'] : ! $question->answer_key['correct'],
            default => $correct ? 'A complete answer.' : 'Not sure.',
        };
    }

    // ─────────────── coverage extras (empty-table audit, July 2026) ───────────────

    /** National holidays inside the active 2018 E.C. year — the HR register overlay. */
    private function buildHolidays(School $school): void
    {
        $holidays = [
            ['Meskel', '2025-09-27'],
            ['Ethiopian Christmas (Genna)', '2026-01-07'],
            ['Timket (Epiphany)', '2026-01-19'],
            ['Adwa Victory Day', '2026-03-02'],
            ['Eid al-Fitr', '2026-03-20'],
            ['Ethiopian Good Friday', '2026-04-10'],
            ['Fasika (Ethiopian Easter)', '2026-04-12'],
            ['International Labour Day', '2026-05-01'],
        ];

        foreach ($holidays as [$name, $date]) {
            Holiday::create(['school_id' => $school->id, 'name' => $name, 'date' => $date]);
        }
    }

    /**
     * Explicit grading policies for two schools: the showcase school renders
     * letters on the Ethiopian A–F scale, school #3 pins the percentage scale
     * — the rest demo the platform default fallback.
     */
    private function buildGradingPolicy(School $school, int $index): void
    {
        $scaleName = match ($index) {
            0 => 'Ethiopian Letter (A–F)',
            2 => 'Percentage (0–100)',
            default => null,
        };

        if ($scaleName === null) {
            return;
        }

        $scale = GradingScale::query()->where('name', $scaleName)->first();

        if ($scale !== null) {
            GradingPolicy::create([
                'school_id' => $school->id,
                'grading_scale_id' => $scale->id,
                'display' => $index === 0 ? 'letter' : 'numeric',
            ]);
        }
    }

    /**
     * A homeroom teacher for every section of the active year — homeroom-gated
     * features (teacher attendance, communication book) need these to demo.
     *
     * @param  array<string, list<Section>>  $sections
     * @param  list<Employee>  $teachers
     */
    private function buildHomerooms(Branch $branch, AcademicYear $year, array $sections, array $teachers): void
    {
        if ($teachers === []) {
            return;
        }

        $i = 0;

        foreach ($sections as $gradeSections) {
            foreach ($gradeSections as $section) {
                SectionHomeroom::create([
                    'section_id' => $section->id,
                    'academic_year_id' => $year->id,
                    'employee_id' => $teachers[$i++ % count($teachers)]->id,
                ]);
            }
        }
    }

    /** Qualifications for every staff member + allowances/deductions payroll can chew on. */
    private function buildHrExtras(Branch $branch): void
    {
        $employees = Employee::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->with('activePositions')
            ->get();

        foreach ($employees as $i => $employee) {
            $isTeacher = $employee->activePositions->contains(fn ($p) => $p->job_title === 'teacher');

            $employee->qualifications()->create([
                'education_level' => $isTeacher ? (mt_rand(1, 100) <= 70 ? 'bachelor' : 'master') : 'diploma',
                'field_of_study' => $isTeacher ? 'Education' : 'Management',
                'institution' => ['Addis Ababa University', 'Kotebe University of Education', 'Bahir Dar University'][$i % 3],
                'graduation_year' => mt_rand(2008, 2021),
            ]);

            // Every third employee carries a transport allowance; a couple
            // repay a credit-association deduction — payslips land non-trivial.
            if ($i % 3 === 0) {
                $employee->allowances()->create(['name' => 'Transport allowance', 'amount' => 1500]);
            }

            if ($i % 4 === 0) {
                $employee->deductions()->create(['name' => 'Credit association', 'amount' => 500]);
            }
        }
    }

    /** One PAID run (Sene, last month) and one DRAFT run (Hamle) via the real compute action. */
    private function buildPayroll(Branch $branch): void
    {
        $director = Membership::query()
            ->where('branch_id', $branch->id)
            ->where('role', 'director')
            ->where('is_active', true)
            ->first()?->user_id;

        $compute = app(ComputePayrollAction::class);

        $paid = PayrollRun::create([
            'school_id' => $branch->school_id, 'branch_id' => $branch->id,
            'name' => 'Sene 2018 E.C.',
            'period_start' => '2026-06-08', 'period_end' => '2026-07-07',
            'status' => 'draft', 'created_by' => $this->financeUserId,
        ]);
        $compute->execute($paid);
        $paid->update([
            'status' => 'paid',
            'approved_by' => $director, 'approved_at' => now()->subDays(12),
            'paid_by' => $this->financeUserId, 'paid_at' => now()->subDays(10),
        ]);

        $draft = PayrollRun::create([
            'school_id' => $branch->school_id, 'branch_id' => $branch->id,
            'name' => 'Hamle 2018 E.C.',
            'period_start' => '2026-07-08', 'period_end' => '2026-08-06',
            'status' => 'draft', 'created_by' => $this->financeUserId,
        ]);
        $compute->execute($draft);
    }

    /**
     * The finance books beyond fees: categories, approved + pending expenses
     * (the four-eyes queue), other incomes and a budget per expense category
     * so budget-vs-actual has bars to draw.
     */
    private function buildFinanceOps(Branch $branch, AcademicYear $year): void
    {
        $principal = Membership::query()
            ->where('school_id', $branch->school_id)
            ->where('role', 'principal')
            ->where('is_active', true)
            ->first()?->user_id;

        $expenseCategories = [];

        foreach (['Utilities', 'Maintenance & repairs', 'Teaching materials'] as $name) {
            $expenseCategories[] = FinanceCategory::create([
                'school_id' => $branch->school_id, 'kind' => 'expense', 'name' => $name,
            ]);
        }

        $rental = FinanceCategory::create([
            'school_id' => $branch->school_id, 'kind' => 'income', 'name' => 'Hall rental',
        ]);

        $account = $this->accountFor($branch->school_id, 'bank_transfer');

        $expenseRows = [
            ['Electricity bill — Megabit', 18500, 90, 'cash', 'Ethiopian Electric Utility'],
            ['Water bill — Miazia', 6200, 60, 'cash', 'AAWSA'],
            ['Whiteboard markers & chalk', 9400, 45, 'bank_transfer', 'Mega Stationery PLC'],
            ['Roof repair — Block B', 42000, 20, 'bank_transfer', 'Selam Construction'],
        ];

        foreach ($expenseRows as $i => [$title, $amount, $daysAgo, $method, $payee]) {
            Expense::create([
                'school_id' => $branch->school_id, 'branch_id' => $branch->id,
                'finance_category_id' => $expenseCategories[$i % 3]->id,
                'title' => $title, 'amount' => $amount,
                'expense_date' => now()->subDays($daysAgo)->toDateString(),
                'method' => $method,
                'bank_account_id' => $method === 'bank_transfer' ? $account?->id : null,
                'payee' => $payee,
                'status' => 'approved',
                'recorded_by' => $this->financeUserId,
                'approved_by' => $principal,
                'approved_at' => now()->subDays($daysAgo - 1),
            ]);
        }

        // One awaiting approval — the self-approval rule's review queue.
        Expense::create([
            'school_id' => $branch->school_id, 'branch_id' => $branch->id,
            'finance_category_id' => $expenseCategories[1]->id,
            'title' => 'Window glass replacement — Grade 9 wing', 'amount' => 15800,
            'expense_date' => now()->subDays(2)->toDateString(),
            'method' => 'cash', 'payee' => 'Tesfa Glass Works',
            'status' => 'pending', 'recorded_by' => $this->financeUserId,
        ]);

        OtherIncome::create([
            'school_id' => $branch->school_id, 'branch_id' => $branch->id,
            'finance_category_id' => $rental->id,
            'title' => 'Assembly hall rental — wedding', 'amount' => 25000,
            'received_on' => now()->subDays(30)->toDateString(),
            'method' => 'bank_transfer', 'bank_account_id' => $account?->id,
            'source' => 'Private booking', 'recorded_by' => $this->financeUserId,
        ]);

        foreach ($expenseCategories as $i => $category) {
            Budget::create([
                'school_id' => $branch->school_id, 'branch_id' => $branch->id,
                'academic_year_id' => $year->id,
                'finance_category_id' => $category->id,
                'amount' => [120000, 200000, 80000][$i],
            ]);
        }
    }

    /**
     * The NFC lane: student + staff ID cards, this morning's gate scans
     * (processed + one unknown card) and a lost-card replacement request.
     *
     * @param  list<array{student: Student, enrollment: StudentEnrollment, ability: int}>  $students
     */
    private function buildNfc(Branch $branch, array $students): void
    {
        $gate = Device::query()->where('branch_id', $branch->id)->first();

        Device::create([
            'school_id' => $branch->school_id, 'branch_id' => $branch->id,
            'name' => 'Staff room reader', 'location' => 'Staff room',
            'audience' => 'employees',
            'token_hash' => Device::hashToken(Device::mintToken()),
            'last_seen_at' => now(),
        ]);

        $active = array_values(array_filter(
            $students,
            fn (array $row) => $row['enrollment']->status === EnrollmentStatus::Active,
        ));

        $cards = [];

        foreach (array_slice($active, 0, 8) as $i => $row) {
            $cards[] = IdCard::create([
                'school_id' => $branch->school_id, 'branch_id' => $branch->id,
                'card_uid' => sprintf('04A1%08X', 0x5B0000 + $i),
                'holder_type' => $row['student']->getMorphClass(),
                'holder_id' => $row['student']->id,
                'status' => 'active',
                'issued_on' => now()->subMonths(4)->toDateString(),
                'issued_by' => $this->financeUserId,
            ]);
        }

        foreach (Employee::query()->where('branch_id', $branch->id)->limit(2)->get() as $j => $employee) {
            IdCard::create([
                'school_id' => $branch->school_id, 'branch_id' => $branch->id,
                'card_uid' => sprintf('04B2%08X', 0x1F0000 + $j),
                'holder_type' => $employee->getMorphClass(),
                'holder_id' => $employee->id,
                'status' => 'active',
                'issued_on' => now()->subMonths(4)->toDateString(),
                'issued_by' => $this->financeUserId,
            ]);
        }

        if ($gate !== null) {
            foreach (array_slice($cards, 0, 5) as $k => $card) {
                DeviceEvent::create([
                    'device_id' => $gate->id,
                    'school_id' => $branch->school_id, 'branch_id' => $branch->id,
                    'card_uid' => $card->card_uid,
                    'event_uid' => sprintf('EVT-%s-%02d', now()->format('Ymd'), $k + 1),
                    'scanned_at' => now()->setTime(7, 20 + $k),
                    'received_at' => now()->setTime(7, 21 + $k),
                    'id_card_id' => $card->id,
                    'holder_type' => $card->holder_type, 'holder_id' => $card->holder_id,
                    'status' => DeviceEvent::STATUS_PROCESSED,
                ]);
            }

            // A tap from a card nobody issued — the review queue's classic row.
            DeviceEvent::create([
                'device_id' => $gate->id,
                'school_id' => $branch->school_id, 'branch_id' => $branch->id,
                'card_uid' => '04DEADBEEF01',
                'event_uid' => sprintf('EVT-%s-UNK', now()->format('Ymd')),
                'scanned_at' => now()->setTime(7, 45),
                'received_at' => now()->setTime(7, 46),
                'status' => DeviceEvent::STATUS_UNKNOWN_CARD,
            ]);
        }

        if ($cards !== []) {
            $lost = $cards[count($cards) - 1];
            $lost->update(['status' => 'lost', 'deactivated_at' => now()->subDay()]);

            CardRequest::create([
                'school_id' => $branch->school_id, 'branch_id' => $branch->id,
                'id_card_id' => $lost->id,
                'holder_type' => $lost->holder_type, 'holder_id' => $lost->holder_id,
                'reason' => 'lost', 'status' => 'requested',
                'note' => 'Card lost on the way home; parent already paid the replacement fee.',
                'requested_by' => $this->financeUserId,
            ]);
        }
    }

    /** Sign-off rows for the closed term's marklists — approved, plus one awaiting review. */
    /**
     * Teacher appraisals in every workflow state: the closed term carries
     * acknowledged records with varied scores (the trend a director reads),
     * the active term one submitted (teacher yet to sign) and one draft.
     */
    private function buildEvaluations(Branch $branch, Term $closedTerm, Term $activeTerm): void
    {
        $template = EvaluationPolicy::templateFor(School::find($branch->school_id))->load('criteria');

        $evaluator = Membership::query()
            ->where('branch_id', $branch->id)
            ->where('role', 'director')
            ->where('is_active', true)
            ->first()?->user_id;

        $teachers = Employee::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->whereIn('id', SubjectAssignment::query()->where('term_id', $closedTerm->id)->select('employee_id'))
            ->orderBy('id')
            ->limit(4)
            ->get();

        $make = function (Employee $teacher, Term $term, string $status, float $base) use ($template, $branch, $evaluator): void {
            $evaluation = TeacherEvaluation::updateOrCreate(
                ['employee_id' => $teacher->id, 'term_id' => $term->id],
                [
                    'school_id' => $branch->school_id,
                    'branch_id' => $branch->id,
                    'evaluation_template_id' => $template->id,
                    'evaluator_id' => $evaluator,
                    'status' => $status,
                    'strengths' => $status === 'draft' ? null : 'Well-prepared lessons and steady classroom control.',
                    'improvements' => $status === 'draft' ? null : 'Submit weekly plans earlier and vary assessment formats.',
                    'teacher_comment' => $status === 'acknowledged' ? 'Seen — thank you for the feedback.' : null,
                    'submitted_at' => $status === 'draft' ? null : now()->subDays(10),
                    'acknowledged_at' => $status === 'acknowledged' ? now()->subDays(7) : null,
                ],
            );

            $evaluation->scores()->delete();
            foreach ($template->criteria as $index => $criterion) {
                $score = $status === 'draft' && $index > 2
                    ? null // half-filled worksheet
                    : round(min((float) $criterion->max_score, max(2, $base + (($index % 3) - 1) * 0.5)), 1);
                $evaluation->scores()->create([
                    'evaluation_criterion_id' => $criterion->id,
                    'domain' => $criterion->domain,
                    'label' => $criterion->label,
                    'weight' => $criterion->weight,
                    'max_score' => $criterion->max_score,
                    'score' => $score,
                    'sort_order' => $criterion->sort_order,
                ]);
            }

            $evaluation->update(['overall_score' => $evaluation->fresh('scores')->computeOverall()]);
        };

        foreach ($teachers as $index => $teacher) {
            $make($teacher, $closedTerm, 'acknowledged', 3.4 + $index * 0.4);
        }
        if ($teachers->count() >= 2) {
            $make($teachers[0], $activeTerm, 'submitted', 4.2);
            $make($teachers[1], $activeTerm, 'draft', 3.8);
        }
    }

    private function buildMarklists(Branch $branch, Term $closedTerm): void
    {
        $director = Membership::query()
            ->where('branch_id', $branch->id)
            ->where('role', 'director')
            ->where('is_active', true)
            ->first()?->user_id;

        $assignments = SubjectAssignment::query()
            ->where('branch_id', $branch->id)
            ->where('term_id', $closedTerm->id)
            ->whereNotNull('employee_id')
            ->with('employee:id,user_id')
            ->orderBy('id')
            ->limit(6)
            ->get();

        foreach ($assignments as $i => $assignment) {
            $submittedAt = now()->subDays(25 - $i);
            $awaiting = $i === 0;

            Marklist::create([
                'subject_assignment_id' => $assignment->id,
                'school_id' => $branch->school_id, 'branch_id' => $branch->id,
                'term_id' => $closedTerm->id,
                'status' => $awaiting ? 'submitted' : 'approved',
                'submitted_at' => $submittedAt,
                'submitted_by' => $assignment->employee?->user_id,
                'approved_at' => $awaiting ? null : $submittedAt->copy()->addDay(),
                'approved_by' => $awaiting ? null : $director,
                // The awaiting row demoes the trust lane: the director entered
                // marks on behalf of the teacher (badged in the approval queue)
                // and is therefore four-eyes-blocked from approving it.
                'assisted_by' => $awaiting ? $director : null,
                'assisted_at' => $awaiting ? $submittedAt->copy()->subDays(2) : null,
                'assist_reason' => $awaiting ? 'Teacher was on sick leave during final exam week.' : null,
            ]);
        }
    }

    /** Payment-proof submissions in all three demo states: needs review, failed, verified. */
    private function buildPaymentVerifications(Branch $branch): void
    {
        $guardianUserFor = fn (int $studentId) => StudentGuardian::query()
            ->where('student_id', $studentId)
            ->where('is_active', true)
            ->with('parentProfile.user')
            ->first()?->parentProfile?->user;

        $unpaid = Invoice::query()
            ->where('branch_id', $branch->id)
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
            ->orderBy('id')
            ->limit(30)
            ->get();

        $states = [
            ['needs_review', null],
            ['failed', 'Transaction number not found at the bank.'],
        ];

        foreach ($unpaid as $invoice) {
            if ($states === []) {
                break;
            }

            $submitter = $guardianUserFor($invoice->student_id);

            if ($submitter === null) {
                continue;
            }

            [$status, $failure] = array_shift($states);

            PaymentVerification::create([
                'invoice_id' => $invoice->id, 'student_id' => $invoice->student_id,
                'submitted_by' => $submitter->id,
                'method' => 'bank_transfer', 'bank_code' => 'CBE',
                'transaction_number' => 'FT'.mt_rand(26000000, 26999999).'XK',
                'status' => $status, 'failure_reason' => $failure,
            ]);
        }

        // A verified one, anchored to a real recorded payment.
        $payment = Payment::query()
            ->where('branch_id', $branch->id)
            ->whereNotNull('invoice_id')
            ->orderBy('id')
            ->first();

        $submitter = $payment === null ? null : $guardianUserFor($payment->student_id);

        if ($payment !== null && $submitter !== null) {
            PaymentVerification::create([
                'invoice_id' => $payment->invoice_id, 'student_id' => $payment->student_id,
                'submitted_by' => $submitter->id,
                'method' => 'bank_transfer', 'bank_code' => 'CBE',
                'transaction_number' => 'FT'.mt_rand(26000000, 26999999).'VR',
                'status' => 'verified', 'payment_id' => $payment->id,
                'reviewed_by' => $this->financeUserId, 'reviewed_at' => now()->subDays(3),
            ]);
        }
    }

    /**
     * Health-register rows for a few students so the profile tab and the
     * homeroom teacher's medical flags land populated.
     *
     * @param  list<array{student: Student, enrollment: StudentEnrollment, ability: int}>  $students
     */
    private function buildStudentHealth(array $students): void
    {
        $conditions = HealthCondition::query()
            ->whereIn('name', ['Asthma', 'Food allergy', 'Epilepsy / seizure disorder', 'Diabetes (Type 1)'])
            ->pluck('id')
            ->values();

        if ($conditions->isEmpty()) {
            return;
        }

        $notes = [
            ['moderate', 'Carries an inhaler; avoid dusty outdoor drills.', 'Salbutamol inhaler'],
            ['severe', 'Peanut allergy — canteen staff informed.', 'EpiPen in the clinic fridge'],
            ['mild', 'Last episode over a year ago; teacher briefed.', null],
            ['moderate', 'Checks glucose before sport; snack allowed in class.', 'Insulin (self-administered)'],
        ];

        foreach (array_slice($students, 0, 4) as $i => $row) {
            [$severity, $note, $medication] = $notes[$i];

            $row['student']->healthConditions()->syncWithoutDetaching([
                $conditions[$i % $conditions->count()] => [
                    'severity' => $severity, 'notes' => $note, 'medication' => $medication,
                ],
            ]);
        }
    }

    /**
     * Unavailability windows for two teachers — the timetable solver's inputs.
     *
     * @param  list<Employee>  $teachers
     */
    private function buildTeacherAvailabilities(array $teachers): void
    {
        if (count($teachers) < 2) {
            return;
        }

        TeacherAvailability::create([
            'employee_id' => $teachers[0]->id,
            'day_of_week' => 3, 'from_period' => 1, 'to_period' => 2,
            'note' => 'MSc classes at AAU on Wednesday mornings',
        ]);

        TeacherAvailability::create([
            'employee_id' => $teachers[1]->id,
            'day_of_week' => 5, 'from_period' => null, 'to_period' => null,
            'note' => 'Teaches at the night program on Fridays',
        ]);
    }

    /**
     * One mid-year withdrawal with its QR-verifiable clearance letter token.
     *
     * @param  list<array{student: Student, enrollment: StudentEnrollment, ability: int}>  $students
     */
    private function buildWithdrawal(Branch $branch, array $students): void
    {
        $row = null;

        foreach (array_reverse($students) as $candidate) {
            if ($candidate['enrollment']->status === EnrollmentStatus::Active) {
                $row = $candidate;
                break;
            }
        }

        if ($row === null) {
            return;
        }

        $row['enrollment']->update(['status' => EnrollmentStatus::Withdrawn]);

        StudentWithdrawal::create([
            'student_id' => $row['student']->id,
            'enrollment_id' => $row['enrollment']->id,
            'school_id' => $branch->school_id, 'branch_id' => $branch->id,
            'reason' => 'Family relocating to Dire Dawa for work.',
            'destination' => 'Dire Dawa',
            'withdrawn_on' => now()->subDays(14)->toDateString(),
            'outstanding_amount' => 0,
            'public_token' => Str::random(48),
            'withdrawn_by' => $this->financeUserId,
        ]);
    }

    /** The promotion board's audit trail: executed decisions from last year's rollover. */
    private function buildPromotionLedger(Branch $branch, AcademicYear $activeYear): void
    {
        $previousYear = AcademicYear::query()
            ->where('branch_id', $branch->id)
            ->where('name', '2017 E.C.')
            ->first();

        $director = Membership::query()
            ->where('branch_id', $branch->id)
            ->where('role', 'director')
            ->where('is_active', true)
            ->first()?->user_id;

        if ($previousYear === null) {
            return;
        }

        $sourceEnrollments = StudentEnrollment::query()
            ->where('academic_year_id', $previousYear->id)
            ->where('branch_id', $branch->id)
            ->orderBy('id')
            ->limit(12)
            ->get();

        foreach ($sourceEnrollments as $source) {
            $target = StudentEnrollment::query()
                ->where('student_id', $source->student_id)
                ->where('academic_year_id', $activeYear->id)
                ->orderBy('id')
                ->first();

            if ($target === null) {
                continue;
            }

            $repeated = $target->grade_level_id === $source->grade_level_id;

            StudentPromotion::create([
                'student_id' => $source->student_id,
                'academic_year_id' => $previousYear->id,
                'from_enrollment_id' => $source->id,
                'to_enrollment_id' => $target->id,
                'from_grade_level_id' => $source->grade_level_id,
                'to_grade_level_id' => $target->grade_level_id,
                'from_branch_id' => $source->branch_id,
                'to_branch_id' => $target->branch_id,
                'decision' => $repeated ? 'repeated' : 'promoted',
                'average' => $repeated ? mt_rand(380, 490) / 10 : mt_rand(550, 940) / 10,
                'decided_by' => $director,
                'decided_at' => '2025-07-15 10:00:00',
                'executed_at' => '2025-08-20 09:00:00',
            ]);
        }
    }

    /** A parent-initiated transfer application into the showcase school, awaiting decision. */
    private function buildTransferApplication(): void
    {
        [$firstSchoolId, $secondSchoolId] = School::query()->orderBy('id')->limit(2)->pluck('id')->all();

        $toBranch = Branch::query()->where('school_id', $firstSchoolId)->orderBy('id')->first();

        if ($toBranch === null) {
            return;
        }

        $link = StudentGuardian::query()
            ->where('is_active', true)
            ->whereHas('student.enrollments', fn ($q) => $q
                ->where('school_id', $secondSchoolId)
                ->where('status', EnrollmentStatus::Active->value))
            ->with(['parentProfile.user', 'student'])
            ->first();

        $applicant = $link?->parentProfile?->user;

        if ($link === null || $applicant === null) {
            return;
        }

        $enrollment = StudentEnrollment::query()
            ->where('student_id', $link->student_id)
            ->where('school_id', $secondSchoolId)
            ->where('status', EnrollmentStatus::Active->value)
            ->orderBy('id')
            ->first();

        if ($enrollment === null) {
            return;
        }

        TransferApplication::create([
            'student_id' => $link->student_id,
            'applicant_user_id' => $applicant->id,
            'applicant_parent_id' => $link->parent_id,
            'from_enrollment_id' => $enrollment->id,
            'from_school_id' => $enrollment->school_id,
            'from_branch_id' => $enrollment->branch_id,
            'to_school_id' => $toBranch->school_id,
            'to_branch_id' => $toBranch->id,
            'status' => 'submitted',
            'reason' => 'We are moving close to this school next month.',
        ]);
    }

    private function printSummary(): void
    {
        $this->command?->newLine();
        $this->command?->info('Demo world ready — everyone signs in with PIN "'.self::PASSWORD_PLAIN.'"');
        $this->command?->table(['Who', 'Phone'], array_map(
            fn (array $login) => [$login['label'], $login['phone']],
            $this->sampleLogins,
        ));
        $this->command?->table(['What', 'Count'], [
            ['Schools', School::count()],
            ['Branches', Branch::count()],
            ['Academic years', AcademicYear::count()],
            ['Staff (employees)', Employee::count()],
            ['Students', Student::count()],
            ['Enrollments (pending)', StudentEnrollment::where('status', 'pending')->count()],
            ['Parents', ParentProfile::count()],
            ['Payment accounts', BankAccount::count()],
            ['Invoices', Invoice::count()],
            ['Invoices (overdue)', Invoice::whereIn('status', ['unpaid', 'partial'])->whereDate('due_date', '<', now())->count()],
            ['Payments', Payment::count()],
            ['Concessions', FeeConcession::count()],
            ['Concessions (pending review)', FeeConcession::where('status', 'pending')->count()],
            ['Concession-stamped invoices', Invoice::whereNotNull('fee_concession_id')->count()],
            ['Report-card rows', StudentTermResult::count()],
            ['Timetable slots', TimetableSlot::count()],
            ['Question banks', QuestionBank::count()],
            ['Exams/quizzes', Quiz::count()],
            ['Exam attempts', DB::table('quiz_attempts')->count()],
            ['Assignments', Assignment::count()],
            ['Assignment submissions', DB::table('assignment_submissions')->count()],
            ['Courses', Course::count()],
            ['Course materials', CourseMaterial::count()],
            ['Notifications (sample feeds)', Notification::count()],
        ]);
    }
}
