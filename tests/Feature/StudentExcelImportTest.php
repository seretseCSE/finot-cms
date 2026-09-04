<?php

namespace Tests\Feature;

use App\Exports\StudentImportTemplateExport;
use App\Imports\HeadingRowSpreadsheet;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Services\Members\StudentExcelImporter;
use App\Services\PhoneFormattingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudentExcelImportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function template_has_expected_headings(): void
    {
        Storage::disk('local')->makeDirectory('imports');
        Excel::store(new StudentImportTemplateExport(), 'imports/student-template.xlsx', 'local');

        $path = Storage::disk('local')->path('imports/student-template.xlsx');
        $spreadsheet = IOFactory::load($path);
        $headings = $spreadsheet->getSheet(0)->rangeToArray('A1:Q1')[0];

        $this->assertSame(array_values(StudentExcelImporter::columns()), $headings);
        $this->assertSame('Instructions', $spreadsheet->getSheet(1)->getTitle());
    }

    #[Test]
    public function import_creates_students_from_excel(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $path = $this->storeSpreadsheet([
            ['Abebe', 'Kebede', 'Tesfaye', 'Almaz', 'Male', 'Kids', '2015-03-20', '911111111', '', 'Addis Ababa', 'Bole', '03', '', '', '', '', 'Active'],
            ['Marta', 'Hailu', '', '', 'Female', 'Youth', '2008-01-15', '911111112', 'marta@example.com', '', '', '', '', '', '', '', ''],
        ]);

        $result = app(StudentExcelImporter::class)->import($path, [
            'user_id' => $user->id,
            'department_id' => $user->department_id,
        ]);

        $this->assertSame(2, $result->imported);
        $this->assertSame(0, $result->failed);
        $this->assertDatabaseHas('members', [
            'first_name' => 'Abebe',
            'father_name' => 'Kebede',
            'phone' => PhoneFormattingService::normalizeForAuth('911111111'),
            'occupation_status' => 'Student',
            'status' => 'Active',
        ]);
        $this->assertDatabaseHas('members', [
            'first_name' => 'Marta',
            'email' => 'marta@example.com',
            'member_type' => 'Youth',
        ]);
    }

    #[Test]
    public function import_skips_duplicate_phone_and_reports_missing_fields(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        Member::factory()->create([
            'phone' => PhoneFormattingService::normalizeForAuth('911111113'),
        ]);

        $path = $this->storeSpreadsheet([
            ['Abebe', 'Kebede', '', '', 'Male', 'Kids', '2015-03-20', '911111113', '', '', '', '', '', '', '', '', ''],
            ['', 'Kebede', '', '', 'Male', 'Kids', '2015-03-20', '911111114', '', '', '', '', '', '', '', '', ''],
        ]);

        $result = app(StudentExcelImporter::class)->import($path, [
            'user_id' => $user->id,
        ]);

        $this->assertSame(0, $result->imported);
        $this->assertSame(2, $result->failed);
        $this->assertTrue(collect($result->errors)->contains(fn (string $error) => str_contains($error, 'already belongs')));
        $this->assertTrue(collect($result->errors)->contains(fn (string $error) => str_contains($error, 'First Name')));
        $this->assertSame(1, Member::query()->where('phone', PhoneFormattingService::normalizeForAuth('911111113'))->count());
    }

    #[Test]
    public function import_assigns_group(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $group = MemberGroup::factory()->create([
            'name' => 'Sunday Kids',
            'created_by' => $user->id,
        ]);

        $path = $this->storeSpreadsheet([
            ['Abebe', 'Kebede', '', '', 'Male', 'Kids', '2015-03-20', '911111115', '', '', '', '', '', '', '', 'Sunday Kids', 'Active'],
        ]);

        $result = app(StudentExcelImporter::class)->import($path, [
            'user_id' => $user->id,
            'department_id' => $user->department_id,
        ]);

        $this->assertSame(1, $result->imported, implode("\n", [...$result->errors, ...$result->warnings]));
        $this->assertSame(0, $result->failed);

        $member = Member::query()->where('phone', PhoneFormattingService::normalizeForAuth('911111115'))->first();
        $this->assertNotNull($member);
        $this->assertSame($group->id, $member->currentGroup?->id);
    }

    #[Test]
    public function import_uses_default_group_from_options(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $group = MemberGroup::factory()->create([
            'name' => 'Youth Choir',
            'created_by' => $user->id,
        ]);

        $path = $this->storeSpreadsheet([
            ['Sara', 'Mengistu', '', '', 'Female', 'Youth', '2009-06-01', '911111116', '', '', '', '', '', '', '', '', ''],
        ]);

        $result = app(StudentExcelImporter::class)->import($path, [
            'user_id' => $user->id,
            'group_id' => $group->id,
        ]);

        $this->assertSame(1, $result->imported, implode("\n", [...$result->errors, ...$result->warnings]));

        $member = Member::query()->where('first_name', 'Sara')->first();
        $this->assertSame($group->id, $member->currentGroup?->id);
    }

    #[Test]
    public function members_page_is_accessible_for_import_actions(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $this->get('/admin/members')->assertOk();
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function storeSpreadsheet(array $rows): string
    {
        $export = new class (array_values(StudentExcelImporter::columns()), $rows) implements FromArray, WithHeadings {
            public function __construct(private array $headingRow, private array $dataRows)
            {
            }

            public function headings(): array
            {
                return $this->headingRow;
            }

            public function array(): array
            {
                return $this->dataRows;
            }
        };

        Storage::disk('local')->makeDirectory('imports');
        Excel::store($export, 'imports/students.xlsx', 'local');

        $path = Storage::disk('local')->path('imports/students.xlsx');
        $this->assertFileExists($path);

        $imported = Excel::toArray(new HeadingRowSpreadsheet(), $path);
        $this->assertNotEmpty($imported[0] ?? []);

        return $path;
    }
}
