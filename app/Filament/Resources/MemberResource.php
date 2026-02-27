<?php

namespace App\Filament\Resources;

use App\Filament\Exports\MemberExporter;
use App\Filament\Resources\MemberResource\Pages;
use App\Filament\Forms\Components\CustomOptionSelect;
use App\Helpers\EthiopianDateHelper;
use App\Models\Department;
use App\Models\MemberGroupAssignment;
use App\Models\Member;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Cancel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    public static function getNavigationIcon(): ?string { return 'heroicon-o-users'; }

    public static function getNavigationGroup(): ?string { return 'Membership'; }

    public static function getNavigationLabel(): string { return 'Members / አባላት'; }

    public static function getModelLabel(): string { return 'Member'; }

    public static function getPluralModelLabel(): string { return 'Members'; }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Tab 1 - Personal Information
                Forms\Components\Tabs\Tab::make('Personal Information / የግል መረጃ')
                    ->schema([
                        Section::make()
                            ->schema([
                                \App\Filament\Forms\Components\CustomOptionSelect::makeWithOther('title', 'title', [
                                        'Dn.' => 'Dn. (ዲ.)',
                                        'Br.' => 'Br. (ወ.)',
                                        'Sr.' => 'Sr. (ሰ.)',
                                        'Mr.' => 'Mr. (አቶ)',
                                        'Mrs.' => 'Mrs. (ወ/ሮ)',
                                        'Ms.' => 'Ms. (ወ/ሪት)',
                                        'Dr.' => 'Dr.',
                                    ], true),

                                \App\Filament\Forms\Components\CustomOptionSelect::makeWithOther('member_type', 'member_type', [
                                        'Kids' => 'Kids',
                                        'Youth' => 'Youth',
                                        'Adult' => 'Adult',
                                    ], true)
                                    ->live(),

                                Forms\Components\TextInput::make('first_name')
                                    ->label('First Name / ስም')
                                    ->required()
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('father_name')
                                    ->label('Father\'s Name / የአባት ስም')
                                    ->required()
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('grandfather_name')
                                    ->label('Grandfather\'s Name / የአያት ስም')
                                    ->required()
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('mother_name')
                                    ->label('Mother\'s Name / የእናት ስም')
                                    ->required()
                                    ->maxLength(100),

                                Forms\Components\DatePicker::make('date_of_birth')
                                    ->label('Date of Birth / የትውልድ ቀን')
                                    ->required()
                                    ->maxDate(now()),

                                Forms\Components\Radio::make('gender')
                                    ->label('Gender / ፆታ')
                                    ->options([
                                        'Male' => 'Male',
                                        'Female' => 'Female',
                                    ])
                                    ->required(),

                                Forms\Components\TextInput::make('christian_name')
                                    ->label('Christian Name / የክርስትና ስም')
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('member_code')
                                    ->label('Member Code')
                                    ->disabled()
                                    ->dehydrated(false),

                                \App\Filament\Forms\Components\CustomOptionSelect::makeWithOther('status', 'member_status', [
                                        'Draft' => 'Draft',
                                        'Member' => 'Member',
                                        'Active' => 'Active',
                                        'Former' => 'Former',
                                    ])
                                    ->default('Draft'),

                                Forms\Components\FileUpload::make('photo')
                                    ->disk('members')
                                    ->image()
                                    ->acceptedFileTypes(['image/*'])
                                    ->maxSize(5120),

                                Forms\Components\Toggle::make('consent_for_photography')
                                    ->label('Parent/Guardian has given consent for photography'),
                            ])
                            ->columns(2),
                    ])
                    ->activeTab(0),

                // Tab 2 - Address & Contact
                Forms\Components\Tabs\Tab::make('Address & Contact')
                    ->schema([
                        Section::make('Residential Address / የመኖሪያ አድራሻ')
                            ->schema([
                                Forms\Components\TextInput::make('city')
                                    ->label('City / የመኖሪያ ከተማ')
                                    ->required()
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('sub_city')
                                    ->label('Sub-City / ክ/ከተማ')
                                    ->required()
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('woreda')
                                    ->label('Woreda / ወረዳ')
                                    ->required()
                                    ->maxLength(50),

                                Forms\Components\TextInput::make('zone')
                                    ->label('Zone/Keten / ቀጠና')
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('block')
                                    ->label('Block / ብሎክ')
                                    ->maxLength(50),

                                Forms\Components\TextInput::make('neighborhood')
                                    ->label('Neighborhood Specific Name / የሠፈር ልዩ ስም')
                                    ->maxLength(200),
                            ])
                            ->columns(2),

                        Section::make('Contact Information / የግንኙነት መረጃ')
                            ->schema([
                                Forms\Components\TextInput::make('phone')
                                    ->label('Personal Phone / ስልክ')
                                    ->required()
                                    ->regex('/^(\+251|0)?9\d{8}$/')
                                    ->unique(ignoreRecord: true)
                                    ->live(debounce: 500),

                                Forms\Components\TextInput::make('email')
                                    ->label('Email (Optional)')
                                    ->email()
                                    ->maxLength(191),
                            ])
                            ->columns(2),
                    ]),

                // Tab 3 - Emergency & Spiritual
                Forms\Components\Tabs\Tab::make('Emergency & Spiritual')
                    ->schema([
                        Section::make('Emergency Contact / የቅርብ ጓደኛ')
                            ->schema([
                                Forms\Components\TextInput::make('emergency_contact_name')
                                    ->label('Emergency Contact Name / የቅርብ ጓደኛ ስም')
                                    ->required()
                                    ->maxLength(200),

                                Forms\Components\TextInput::make('emergency_contact_phone')
                                    ->label('Emergency Contact Phone / የቅርብ ጓደኛ ስልክ')
                                    ->required()
                                    ->regex('/^(\+251|0)?9\d{8}$/'),
                            ])
                            ->columns(2),

                        Section::make('Spiritual Information / መንፈሳዊ መረጃ')
                            ->schema([
                                Forms\Components\TextInput::make('confession_father_name')
                                    ->label('Confession Father\'s Name / የንስሀ አባት ስም')
                                    ->maxLength(200),

                                Forms\Components\TextInput::make('confession_father_phone')
                                    ->label('Confession Father\'s Phone / የንሰሐ አባት ስልክ')
                                    ->regex('/^(\+251|0)?9\d{8}$/'),
                            ])
                            ->columns(2),
                    ]),

                // Tab 4 - Conditional based on member_type
                Forms\Components\Tabs\Tab::make('Additional Information')
                    ->schema([
                        // Kids: Parent/Guardian Information
                        Section::make('Parent/Guardian Information / የወላጅ/አሳዲጊ መረጃ')
                            ->schema([
                                Forms\Components\Repeater::make('parent_guardians')
                                    ->label('')
                                    ->schema([
                                        Forms\Components\Select::make('parent_id')
                                            ->label('Select Existing Parent')
                                            ->options(function () {
                                                return \App\Models\ParentModel::active()
                                                    ->orderBy('full_name')
                                                    ->pluck('full_name', 'id')
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                // Clear manual fields when parent is selected
                                                if ($state) {
                                                    $set('parent_name', null);
                                                    $set('phone', null);
                                                    $set('relationship', null);
                                                }
                                            }),

                                        Forms\Components\TextInput::make('parent_name')
                                            ->label('Parent/Guardian Name / ስም')
                                            ->required(fn (callable $get) => !$get('parent_id'))
                                            ->maxLength(200)
                                            ->disabled(fn (callable $get) => $get('parent_id')),

                                        Forms\Components\Select::make('relationship')
                                            ->label('Relationship / ግንኙነት')
                                            ->options([
                                                'Father' => 'Father',
                                                'Mother' => 'Mother',
                                                'Guardian' => 'Guardian',
                                                'GrandFather' => 'GrandFather',
                                                'GrandMother' => 'GrandMother',
                                                'Uncle' => 'Uncle',
                                                'Brother' => 'Brother',
                                                'Aunt' => 'Aunt',
                                                'Sister' => 'Sister',
                                                'Other' => 'Other',
                                            ])
                                            ->required(fn (callable $get) => !$get('parent_id'))
                                            ->disabled(fn (callable $get) => $get('parent_id'))
                                            ->live(),

                                        Forms\Components\TextInput::make('phone')
                                            ->label('Phone / ስልክ')
                                            ->required(fn (callable $get) => !$get('parent_id'))
                                            ->regex('/^(\+251|0)?9\d{8}$/')
                                            ->disabled(fn (callable $get) => $get('parent_id'))
                                            ->live(debounce: 500),
                                    ])
                                    ->columns(2)
                                    ->minItems(1)
                                    ->maxItems(10)
                                    ->addActionLabel('+ Add Parent/Guardian / ወላጅ/አሳዲጊ ጨምር')
                                    ->collapsible()
                                    ->mutateRelationshipDataBeforeCreate(function (array $data): array {
                                        // Handle parent linking/creation logic
                                        if (isset($data['parent_id']) && $data['parent_id']) {
                                            // Link to existing parent
                                            $parent = \App\Models\ParentModel::find($data['parent_id']);
                                            return [
                                                'parent_id' => $data['parent_id'],
                                                'parent_name' => $parent->full_name,
                                                'phone' => $parent->phone,
                                                'relationship' => $data['relationship'],
                                                'is_external' => false,
                                            ];
                                        } else {
                                            // Create/find parent by phone
                                            $parent = \App\Models\ParentModel::findOrCreateByPhone(
                                                $data['phone'],
                                                $data['parent_name'],
                                                $data['relationship']
                                            );
                                            return [
                                                'parent_id' => $parent->id,
                                                'parent_name' => $data['parent_name'],
                                                'phone' => $data['phone'],
                                                'relationship' => $data['relationship'],
                                                'is_external' => false,
                                            ];
                                        }
                                    }),
                            ])
                            ->visible(fn (callable $get) => $get('member_type') === 'Kids'),

                        Section::make('Additional Kids Information')
                            ->schema([
                                Forms\Components\Select::make('spiritual_education_level')
                                    ->label('Spiritual Education Level / የመንፈሳዊ ት/ት ደረጃ')
                                    ->options([
                                        'Beginner' => 'Beginner',
                                        'Intermediate' => 'Intermediate',
                                        'Advanced' => 'Advanced',
                                    ])
                                    ->maxLength(100),

                                Forms\Components\Textarea::make('special_talents')
                                    ->label('Special Talents / ልዩ ተሰጥዖ')
                                    ->rows(3),
                            ])
                            ->visible(fn (callable $get) => $get('member_type') === 'Kids'),

                        // Youth/Adult: Family Information
                        Section::make('Family Information / የቤተሰብ መረጃ')
                            ->schema([
                                Forms\Components\TextInput::make('family_size')
                                    ->label('Total Family Size / ቤተሰብ ብዛት')
                                    ->numeric()
                                    ->minValue(1),

                                Forms\Components\TextInput::make('brothers_count')
                                    ->label('Number of Brothers / ወንድም ብዛት')
                                    ->numeric()
                                    ->minValue(0),

                                Forms\Components\TextInput::make('sisters_count')
                                    ->label('Number of Sisters / እህት ብዛት')
                                    ->numeric()
                                    ->minValue(0),

                                Forms\Components\TextInput::make('family_confession_father')
                                    ->label('Family Confession Father Name')
                                    ->maxLength(200),

                                Forms\Components\DatePicker::make('sunday_school_entry_year')
                                    ->label('Sunday School Entry Year / ሰንበት ት/ቤት ዓ.ም')
                                    ->format('Y')
                                    ->displayFormat('Y'),

                                Forms\Components\Textarea::make('past_service_departments')
                                    ->label('Past Service Departments / ያገለገሉባቸው')
                                    ->rows(3),
                            ])
                            ->columns(2)
                            ->visible(fn (callable $get) => in_array($get('member_type'), ['Youth', 'Adult'])),

                        // Youth/Adult: Occupation Information
                        Section::make('Occupation / ሙያ')
                            ->schema([
                                \App\Filament\Forms\Components\CustomOptionSelect::makeWithOther('occupation_status', 'occupation_status', [
                                        'Student' => 'Student',
                                        'Employee' => 'Employee',
                                    ])
                                    ->live(),

                                // Student Fields
                                Forms\Components\Repeater::make('education_history')
                                    ->label('Education History')
                                    ->schema([
                                        Forms\Components\TextInput::make('school_name')
                                            ->label('School Name')
                                            ->required()
                                            ->maxLength(200),

                                        Forms\Components\TextInput::make('education_level')
                                            ->label('Education Level')
                                            ->required()
                                            ->maxLength(100),

                                        Forms\Components\TextInput::make('education_department')
                                            ->label('Department')
                                            ->maxLength(100),

                                        Forms\Components\Toggle::make('is_current')
                                            ->label('Currently Enrolled')
                                            ->default(false),
                                    ])
                                    ->columns(2)
                                    ->visible(fn (callable $get) => $get('occupation_status') === 'Student'),

                                // Employee Fields
                                \App\Filament\Forms\Components\CustomOptionSelect::makeWithOther('employment_status', 'employment_status', [
                                    'Hired' => 'Hired',
                                    'Not Hired' => 'Not Hired',
                                    'Private Sector' => 'Private Sector',
                                ])
                                    ->visible(fn (callable $get) => $get('occupation_status') === 'Employee'),

                                Forms\Components\TextInput::make('company_name')
                                    ->label('Company Name')
                                    ->required(fn (callable $get) => in_array($get('employment_status'), ['Hired', 'Private Sector']))
                                    ->maxLength(200)
                                    ->visible(fn (callable $get) => $get('occupation_status') === 'Employee' && in_array($get('employment_status'), ['Hired', 'Private Sector'])),

                                Forms\Components\TextInput::make('job_role')
                                    ->label('Job Role')
                                    ->required(fn (callable $get) => in_array($get('employment_status'), ['Hired', 'Private Sector']))
                                    ->maxLength(200)
                                    ->visible(fn (callable $get) => $get('occupation_status') === 'Employee' && in_array($get('employment_status'), ['Hired', 'Private Sector'])),

                                Forms\Components\Textarea::make('company_address')
                                    ->label('Company Address')
                                    ->rows(3)
                                    ->visible(fn (callable $get) => $get('occupation_status') === 'Employee' && in_array($get('employment_status'), ['Hired', 'Private Sector'])),
                            ])
                            ->visible(fn (callable $get) => in_array($get('member_type'), ['Youth', 'Adult'])),

                        // Marital Status & Children
                        Section::make('Marital Status & Children')
                            ->schema([
                                \App\Filament\Forms\Components\CustomOptionSelect::makeWithOther('marital_status', 'marital_status', [
                                        'Single' => 'Single',
                                        'Married' => 'Married',
                                    ])
                                    ->live(),

                                Forms\Components\DatePicker::make('marriage_year')
                                    ->label('Marriage Year / ጋብቻ ዓ.ም')
                                    ->format('Y')
                                    ->displayFormat('Y')
                                    ->required(fn (callable $get) => $get('marital_status') === 'Married')
                                    ->visible(fn (callable $get) => $get('marital_status') === 'Married'),

                                Forms\Components\TextInput::make('spouse_name')
                                    ->label('Spouse Name / የባለቤት ስም')
                                    ->required(fn (callable $get) => $get('marital_status') === 'Married')
                                    ->maxLength(200)
                                    ->visible(fn (callable $get) => $get('marital_status') === 'Married'),

                                Forms\Components\TextInput::make('spouse_phone')
                                    ->label('Spouse Phone / የባለቤት ስልክ')
                                    ->regex('/^(\+251|0)?9\d{8}$/')
                                    ->visible(fn (callable $get) => $get('marital_status') === 'Married'),

                                Forms\Components\TextInput::make('children_count')
                                    ->label('Number of Children / ልጆች ብዛት')
                                    ->numeric()
                                    ->minValue(0)
                                    ->live()
                                    ->visible(fn (callable $get) => $get('marital_status') === 'Married'),

                                Forms\Components\Repeater::make('children')
                                    ->label('Children Names')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label(fn ($state, callable $get) => 'Child ' . ($get('index') + 1) . ' / ልጅ ' . ($get('index') + 1))
                                            ->required()
                                            ->maxLength(200),
                                    ])
                                    ->minItems(0)
                                    ->maxItems(fn (callable $get) => $get('../../children_count', 0))
                                    ->visible(fn (callable $get) => $get('marital_status') === 'Married' && $get('children_count') > 0),
                            ])
                            ->columns(2)
                            ->visible(fn (callable $get) => in_array($get('member_type'), ['Youth', 'Adult'])),
                    ]),

                // Tab 5 - Status & History
                Forms\Components\Tabs\Tab::make('Status & History')
                    ->schema([
                        Section::make('Member Status')
                            ->schema([
                                \App\Filament\Forms\Components\CustomOptionSelect::makeWithOther('status', 'member_status', [
                                        'Draft' => 'Draft',
                                        'Member' => 'Member',
                                        'Active' => 'Active',
                                        'Former' => 'Former',
                                    ], true)
                                    ->disabled(fn () => !Auth::user()->hasRole(['hr_head', 'admin', 'superadmin'])),

                                Forms\Components\DatePicker::make('member_since')
                                    ->label('Member Since / አባልነት ጀምሮ')
                                    ->format('Y-m-d')
                                    ->displayFormat('M d, Y'),

                                Forms\Components\Textarea::make('hr_notes')
                                    ->label('HR Notes')
                                    ->rows(3)
                                    ->disabled(fn () => !Auth::user()->hasRole(['hr_head', 'admin', 'superadmin'])),
                            ]),

                        Section::make('Assignment History')
                            ->schema([
                                Forms\Components\Placeholder::make('assignment_history')
                                    ->label('Recent Group Assignments')
                                    ->content(fn ($record) => $record?->groupAssignments()
                                        ->with('group')
                                        ->latest()
                                        ->take(5)
                                        ->map(fn ($assignment) =>
                                            $assignment->group->name . ' - ' .
                                            $assignment->assigned_at->format('M d, Y')
                                        )
                                        ->join("\n") ?: 'No assignments yet'),

                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('view_timeline')
                                        ->label('View Full Timeline →')
                                        ->url(fn ($record) => static::getUrl('timeline', ['member_id' => $record?->id]))
                                        ->icon('heroicon-o-clock'),
                                ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('member_code')
                    ->label('Member ID')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Full Name')
                    ->searchable(['first_name', 'father_name', 'grandfather_name']),

                Tables\Columns\BadgeColumn::make('member_type')
                    ->colors([
                        'info' => 'Kids',
                        'warning' => 'Youth',
                        'success' => 'Adult',
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'Draft',
                        'info' => 'Member',
                        'success' => 'Active',
                        'danger' => 'Former',
                    ]),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('currentGroup.name')
                    ->label('Current Group')
                    ->getStateUsing(fn (Member $record): string => $record->currentGroup?->name ?? 'Unassigned')
                    ->color(fn (Member $record): string => $record->currentGroup ? 'primary' : 'gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->formatStateUsing(fn ($state) => $state ? app(EthiopianDateHelper::class)->toString($state) : '')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Member' => 'Member',
                        'Active' => 'Active',
                        'Former' => 'Former',
                    ]),

                Tables\Filters\SelectFilter::make('member_type')
                    ->options([
                        'Kids' => 'Kids',
                        'Youth' => 'Youth',
                        'Adult' => 'Adult',
                    ]),

                Tables\Filters\SelectFilter::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Parent')
                    ->relationship('parents', 'full_name')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => request()->has('parent_id')),

                Tables\Filters\SelectFilter::make('group_id')
                    ->label('Filter by Group')
                    ->options(fn () => MemberGroup::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $groupId = $data['value'] ?? null;

                        if (blank($groupId)) {
                            return $query;
                        }

                        return $query->whereHas('currentGroupAssignment', function (Builder $assignmentQuery) use ($groupId): void {
                            $assignmentQuery
                                ->active()
                                ->where('group_id', $groupId);
                        });
                    }),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
                Actions\RestoreAction::make(),

                Actions\Action::make('remove_from_group')
                    ->label('Remove from Group')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->visible(fn ($record) => $record->currentGroup)
                    ->requiresConfirmation()
                    ->modalHeading('Remove Member from Group')
                    ->modalDescription(fn ($record) =>
                        "Are you sure you want to remove {$record->full_name} from {$record->currentGroup->name}?"
                    )
                    ->action(function ($record) {
                        try {
                            if ($record->currentGroup) {
                                $record->currentGroup->removeMember($record->id);

                                \Filament\Notifications\Notification::make()
                                    ->title('Member Removed')
                                    ->body("{$record->full_name} has been removed from {$record->currentGroup->name}")
                                    ->success()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Removal Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                    Actions\ExportBulkAction::make()
                        ->exporter(MemberExporter::class),

                    Actions\BulkAction::make('assign_to_group')
                        ->label('Assign to Group')
                        ->icon('heroicon-o-user-plus')
                        ->accessSelectedRecords()
                        ->mountUsing(function (Tables\Actions\BulkAction $action): void {
                            $selectedCount = $action->getSelectedRecordsQuery()->count();

                            if ($selectedCount > 100) {
                                Notification::make()
                                    ->title('Selection Limit Exceeded')
                                    ->body('You can assign a maximum of 100 members at a time.')
                                    ->warning()
                                    ->send();

                                throw new Cancel();
                            }
                        })
                        ->form([
                            Forms\Components\Select::make('group_id')
                                ->label('Group')
                                ->options(fn () => MemberGroup::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->preload()
                                ->required(),

                            Forms\Components\DatePicker::make('effective_from')
                                ->label('Effective From Date')
                                ->default(now())
                                ->required(),
                        ])
                        ->action(function (Tables\Actions\BulkAction $action, array $data): void {
                            $group = MemberGroup::query()->findOrFail($data['group_id']);
                            $effectiveFrom = $data['effective_from'];

                            $members = $action->getSelectedRecords();

                            $membersWithActiveAssignments = [];

                            foreach ($members as $member) {
                                $hasActiveAssignment = MemberGroupAssignment::query()
                                    ->forMember($member->id)
                                    ->active()
                                    ->exists();

                                if ($hasActiveAssignment) {
                                    $membersWithActiveAssignments[] = $member->full_name;
                                }
                            }

                            if (!empty($membersWithActiveAssignments)) {
                                $list = implode(', ', $membersWithActiveAssignments);

                                Notification::make()
                                    ->title('Assignment failed.')
                                    ->body("The following members already have groups: {$list}")
                                    ->danger()
                                    ->send();

                                return;
                            }

                            try {
                                DB::transaction(function () use ($members, $group, $effectiveFrom): void {
                                    foreach ($members as $member) {
                                        $assignment = MemberGroupAssignment::create([
                                            'member_id' => $member->id,
                                            'group_id' => $group->id,
                                            'effective_from' => $effectiveFrom,
                                            'assigned_by' => auth()->user()->id(),
                                        ]);

                                        Log::channel('audit')->warning('Tier 2 Audit Log', [
                                            'tier' => 2,
                                            'action' => 'member_group_assigned',
                                            'member_id' => $member->id,
                                            'member_name' => $member->full_name,
                                            'group_id' => $group->id,
                                            'group_name' => $group->name,
                                            'effective_from' => $assignment->effective_from?->toDateString(),
                                            'assigned_by' => auth()->user()->id(),
                                            'timestamp' => now()->toDateTimeString(),
                                        ]);
                                    }
                                });

                                $count = $members->count();
                                Notification::make()
                                    ->title('Assignment successful')
                                    ->body("{$count} members assigned to {$group->name} successfully")
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Assignment Failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembers::route('/'),
            'create' => Pages\CreateMember::route('/create'),
            'edit' => Pages\EditMember::route('/{record}/edit'),
            'view' => Pages\ViewMember::route('/{record}'),
            'timeline' => Pages\Timeline::route('/{record}/timeline'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user->hasRole([
            'admin',
            'superadmin',
            'hr_head',
            'education_head',
            'finance_head',
            'charity_head',
            'internal_relations_head',
            'department_secretary',
            'nibret_hisab_head'
        ]);
    }

    public static function canCreate(): bool
    {
        $user = Auth::user();

        return $user->hasRole([
            'hr_head',
            'admin',
            'superadmin'
        ]);
    }

    public static function canEdit($record): bool
    {
        $user = Auth::user();

        return $user->hasRole([
            'hr_head',
            'admin',
            'superadmin'
        ]);
    }

    public static function canDelete($record): bool
    {
        $user = Auth::user();

        return $user->hasRole([
            'hr_head',
            'admin',
            'superadmin'
        ]);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['department']);
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->full_name;
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Member Code' => $record->member_code,
            'Phone' => $record->phone,
            'Type' => $record->member_type,
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'father_name', 'phone', 'member_code'];
    }
}

