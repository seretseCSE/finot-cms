<?php

namespace App\Services\Members;

use App\Enums\Gender;
use App\Enums\MemberStatus;
use App\Enums\MemberType;
use App\Enums\OccupationStatus;
use App\Imports\HeadingRowSpreadsheet;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Scopes\DepartmentScope;
use App\Services\PhoneFormattingService;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class StudentExcelImporter
{
    /**
     * Template column key => header label.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        return [
            'first_name' => 'First Name',
            'father_name' => 'Father Name',
            'grandfather_name' => 'Grandfather Name',
            'mother_name' => 'Mother Name',
            'gender' => 'Gender',
            'member_type' => 'Member Type',
            'date_of_birth' => 'Date of Birth',
            'phone' => 'Phone',
            'email' => 'Email',
            'city' => 'City',
            'sub_city' => 'Sub City',
            'woreda' => 'Woreda',
            'christian_name' => 'Christian Name',
            'emergency_contact_name' => 'Emergency Contact Name',
            'emergency_contact_phone' => 'Emergency Contact Phone',
            'group' => 'Group',
            'status' => 'Status',
        ];
    }

    /**
     * @return array<string, array{required: bool, help: string}>
     */
    public static function columnGuide(): array
    {
        return [
            'First Name' => ['required' => true, 'help' => 'Student given name'],
            'Father Name' => ['required' => true, 'help' => "Father's name"],
            'Grandfather Name' => ['required' => false, 'help' => "Grandfather's name"],
            'Mother Name' => ['required' => false, 'help' => "Mother's name"],
            'Gender' => ['required' => true, 'help' => 'Male or Female'],
            'Member Type' => ['required' => true, 'help' => 'Kids, Youth, or Adult'],
            'Date of Birth' => ['required' => true, 'help' => 'Gregorian date, YYYY-MM-DD'],
            'Phone' => ['required' => true, 'help' => '9 digits after +251 (e.g. 911234567). Use a parent phone if needed.'],
            'Email' => ['required' => false, 'help' => 'Optional email address'],
            'City' => ['required' => false, 'help' => 'City of residence'],
            'Sub City' => ['required' => false, 'help' => 'Sub-city of residence'],
            'Woreda' => ['required' => false, 'help' => 'Woreda / district'],
            'Christian Name' => ['required' => false, 'help' => 'Baptism name'],
            'Emergency Contact Name' => ['required' => false, 'help' => 'Emergency contact person'],
            'Emergency Contact Phone' => ['required' => false, 'help' => '9 digits after +251'],
            'Group' => ['required' => false, 'help' => 'Existing member group name'],
            'Status' => ['required' => false, 'help' => 'Draft, Member, Active, or Former. Defaults to Active.'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function exampleRow(): array
    {
        return [
            'Abebe',
            'Kebede',
            'Tesfaye',
            'Almaz',
            'Male',
            'Kids',
            '2015-03-20',
            '911234567',
            '',
            'Addis Ababa',
            'Bole',
            '03',
            '',
            '',
            '',
            '',
            'Active',
        ];
    }

    /**
     * @param  array{user_id?: int|null, department_id?: int|null, group_id?: int|null}  $options
     */
    public function import(string $path, array $options = []): StudentExcelImportResult
    {
        $result = new StudentExcelImportResult();
        $sheets = Excel::toArray(new HeadingRowSpreadsheet(), $path);
        $rows = $sheets[0] ?? [];

        $seenPhones = [];
        $userId = $options['user_id'] ?? auth()->id();
        $departmentId = $options['department_id'] ?? auth()->user()?->department_id;
        $defaultGroupId = $options['group_id'] ?? null;

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $row = $this->normalizeRow(is_array($row) ? $row : []);

            if ($this->isEmptyRow($row)) {
                continue;
            }

            try {
                DB::transaction(function () use ($row, $rowNumber, &$result, &$seenPhones, $userId, $departmentId, $defaultGroupId): void {
                    $this->importRow(
                        $row,
                        $rowNumber,
                        $result,
                        $seenPhones,
                        $userId,
                        $departmentId,
                        $defaultGroupId ? (int) $defaultGroupId : null,
                    );
                });
            } catch (Throwable $e) {
                $result->failed++;
                $result->errors[] = "Row {$rowNumber}: {$e->getMessage()}";
            }
        }

        return $result;
    }

    public function importUploadedFile(mixed $file, array $options = []): StudentExcelImportResult
    {
        return $this->import($this->resolvePath($file), $options);
    }

    public function resolvePath(mixed $file): string
    {
        if (is_array($file)) {
            $file = $file[0] ?? null;
        }

        if ($file instanceof TemporaryUploadedFile) {
            $path = $file->getRealPath();

            if ($path && is_file($path)) {
                return $path;
            }
        }

        if (is_string($file) && is_file($file)) {
            return $file;
        }

        if (is_string($file)) {
            foreach (['local', 'public'] as $disk) {
                if (Storage::disk($disk)->exists($file)) {
                    return Storage::disk($disk)->path($file);
                }
            }
        }

        throw new \InvalidArgumentException('Could not read the uploaded spreadsheet.');
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, true>  $seenPhones
     */
    private function importRow(
        array $row,
        int $rowNumber,
        StudentExcelImportResult $result,
        array &$seenPhones,
        mixed $userId,
        mixed $departmentId,
        ?int $defaultGroupId,
    ): void {
        $firstName = $this->cellString($row['first_name'] ?? null);
        $fatherName = $this->cellString($row['father_name'] ?? null);
        $gender = $this->parseGender($this->cellString($row['gender'] ?? null));
        $memberType = $this->parseMemberType($this->cellString($row['member_type'] ?? null));
        $dateOfBirth = $this->parseDate($row['date_of_birth'] ?? null);
        $phone = $this->parsePhone($this->cellString($row['phone'] ?? null));

        $missing = [];
        if (! $firstName) {
            $missing[] = 'First Name';
        }
        if (! $fatherName) {
            $missing[] = 'Father Name';
        }
        if (! $gender) {
            $missing[] = 'Gender (Male or Female)';
        }
        if (! $memberType) {
            $missing[] = 'Member Type (Kids, Youth, or Adult)';
        }
        if (! $dateOfBirth) {
            $missing[] = 'Date of Birth (YYYY-MM-DD)';
        }
        if (! $phone) {
            $missing[] = 'Phone (9 digits)';
        }

        if ($missing !== []) {
            throw new \RuntimeException('Missing or invalid: '.implode(', ', $missing));
        }

        if (isset($seenPhones[$phone])) {
            throw new \RuntimeException("Duplicate phone {$phone} in this file.");
        }

        $existing = Member::query()
            ->withoutGlobalScope(DepartmentScope::class)
            ->withTrashed()
            ->where('phone', $phone)
            ->exists();

        if ($existing) {
            throw new \RuntimeException("Phone {$phone} already belongs to an existing member.");
        }

        $seenPhones[$phone] = true;

        $status = $this->parseStatus($this->cellString($row['status'] ?? null)) ?? MemberStatus::ACTIVE;

        $member = Member::query()->create([
            'first_name' => $firstName,
            'father_name' => $fatherName,
            'grandfather_name' => $this->cellString($row['grandfather_name'] ?? null) ?? '',
            'mother_name' => $this->cellString($row['mother_name'] ?? null) ?? '',
            'gender' => $gender,
            'member_type' => $memberType,
            'date_of_birth' => $dateOfBirth->toDateString(),
            'phone' => $phone,
            'email' => $this->cellString($row['email'] ?? null),
            'city' => $this->cellString($row['city'] ?? null) ?? '',
            'sub_city' => $this->cellString($row['sub_city'] ?? null) ?? '',
            'woreda' => $this->cellString($row['woreda'] ?? null) ?? '',
            'christian_name' => $this->cellString($row['christian_name'] ?? null),
            'emergency_contact_name' => $this->cellString($row['emergency_contact_name'] ?? null) ?? '',
            'emergency_contact_phone' => $this->parsePhone($this->cellString($row['emergency_contact_phone'] ?? null)) ?? '',
            'status' => $status,
            'occupation_status' => OccupationStatus::STUDENT,
            'department_id' => $departmentId,
            'member_since' => now()->toDateString(),
        ]);

        $groupName = $this->cellString($row['group'] ?? null);
        $group = $groupName
            ? $this->findGroup($groupName)
            : ($defaultGroupId ? MemberGroup::query()->find($defaultGroupId) : null);

        if ($groupName && ! $group) {
            $result->warnings[] = "Row {$rowNumber}: member created but group \"{$groupName}\" was not found.";
        } elseif ($group) {
            try {
                $group->assignMember($member->id, now()->toDateString(), $userId);
            } catch (Throwable $e) {
                $result->warnings[] = "Row {$rowNumber}: member created but group assignment failed ({$e->getMessage()}).";
            }
        }

        $result->imported++;
    }

    private function findGroup(string $name): ?MemberGroup
    {
        return MemberGroup::query()
            ->active()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $key = Str::slug((string) $key, '_');
            $key = match ($key) {
                'firstname', 'first' => 'first_name',
                'fathers_name', 'father' => 'father_name',
                'grandfathers_name', 'grandfather' => 'grandfather_name',
                'mothers_name', 'mother' => 'mother_name',
                'sex' => 'gender',
                'type' => 'member_type',
                'dob', 'birth_date', 'birthdate' => 'date_of_birth',
                'mobile', 'telephone', 'phone_number' => 'phone',
                'group_name' => 'group',
                'baptism_name' => 'christian_name',
                default => $key,
            };
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->cellString($value) !== null) {
                return false;
            }
        }

        return true;
    }

    private function parseGender(?string $value): ?Gender
    {
        return match (Str::lower((string) $value)) {
            'male', 'm' => Gender::MALE,
            'female', 'f' => Gender::FEMALE,
            default => null,
        };
    }

    private function parseMemberType(?string $value): ?MemberType
    {
        return match (Str::lower((string) $value)) {
            'kids', 'kid', 'child', 'children' => MemberType::KIDS,
            'youth' => MemberType::YOUTH,
            'adult', 'adults' => MemberType::ADULT,
            default => MemberType::tryFrom((string) $value),
        };
    }

    private function parseStatus(?string $value): ?MemberStatus
    {
        if ($value === null) {
            return null;
        }

        return MemberStatus::tryFrom($value)
            ?? match (Str::lower($value)) {
                'draft' => MemberStatus::DRAFT,
                'member' => MemberStatus::MEMBER,
                'active' => MemberStatus::ACTIVE,
                'former' => MemberStatus::FORMER,
                default => null,
            };
    }

    private function parsePhone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = PhoneFormattingService::normalizeForAuth($value);
        $national = PhoneFormattingService::nationalDigits($normalized);

        if (! $national || strlen($national) !== 9) {
            return null;
        }

        return $normalized;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance(\DateTime::createFromInterface($value));
        }

        if (is_numeric($value) && (float) $value > 20000) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
            } catch (Throwable) {
                // Fall through to string parsing.
            }
        }

        try {
            return Carbon::parse(trim((string) $value));
        } catch (Throwable) {
            return null;
        }
    }

    private function cellString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value) && ! is_string($value)) {
            if ((float) $value == (int) $value) {
                return (string) (int) $value;
            }

            return (string) $value;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
