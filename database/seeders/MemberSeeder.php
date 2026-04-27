<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\ParentModel;
use App\Models\MemberParentGuardian;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MemberSeeder extends Seeder
{
    /**
     * Parent data - all phones must be unique.
     */
    protected array $parentData = [
        ['full_name' => 'Abebe Kebede',        'phone' => '+251911234561', 'relationship_type' => 'Father', 'children_count' => 3],
        ['full_name' => 'Tigist Bekele',       'phone' => '+251911234562', 'relationship_type' => 'Mother', 'children_count' => 2],
        ['full_name' => 'Mekonnen Haile',      'phone' => '+251911234563', 'relationship_type' => 'Father', 'children_count' => 4],
        ['full_name' => 'Almaz Tadesse',       'phone' => '+251911234564', 'relationship_type' => 'Mother', 'children_count' => 3],
        ['full_name' => 'Dawit Solomon',       'phone' => '+251911234565', 'relationship_type' => 'Father', 'children_count' => 2],
        ['full_name' => 'Wubalem Haile',       'phone' => '+251911234566', 'relationship_type' => 'Mother', 'children_count' => 4],
        ['full_name' => 'Sara Tesfaye',        'phone' => '+251911234567', 'relationship_type' => 'Mother', 'children_count' => 1],
        ['full_name' => 'Tesfaye Mekonnen',    'phone' => '+251911234568', 'relationship_type' => 'Father', 'children_count' => 1],
        ['full_name' => 'Kassa Wondimu',       'phone' => '+251911234569', 'relationship_type' => 'Father', 'children_count' => 3],
        ['full_name' => 'Sara Ahmed',          'phone' => '+251911234570', 'relationship_type' => 'Mother', 'children_count' => 1],
        ['full_name' => 'Mohammed Ali',        'phone' => '+251911234571', 'relationship_type' => 'Father', 'children_count' => 1],
        ['full_name' => 'Fatima Omar',         'phone' => '+251911234572', 'relationship_type' => 'Mother', 'children_count' => 1],
        ['full_name' => 'Yonas Solomon',       'phone' => '+251911234573', 'relationship_type' => 'Father', 'children_count' => 1],
        ['full_name' => 'Hana Gebre',          'phone' => '+251911234574', 'relationship_type' => 'Mother', 'children_count' => 1],
        ['full_name' => 'Berhanu Tesfaye',     'phone' => '+251911234575', 'relationship_type' => 'Father', 'children_count' => 2],
        ['full_name' => 'Zenebuwq Alemu',      'phone' => '+251911234576', 'relationship_type' => 'Mother', 'children_count' => 3],
        ['full_name' => 'Getachew Mekuria',    'phone' => '+251911234577', 'relationship_type' => 'Father', 'children_count' => 2],
        ['full_name' => 'Girma Bekele',        'phone' => '+251911234578', 'relationship_type' => 'Father', 'children_count' => 1],
        ['full_name' => 'Wubalem Girma',       'phone' => '+251911234579', 'relationship_type' => 'Mother', 'children_count' => 1],
    ];

    /**
     * Detailed member data with parent phone references.
     * Update: phones now match the corrected parent phones.
     */
    protected array $detailedMembers = [
        // === Abebe Kebede's children (+251911234561) ===
        [
            'member_code' => 'M-000001', 'member_type' => 'Kids', 'status' => 'Active',
            'title' => 'Mr.', 'first_name' => 'Samuel', 'father_name' => 'Abebe',
            'grandfather_name' => 'Kebede', 'mother_name' => 'Tigist',
            'date_of_birth' => '2010-05-12', 'gender' => 'Male',
            'city' => 'Addis Ababa', 'sub_city' => 'Kolfe Keranio', 'woreda' => 'Kolfe',
            'phone' => '+251912000001', 'email' => 'samuel.abebe@example.com',
            'emergency_contact_name' => 'Abebe Kebede', 'emergency_contact_phone' => '+251911234561',
            'confession_father_name' => 'Father Zekarias', 'confession_father_phone' => '+251912000099',
            'christian_name' => 'Samuel', 'special_talents' => 'Singing, Drawing',
            'family_size' => 5, 'brothers_count' => 2, 'sisters_count' => 2,
            'sunday_school_entry_year' => '2018', 'occupation_status' => 'Student',
            'marital_status' => 'Single', 'department_id' => 3,
            'parents' => ['+251911234561'],
        ],
        [
            'member_code' => 'M-000002', 'member_type' => 'Kids', 'status' => 'Active',
            'title' => 'Miss', 'first_name' => 'Hanna', 'father_name' => 'Abebe',
            'grandfather_name' => 'Kebede', 'mother_name' => 'Tigist',
            'date_of_birth' => '2012-08-25', 'gender' => 'Female',
            'city' => 'Addis Ababa', 'sub_city' => 'Kolfe Keranio', 'woreda' => 'Kolfe',
            'phone' => '+251912000002', 'email' => 'hanna.abebe@example.com',
            'emergency_contact_name' => 'Abebe Kebede', 'emergency_contact_phone' => '+251911234561',
            'confession_father_name' => 'Father Zekarias', 'confession_father_phone' => '+251912000099',
            'christian_name' => 'Hanna', 'special_talents' => 'Dancing, Reading',
            'family_size' => 5, 'brothers_count' => 1, 'sisters_count' => 3,
            'sunday_school_entry_year' => '2019', 'occupation_status' => 'Student',
            'marital_status' => 'Single', 'department_id' => 3,
            'parents' => ['+251911234561'],
        ],
        [
            'member_code' => 'M-000003', 'member_type' => 'Kids', 'status' => 'Active',
            'title' => 'Master', 'first_name' => 'Daniel', 'father_name' => 'Abebe',
            'grandfather_name' => 'Kebede', 'mother_name' => 'Tigist',
            'date_of_birth' => '2015-02-18', 'gender' => 'Male',
            'city' => 'Addis Ababa', 'sub_city' => 'Kolfe Keranio', 'woreda' => 'Kolfe',
            'phone' => '+251912000003', 'email' => 'daniel.abebe@example.com',
            'emergency_contact_name' => 'Abebe Kebede', 'emergency_contact_phone' => '+251911234561',
            'confession_father_name' => 'Father Zekarias', 'confession_father_phone' => '+251912000099',
            'christian_name' => 'Daniel', 'special_talents' => 'Sports, Music',
            'family_size' => 5, 'brothers_count' => 2, 'sisters_count' => 2,
            'sunday_school_entry_year' => '2020', 'occupation_status' => 'Student',
            'marital_status' => 'Single', 'department_id' => 3,
            'parents' => ['+251911234561'],
        ],

        // === Tigist Bekele's children (+251911234562) ===
        [
            'member_code' => 'M-000004', 'member_type' => 'Kids', 'status' => 'Active',
            'title' => 'Miss', 'first_name' => 'Ruth', 'father_name' => 'Bekele',
            'grandfather_name' => 'Tadesse', 'mother_name' => 'Tigist',
            'date_of_birth' => '2011-07-30', 'gender' => 'Female',
            'city' => 'Addis Ababa', 'sub_city' => 'Bole', 'woreda' => 'Bole',
            'phone' => '+251912000004', 'email' => 'ruth.bekele@example.com',
            'emergency_contact_name' => 'Tigist Bekele', 'emergency_contact_phone' => '+251911234562',
            'confession_father_name' => 'Father Michael', 'confession_father_phone' => '+251912000098',
            'christian_name' => 'Ruth', 'special_talents' => 'Singing, Teaching',
            'family_size' => 4, 'brothers_count' => 1, 'sisters_count' => 2,
            'sunday_school_entry_year' => '2017', 'occupation_status' => 'Student',
            'marital_status' => 'Single', 'department_id' => 5,
            'parents' => ['+251911234562'],
        ],
        [
            'member_code' => 'M-000005', 'member_type' => 'Kids', 'status' => 'Active',
            'title' => 'Master', 'first_name' => 'David', 'father_name' => 'Bekele',
            'grandfather_name' => 'Tadesse', 'mother_name' => 'Tigist',
            'date_of_birth' => '2014-04-22', 'gender' => 'Male',
            'city' => 'Addis Ababa', 'sub_city' => 'Bole', 'woreda' => 'Bole',
            'phone' => '+251912000005', 'email' => 'david.bekele@example.com',
            'emergency_contact_name' => 'Tigist Bekele', 'emergency_contact_phone' => '+251911234562',
            'confession_father_name' => 'Father Michael', 'confession_father_phone' => '+251912000098',
            'christian_name' => 'David', 'special_talents' => 'Football, Drawing',
            'family_size' => 4, 'brothers_count' => 2, 'sisters_count' => 1,
            'sunday_school_entry_year' => '2019', 'occupation_status' => 'Student',
            'marital_status' => 'Single', 'department_id' => 5,
            'parents' => ['+251911234562'],
        ],

        // === Mekonnen Haile (±251911234563) and Wubalem Haile (+251911234566) ===
        [
            'member_code' => 'M-000006', 'member_type' => 'Youth', 'status' => 'Active',
            'title' => 'Mr.', 'first_name' => 'Michael', 'father_name' => 'Mekonnen',
            'grandfather_name' => 'Haile', 'mother_name' => 'Almaz',
            'date_of_birth' => '2008-12-10', 'gender' => 'Male',
            'city' => 'Addis Ababa', 'sub_city' => 'Kirkos', 'woreda' => 'Kirkos',
            'phone' => '+251912000006', 'email' => 'michael.haile@example.com',
            'emergency_contact_name' => 'Mekonnen Haile', 'emergency_contact_phone' => '+251911234563',
            'confession_father_name' => 'Father Thomas', 'confession_father_phone' => '+251912000097',
            'christian_name' => 'Michael', 'special_talents' => 'Basketball, Reading',
            'family_size' => 6, 'brothers_count' => 3, 'sisters_count' => 2,
            'sunday_school_entry_year' => '2015', 'occupation_status' => 'Student',
            'marital_status' => 'Single', 'department_id' => 4,
            'parents' => ['+251911234563', '+251911234566'],
        ],
        [
            'member_code' => 'M-000007', 'member_type' => 'Kids', 'status' => 'Active',
            'title' => 'Miss', 'first_name' => 'Sarah', 'father_name' => 'Mekonnen',
            'grandfather_name' => 'Haile', 'mother_name' => 'Almaz',
            'date_of_birth' => '2010-09-15', 'gender' => 'Female',
            'city' => 'Addis Ababa', 'sub_city' => 'Kirkos', 'woreda' => 'Kirkos',
            'phone' => '+251912000007', 'email' => 'sarah.haile@example.com',
            'emergency_contact_name' => 'Mekonnen Haile', 'emergency_contact_phone' => '+251911234563',
            'confession_father_name' => 'Father Thomas', 'confession_father_phone' => '+251912000097',
            'christian_name' => 'Sarah', 'special_talents' => 'Music, Art',
            'family_size' => 6, 'brothers_count' => 2, 'sisters_count' => 3,
            'sunday_school_entry_year' => '2016', 'occupation_status' => 'Student',
            'marital_status' => 'Single', 'department_id' => 4,
            'parents' => ['+251911234563', '+251911234566'],
        ],
        [
            'member_code' => 'M-000008', 'member_type' => 'Kids', 'status' => 'Active',
            'title' => 'Master', 'first_name' => 'Solomon', 'father_name' => 'Mekonnen',
            'grandfather_name' => 'Haile', 'mother_name' => 'Almaz',
            'date_of_birth' => '2013-03-08', 'gender' => 'Male',
            'city' => 'Addis Ababa', 'sub_city' => 'Kirkos', 'woreda' => 'Kirkos',
            'phone' => '+251912000008', 'email' => 'solomon.haile@example.com',
            'emergency_contact_name' => 'Mekonnen Haile', 'emergency_contact_phone' => '+251911234563',
            'confession_father_name' => 'Father Thomas', 'confession_father_phone' => '+251912000097',
            'christian_name' => 'Solomon', 'special_talents' => 'Soccer, Swimming',
            'family_size' => 6, 'brothers_count' => 2, 'sisters_count' => 3,
            'sunday_school_entry_year' => '2018', 'occupation_status' => 'Student',
            'marital_status' => 'Single', 'department_id' => 4,
            'parents' => ['+251911234563', '+251911234566'],
        ],
        [
            'member_code' => 'M-000009', 'member_type' => 'Kids', 'status' => 'Active',
            'title' => 'Miss', 'first_name' => 'Martha', 'father_name' => 'Mekonnen',
            'grandfather_name' => 'Haile', 'mother_name' => 'Almaz',
            'date_of_birth' => '2016-11-20', 'gender' => 'Female',
            'city' => 'Addis Ababa', 'sub_city' => 'Kirkos', 'woreda' => 'Kirkos',
            'phone' => '+251912000009', 'email' => 'martha.haile@example.com',
            'emergency_contact_name' => 'Mekonnen Haile', 'emergency_contact_phone' => '+251911234563',
            'confession_father_name' => 'Father Thomas', 'confession_father_phone' => '+251912000097',
            'christian_name' => 'Martha', 'special_talents' => 'Dancing, Storytelling',
            'family_size' => 6, 'brothers_count' => 1, 'sisters_count' => 4,
            'sunday_school_entry_year' => '2020', 'occupation_status' => 'Student',
            'marital_status' => 'Single', 'department_id' => 4,
            'parents' => ['+251911234563', '+251911234566'],
        ],

        // === Adult members (no parents) ===
        [
            'member_code' => 'M-000010', 'member_type' => 'Adult', 'status' => 'Active',
            'title' => 'Dr.', 'first_name' => 'Abraham', 'father_name' => 'Tesfaye',
            'grandfather_name' => 'Mekonnen', 'mother_name' => 'Sara',
            'date_of_birth' => '1985-02-15', 'gender' => 'Male',
            'city' => 'Addis Ababa', 'sub_city' => 'Mekanisa', 'woreda' => 'Mekanisa',
            'phone' => '+251912000010', 'email' => 'abraham.tesfaye@example.com',
            'emergency_contact_name' => 'Tesfaye Mekonnen', 'emergency_contact_phone' => '+251911234568',
            'confession_father_name' => 'Father Yohannes', 'confession_father_phone' => '+251912000096',
            'christian_name' => 'Abraham', 'special_talents' => 'Teaching, Leadership',
            'family_size' => 4, 'brothers_count' => 2, 'sisters_count' => 1,
            'sunday_school_entry_year' => '1995', 'occupation_status' => 'Employee',
            'marital_status' => 'Married', 'spouse_name' => 'Miriam',
            'spouse_phone' => '+251912000011', 'children_count' => 2,
            'department_id' => 3, 'parents' => [],
        ],
        [
            'member_code' => 'M-000011', 'member_type' => 'Adult', 'status' => 'Active',
            'title' => 'Mrs.', 'first_name' => 'Miriam', 'father_name' => 'Girma',
            'grandfather_name' => 'Bekele', 'mother_name' => 'Wubalem',
            'date_of_birth' => '1988-11-28', 'gender' => 'Female',
            'city' => 'Addis Ababa', 'sub_city' => 'Mekanisa', 'woreda' => 'Mekanisa',
            'phone' => '+251912000011', 'email' => 'miriam.girma@example.com',
            'emergency_contact_name' => 'Girma Bekele', 'emergency_contact_phone' => '+251911234578',
            'confession_father_name' => 'Father Petros', 'confession_father_phone' => '+251912000095',
            'christian_name' => 'Miriam', 'special_talents' => 'Music, Counseling',
            'family_size' => 3, 'brothers_count' => 1, 'sisters_count' => 2,
            'sunday_school_entry_year' => '1998', 'occupation_status' => 'Employee',
            'marital_status' => 'Married', 'spouse_name' => 'Abraham',
            'spouse_phone' => '+251912000010', 'children_count' => 2,
            'department_id' => 5, 'parents' => [],
        ],
    ];

    protected array $firstNames = [
        'Kidus', 'Hanna', 'Samuel', 'Ruth', 'Daniel', 'Martha', 'David', 'Esther', 'Michael', 'Sarah',
        'Paul', 'Rachel', 'John', 'Miriam', 'Mark', 'Naomi', 'Luke', 'Rebecca', 'Thomas', 'Leah',
        'Matthew', 'Deborah', 'Joshua', 'Abigail', 'Isaac', 'Jacob', 'Zipporah', 'Aaron',
        'Caleb', 'Elizabeth', 'Peter', 'Mary', 'Joseph', 'Anna', 'Benjamin', 'Judith',
        'Ephraim', 'Manasseh', 'Gideon', 'Delilah', 'Samson', 'Jonathan', 'Abner', 'Solomon',
        'Mekdes', 'Bethel', 'Natan', 'Tinsae', 'Yared', 'Abel', 'Adam',
    ];

    protected array $lastNames = [
        'Tesfaye', 'Bekele', 'Mekonnen', 'Haile', 'Kebede', 'Wondimu', 'Gebre', 'Alemu', 'Solomon',
        'Ahmed', 'Mohammed', 'Omar', 'Ali', 'Fatima', 'Sara', 'Abebe', 'Tigist', 'Almaz', 'Dawit',
        'Kassa', 'Berhanu', 'Mekuria', 'Getachew', 'Zenebuwq', 'Habte', 'Girma', 'Demissie',
    ];

    public function run(): void
    {
        // Clear existing data
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('member_parent_guardians')->truncate();
        DB::table('parents')->truncate();
        DB::table('members')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Step 1: Create parents
        $createdParents = $this->createParents();

        // Step 2: Create detailed members
        $detailedMembers = $this->createDetailedMembers($createdParents);

        // Step 3: Generate additional members
        $generatedMembers = $this->generateAdditionalMembers($createdParents, 40);

        // Step 4: Update parent counts
        $this->updateParentCounts();

        $totalMembers = count($detailedMembers) + count($generatedMembers);
        $kidsWithParents = MemberParentGuardian::distinct('member_id')->count('member_id');

        $this->command->info('MemberSeeder completed!');
        $this->command->info("Parents: " . count($createdParents));
        $this->command->info("Members: {$totalMembers} (" . count($detailedMembers) . " detailed + " . count($generatedMembers) . " generated)");
        $this->command->info("Parent-child relationships: " . MemberParentGuardian::count());
        $this->command->info("Members linked to parents: {$kidsWithParents}");
    }

    private function createParents(): array
    {
        $parents = [];
        foreach ($this->parentData as $data) {
            $parent = ParentModel::create([
                'full_name' => $data['full_name'],
                'phone' => $data['phone'],
                'relationship_type' => $data['relationship_type'],
                'member_count' => 0,
                'is_active' => true,
                'notes' => 'Test parent created via seeder',
            ]);
            $parents[$data['phone']] = $parent;
        }
        return $parents;
    }

    private function createDetailedMembers(array $parents): array
    {
        $members = [];
        foreach ($this->detailedMembers as $index => $memberData) {
            $parentPhones = $memberData['parents'] ?? [];
            unset($memberData['parents']);

            $memberData = array_merge([
                'zone' => $memberData['sub_city'] ?? 'Addis Ababa',
                'block' => 'Block ' . chr(65 + ($index % 26)),
                'neighborhood' => 'Kebele ' . str_pad($index + 1, 2, '0', STR_PAD_LEFT),
                'employment_status' => 'N/A',
                'company_name' => 'N/A',
                'job_role' => 'N/A',
                'company_address' => 'N/A',
                'family_confession_father' => 'Family Confessor ' . ($index + 1),
                'past_service_departments' => 'Sunday School',
                'photo' => null,
                'consent_for_photography' => true,
                'monthly_contribution_amount' => rand(50, 500),
                'deleted_at' => null,
                'member_since' => $memberData['member_since'] ?? now()->subYears(rand(1, 8))->format('Y-m-d'),
            ], $memberData);

            $member = Member::create($memberData);
            $members[] = $member;

            if (in_array($member->member_type, ['Kids', 'Youth']) && !empty($parentPhones)) {
                $this->linkMemberToParents($member, $parentPhones, $parents);
            }
        }
        return $members;
    }

    private function generateAdditionalMembers(array $parents, int $count): array
    {
        $members = [];
        $parentPhones = array_keys($parents);
        $kidsCount = intval($count * 0.6);
        $currentParentIndex = 0;
        $kidIndex = 0;
        $startIndex = count($this->detailedMembers);

        for ($i = 0; $i < $count; $i++) {
            $firstName = $this->firstNames[array_rand($this->firstNames)];
            $lastName = $this->lastNames[array_rand($this->lastNames)];
            $isKid = $kidIndex < $kidsCount;
            $memberType = $isKid ? (rand(0, 1) ? 'Kids' : 'Youth') : 'Adult';
            $age = match($memberType) {
                'Kids' => rand(6, 12), 'Youth' => rand(13, 17), default => rand(20, 50)
            };
            $isFemale = rand(0, 1);

            $memberData = [
                'member_code' => 'M-' . str_pad($startIndex + $i + 1, 6, '0', STR_PAD_LEFT),
                'member_type' => $memberType,
                'status' => rand(0, 10) < 8 ? 'Active' : (rand(0, 1) ? 'Member' : 'Former'),
                'member_since' => now()->subYears(rand(1, 10))->format('Y-m-d'),
                'title' => $isFemale ? (rand(0, 1) ? 'Ms.' : 'Miss') : 'Mr.',
                'first_name' => $firstName,
                'father_name' => $this->firstNames[array_rand($this->firstNames)],
                'grandfather_name' => $this->lastNames[array_rand($this->lastNames)],
                'mother_name' => $this->firstNames[array_rand($this->firstNames)],
                'date_of_birth' => now()->subYears($age)->subDays(rand(0, 365))->format('Y-m-d'),
                'gender' => $isFemale ? 'Female' : 'Male',
                'christian_name' => $this->firstNames[array_rand($this->firstNames)],
                'city' => 'Addis Ababa',
                'sub_city' => ['Bole', 'Kirkos', 'Yeka', 'Lideta', 'Arada', 'Kolfe Keranio'][rand(0, 5)],
                'woreda' => 'Woreda ' . rand(1, 10),
                'zone' => ['Bole', 'Kirkos', 'Yeka', 'Arada', 'Lideta'][rand(0, 4)],
                'block' => 'Block ' . chr(65 + ($i % 26)),
                'neighborhood' => 'Kebele ' . str_pad($i + $startIndex + 1, 2, '0', STR_PAD_LEFT),
                'phone' => '+2519' . str_pad(rand(20000000, 99999999), 8, '0', STR_PAD_LEFT),
                'email' => strtolower($firstName . '.' . $lastName . rand(10, 99) . '@example.com'),
                'emergency_contact_name' => 'Emergency Contact ' . ($i + 1),
                'emergency_contact_phone' => '+2519' . str_pad(rand(20000000, 99999999), 8, '0', STR_PAD_LEFT),
                'confession_father_name' => 'Father ' . ['Michael', 'Gabriel', 'Thomas', 'Markos', 'Petros'][rand(0, 4)],
                'confession_father_phone' => '+2519' . str_pad(rand(20000000, 99999999), 8, '0', STR_PAD_LEFT),
                'spiritual_education_level' => ['Beginner', 'Intermediate', 'Advanced'][rand(0, 2)],
                'special_talents' => implode(', ', array_rand(array_flip(['Singing', 'Drawing', 'Sports', 'Music', 'Reading', 'Dancing', 'Teaching', 'Art']), 2)),
                'family_size' => rand(2, 8),
                'brothers_count' => rand(0, 3),
                'sisters_count' => rand(0, 3),
                'family_confession_father' => 'Family Confessor ' . ($i + 1),
                'sunday_school_entry_year' => now()->subYears(rand(1, 20))->format('Y'),
                'past_service_departments' => 'Sunday School',
                'occupation_status' => $memberType === 'Adult' && rand(0, 1) ? 'Employee' : 'Student',
                'employment_status' => 'N/A',
                'company_name' => 'N/A',
                'job_role' => 'N/A',
                'company_address' => 'N/A',
                'marital_status' => $memberType === 'Adult' ? (rand(0, 1) ? 'Married' : 'Single') : 'Single',
                'spouse_name' => null,
                'spouse_phone' => null,
                'children_count' => $memberType === 'Adult' ? rand(0, 4) : 0,
                'photo' => null,
                'consent_for_photography' => rand(0, 10) < 8,
                'department_id' => rand(1, 6),
                'monthly_contribution_amount' => rand(50, 500),
                'deleted_at' => null,
            ];

            $member = Member::create($memberData);
            $members[] = $member;

            if ($isKid && $kidIndex < $kidsCount && !empty($parentPhones)) {
                $parentPhone = $parentPhones[$currentParentIndex % count($parentPhones)];
                $this->linkMemberToParents($member, [$parentPhone], $parents);
                $currentParentIndex++;
                $kidIndex++;
            }
        }
        return $members;
    }

    private function linkMemberToParents(Member $member, array $parentPhones, array $parents): void
    {
        foreach ($parentPhones as $phone) {
            if (isset($parents[$phone])) {
                $parent = $parents[$phone];
                MemberParentGuardian::create([
                    'member_id' => $member->id,
                    'parent_id' => $parent->id,
                    'parent_name' => $parent->full_name,
                    'relationship' => $parent->relationship_type,
                    'phone' => $parent->phone,
                    'is_external' => false,
                ]);
            }
        }
    }

    private function updateParentCounts(): void
    {
        foreach (ParentModel::all() as $parent) {
            $parent->update([
                'member_count' => MemberParentGuardian::where('parent_id', $parent->id)->count(),
            ]);
        }
    }
}
