<?php

namespace App\Filament\Resources;

use App\Filament\Exports\MemberExporter;
use App\Filament\Forms\Components\CustomOptionSelect;
use App\Filament\Resources\MemberResource\Pages;
use App\Helpers\EthiopianDateHelper;
use App\Models\Department;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MemberGroupAssignment;
use App\Models\MemberParentGuardian;
use App\Models\ParentModel;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Cancel;
use Filament\Tables;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->hasRole(['hr_head', 'internal_relations_head', 'admin', 'superadmin']);
    }

    public static function getNavigationIcon(): ?string { return 'heroicon-o-users'; }
    public static function getNavigationGroup(): ?string { return 'Membership'; }
    public static function getNavigationLabel(): string { return 'Members'; }
    public static function getModelLabel(): string { return 'Member'; }
    public static function getPluralModelLabel(): string { return 'Members'; }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('MemberTabs')
                    ->contained(false)
                    ->tabs([
                        // Tab 1 - Personal Information
                        Tab::make('Personal Information')
                            ->icon('heroicon-o-user')
                            ->schema([
                                // Placeholder::make('workflow_guide')
                                //     ->label('Important Registration Workflow')
                                //     ->content(function (callable $get) {
                                //         if ($get('member_type') === 'Kids') {
                                //             return '📝 **For Kids Registration:** 
                                //             1. Register the child with "Kids" member type
                                //             2. Link to existing parent/guardian members (Adult type) 
                                //             3. Parents must be registered FIRST as separate members';
                                //         } else {
                                //             return '📝 **For Adult Registration:** 
                                //             1. Register the adult with appropriate member type
                                //             2. Set marital status and children information (reference only)
                                //             3. Each child must be registered separately as "Kids" member type';
                                //         }
                                //     })
                                //     ->columnSpanFull()
                                //     ->visible(fn (callable $get) => $get('member_type')),

                                Section::make('Basic Information')
                                    ->description('Primary personal identifiers and demographic details.')
                                    ->schema([
                                        CustomOptionSelect::make('title')
                                            ->label('Title')
                                            ->customOptions('title', [
                                                'Dn.' => 'Dn. (ዲ.)',  
                                                'Mr.' => 'Mr. (አማልክ)',
                                                'Mrs.' => 'Mrs. (ወ/ሮ)',
                                                'Ms.' => 'Ms. (ወ/ሪት)',
                                                'Dr.' => 'Dr.',
                                                'Kesis' => 'ቀሲስ'
                                            ]),

                                        CustomOptionSelect::make('member_type')
                                            ->label('Member Type')
                                            ->customOptions('member_type', [
                                                'Kids' => 'Kids',
                                                'Youth' => 'Youth',
                                                'Adult' => 'Adult',
                                            ])
                                            ->required()
                                            ->live(),

                                        TextInput::make('first_name')
                                            ->label('First Name')
                                            ->required()
                                            ->maxLength(100),

                                        TextInput::make('father_name')
                                            ->label("Father's Name")
                                            ->required()
                                            ->maxLength(100),

                                        TextInput::make('grandfather_name')
                                            ->label("Grandfather's Name")
                                            ->required()
                                            ->maxLength(100),

                                        TextInput::make('mother_name')
                                            ->label("Mother's Name")
                                            ->required()
                                            ->maxLength(100),

                                        DatePicker::make('date_of_birth')
                                            ->label('Date of Birth')
                                            ->required()
                                            ->maxDate(now()),

                                        Radio::make('gender')
                                            ->label('Gender')
                                            ->options([
                                                'Male'   => 'Male',
                                                'Female' => 'Female',
                                            ])
                                            ->required(),

                                        TextInput::make('christian_name')
                                            ->label('Baptism Name')
                                            ->maxLength(100),

                                        TextInput::make('member_code')
                                            ->label('Member Code')
                                            ->disabled()
                                            ->dehydrated(false),

                                        FileUpload::make('photo')
                                            ->disk('members')
                                            ->image()
                                            ->acceptedFileTypes(['image/*'])
                                            ->maxSize(5120),

                                        Toggle::make('consent_for_photography')
                                            ->label('Parent/Guardian has given consent for photography'),
                                    ])
                                    ->columns(3),
                            ]),

                        // Tab 2 - Address & Contact
                        Tab::make('Address & Contact')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Section::make('Residential Address')
                                    ->description('Physical living location and addressing.')
                                    ->schema([
                                        TextInput::make('city')
                                            ->label('City')
                                            ->required()
                                            ->maxLength(100),

                                        TextInput::make('sub_city')
                                            ->label('Sub-City')
                                            ->required()
                                            ->maxLength(100),

                                        TextInput::make('woreda')
                                            ->label('Woreda')
                                            ->required()
                                            ->maxLength(50),

                                        TextInput::make('zone')
                                            ->label('Ketena')
                                            ->maxLength(100),

                                        TextInput::make('block')
                                            ->label('Block')
                                            ->maxLength(50),

                                        TextInput::make('neighborhood')
                                            ->label('Neighborhood Specific Name')
                                            ->maxLength(200),
                                    ])
                                    ->columns(3),

                                Section::make('Contact Information')
                                    ->description('Digital and telephonic communication channels.')
                                    ->schema([
                                        TextInput::make('phone')
                                            ->label('Personal Phone')
                                            ->required(fn (callable $get) => $get('member_type') !== 'Kids')
                                            ->regex('/^(\+251|0)?9\d{8}$/')
                                            ->unique(ignoreRecord: true)
                                            ->live(debounce: 500),

                                        TextInput::make('email')
                                            ->label('Email (Optional)')
                                            ->email()
                                            ->maxLength(191),
                                    ])
                                    ->columns(2),
                            ]),

                        // Tab 3 - Emergency & Spiritual
                        Tab::make('Emergency & Spiritual')
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Section::make('Emergency Contact')
                                    ->description('Who to contact in case of an emergency.')
                                    ->schema([
                                        TextInput::make('emergency_contact_name')
                                            ->label('Emergency Contact Name')
                                            ->required()
                                            ->maxLength(200),

                                        TextInput::make('emergency_contact_phone')
                                            ->label('Emergency Contact Phone')
                                            ->required()
                                            ->regex('/^(\+251|0)?9\d{8}$/'),
                                    ])
                                    ->columns(2),

                                Section::make('Spiritual Information / መንፈሳዊ መረጃ')
                                    ->description('Details regarding the member\'s confession father.')
                                    ->schema([
                                        TextInput::make('confession_father_name')
                                            ->label("Confession Father's Name")
                                            ->maxLength(200),

                                        TextInput::make('confession_father_phone')
                                            ->label("Confession Father's Phone")
                                            ->regex('/^(\+251|0)?9\d{8}$/'),
                                    ])
                                    ->columns(2),
                            ]),

                        // Tab 4 - Parent/Guardian (Kids + historical)
                        Tab::make('Parent/Guardian / ወላጅ')
                            ->icon('heroicon-o-user-group')
                            ->schema([ 

                                Section::make('Parent/Guardian Information')
                                    ->schema([
                                        Repeater::make('parent_guardian_info')
                                            ->label('Parent/Guardian Assignments')
                                            ->schema([
                                                Select::make('parent_id')
                                                    ->label('Select Parent/Guardian')
                                                    ->options(function () {
                                                        try {
                                                            return \App\Models\ParentModel::query()
                                                                ->where('is_active', true)
                                                                ->orderBy('full_name')
                                                                ->pluck('full_name', 'id')
                                                                ->toArray();
                                                        } catch (\Exception $e) {
                                                            return [];
                                                        }
                                                    })
                                                    ->searchable()
                                                    ->preload()
                                                    ->afterStateUpdated(function ($state, callable $set) {
                                                        if ($state) {
                                                            $parent = \App\Models\ParentModel::find($state);
                                                            if ($parent) {
                                                                $set('parent_name', $parent->full_name);
                                                                $set('parent_phone', $parent->phone);
                                                                $set('relationship', $parent->relationship_type);
                                                            }
                                                        }
                                                    }),

                                                TextInput::make('parent_name')
                                                    ->label('Parent/Guardian Name')
                                                    ->required(),

                                                Select::make('relationship')
                                                    ->label('Relationship')
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
                                                    ->required()
                                                    ->searchable(),

                                                TextInput::make('parent_phone')
                                                    ->label('Phone Number')
                                                    ->tel(),
                                            ])
                                            ->columns(2)
                                            ->collapsible()
                                            ->itemLabel(fn (array $state): ?string => 
                                                $state['parent_name'] ?? 'New Parent/Guardian'
                                            )
                                            ->addActionLabel('+ Add Parent/Guardian'),
                                    ])
                                    ->visible(fn (callable $get) => $get('member_type') === 'Kids'),

                                Section::make('Additional Kids Information')
                                    ->description('Details specific to Sunday School level and talents.')
                                    ->schema([
                                        Select::make('spiritual_education_level')
                                            ->label('Spiritual Education Level')
                                            ->options([
                                                'Beginner'     => 'Beginner',
                                                'Intermediate' => 'Intermediate',
                                                'Advanced'     => 'Advanced',
                                            ])
                                            ->required(),

                                        Textarea::make('special_talents')
                                            ->label('Special Talents')
                                            ->rows(3),
                                    ])
                                    ->visible(fn (callable $get) => $get('member_type') === 'Kids'),

                                Section::make('Historical Parent/Guardian Records (Read-Only)')
                                    ->description('This member was previously registered as a Kid. Parent/guardian records from that period are preserved below for reference.')
                                    ->schema([
                                        Placeholder::make('historical_parents')
                                            ->label('')
                                            ->content(fn ($record) => $record?->parentGuardians()
                                                ->get()
                                                ->map(fn ($pg) =>
                                                    "• **{$pg->parent_name}** ({$pg->relationship}) — " .
                                                    ($pg->phone ?? 'No phone')
                                                )
                                                ->join("\n") ?: 'No historical parent records found.')
                                            ->visible(fn ($record) => $record && $record->parentGuardians()->exists()),
                                    ])
                                    ->collapsed()
                                    ->visible(fn ($record, callable $get) =>
                                        in_array($get('member_type'), ['Youth', 'Adult']) &&
                                        $record && $record->parentGuardians()->exists()
                                    ),
                            ])
                            ->visible(fn ($record, callable $get) =>
                                $get('member_type') === 'Kids' ||
                                (in_array($get('member_type'), ['Youth', 'Adult']) && $record && $record->parentGuardians()->exists())
                            ),

                        // Tab 5 - Family & Occupation (Youth/Adult only)
                        Tab::make('Family & Occupation')
                            ->icon('heroicon-o-briefcase')
                            ->schema([
                                Section::make('Family Information')
                                    ->description('Details about family size and spiritual background.')
                                    ->schema([
                                        TextInput::make('family_size')
                                            ->label('Total Family Size')
                                            ->numeric()
                                            ->minValue(1),

                                        TextInput::make('brothers_count')
                                            ->label('Number of Brothers')
                                            ->numeric()
                                            ->minValue(0),

                                        TextInput::make('sisters_count')
                                            ->label('Number of Sisters')
                                            ->numeric()
                                            ->minValue(0),

                                        TextInput::make('family_confession_father')
                                            ->label('Family Confession Father Name')
                                            ->maxLength(200),

                                        DatePicker::make('sunday_school_entry_year')
                                            ->label('Sunday School Entry Year')
                                            ->format('Y')
                                            ->displayFormat('Y'),

                                        Textarea::make('past_service_departments')
                                            ->label('Past Service Departments')
                                            ->rows(3),
                                    ])
                                    ->columns(2),

                                Section::make('Occupation / ሙያ')
                                    ->description('Educational and professional background details.')
                                    ->schema([
                                        CustomOptionSelect::make('occupation_status')
                                            ->label('Occupation Status')
                                            ->customOptions('occupation_status', [
                                                'Student'  => 'Student',
                                                'Employee' => 'Employee',
                                            ])
                                            ->live(),

                                        Repeater::make('education_history')
                                            ->label('Education History')
                                            ->schema([
                                                TextInput::make('school_name')
                                                    ->label('School Name')
                                                    ->required()
                                                    ->maxLength(200),
                                                TextInput::make('education_level')
                                                    ->label('Education Level')
                                                    ->required()
                                                    ->maxLength(100),
                                                TextInput::make('education_department')
                                                    ->label('Department')
                                                    ->maxLength(100),
                                                Toggle::make('is_current')
                                                    ->label('Currently Enrolled')
                                                    ->default(false),
                                            ])
                                            ->columns(2)
                                            ->collapsible()
                                            ->itemLabel(fn (array $state): ?string => $state['school_name'] ?? 'New Education Entry')
                                            ->addActionLabel('+ Add Education'),

                                        CustomOptionSelect::make('employment_status')
                                            ->label('Employment Status')
                                            ->customOptions('employment_status', [
                                                'Hired'          => 'Hired',
                                                'Not Hired'      => 'Not Hired',
                                                'Private Sector' => 'Private Sector',
                                            ])
                                            ->visible(fn (callable $get) => $get('occupation_status') === 'Employee'),

                                        TextInput::make('company_name')
                                            ->label('Company Name')
                                            ->required(fn (callable $get) => in_array($get('employment_status'), ['Hired', 'Private Sector']))
                                            ->maxLength(200)
                                            ->visible(fn (callable $get) => $get('occupation_status') === 'Employee' && in_array($get('employment_status'), ['Hired', 'Private Sector'])),

                                        TextInput::make('job_role')
                                            ->label('Job Role')
                                            ->required(fn (callable $get) => in_array($get('employment_status'), ['Hired', 'Private Sector']))
                                            ->maxLength(200)
                                            ->visible(fn (callable $get) => $get('occupation_status') === 'Employee' && in_array($get('employment_status'), ['Hired', 'Private Sector'])),

                                        Textarea::make('company_address')
                                            ->label('Company Address')
                                            ->rows(3)
                                            ->visible(fn (callable $get) => $get('occupation_status') === 'Employee' && in_array($get('employment_status'), ['Hired', 'Private Sector'])),

                                        // Occupation Categories and Subcategories
                                        Repeater::make('occupation_categories')
                                            ->label('Occupation Categories & Subcategories')
                                            ->schema([
                                                Select::make('category')
                                                    ->label('Category')
                                                    ->options([
                                                        'Community, Social & Linguistic Services' => 'Community, Social & Linguistic Services',
                                                        'Security & Law Enforcement' => 'Security & Law Enforcement',
                                                        'Government, Legal & Civil Service' => 'Government, Legal & Civil Service',
                                                        'Education, Research & Academia' => 'Education, Research & Academia',
                                                        'Healthcare & Medical Sciences' => 'Healthcare & Medical Sciences',
                                                        'Hospitality, Tourism & Food Service' => 'Hospitality, Tourism & Food Service',
                                                        'Transportation & Logistics' => 'Transportation & Logistics',
                                                        'Technology & IT' => 'Technology & IT',
                                                        'Construction, Engineering & Trades' => 'Construction, Engineering & Trades',
                                                        'Trade, Sales & Commerce' => 'Trade, Sales & Commerce',
                                                        'Agriculture, Farming & Forestry' => 'Agriculture, Farming & Forestry',
                                                    ]) 
                                                    ->searchable(),

                                                TextInput::make('subcategories')
                                                    ->label('Subcategories (Tags)')
                                                    ->helperText('Enter subcategories separated by commas (e.g., Web Development, Database Management, Cloud Computing)')
                                                    ->placeholder('e.g., Web Development, Database Management')
                                                    ->separator(',')
                                            ])
                                            ->columns(2)
                                            ->collapsible()
                                            ->itemLabel(fn (array $state): ?string => 
                                                $state['category'] ?? 'New Category'
                                            )
                                            ->addActionLabel('+ Add Category')
                                            ->visible(fn (callable $get) => $get('occupation_status') === 'Employee'),
                                    ]),

                                Section::make('Marital Status & Children')
                                    ->description('Marriage details and dependent children.')
                                    ->schema([
                                        CustomOptionSelect::make('marital_status')
                                            ->label('Marital Status')
                                            ->customOptions('marital_status', [
                                                'Single'  => 'Single',
                                                'Married' => 'Married',
                                            ])
                                            ->live(),

                                        DatePicker::make('marriage_year')
                                            ->label('Marriage Year / ጋብቻ ዓ.ም')
                                            ->format('Y')
                                            ->displayFormat('Y')
                                            ->required(fn (callable $get) => $get('marital_status') === 'Married')
                                            ->visible(fn (callable $get) => $get('marital_status') === 'Married'),

                                        TextInput::make('spouse_name')
                                            ->label('Spouse Name / የባለቤት ስም')
                                            ->required(fn (callable $get) => $get('marital_status') === 'Married')
                                            ->maxLength(200)
                                            ->visible(fn (callable $get) => $get('marital_status') === 'Married'),

                                        TextInput::make('spouse_phone')
                                            ->label('Spouse Phone / የባለቤት ስልክ')
                                            ->regex('/^(\+251|0)?9\d{8}$/')
                                            ->visible(fn (callable $get) => $get('marital_status') === 'Married'),

                                        Repeater::make('children')
                                            ->label('Children Information')
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Child Name')
                                                    ->required()
                                                    ->maxLength(200),
                                                
                                                DatePicker::make('birth_date')
                                                    ->label('Birth Date (Optional)')
                                                    ->format('Y-m-d')
                                                    ->displayFormat('M d, Y')
                                                    ->nullable(),
                                            ])
                                            ->columns(2)
                                            ->collapsible()
                                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'New Child')
                                            ->addActionLabel('+ Add Child')
                                            ->visible(fn (callable $get) => $get('marital_status') === 'Married'),
                                    ])
                                    ->columns(2),
                            ])
                            ->visible(fn (callable $get) => in_array($get('member_type'), ['Youth', 'Adult'])),

                        // Tab 6 - Status & History
                        Tab::make('Status & History')
                            ->icon('heroicon-o-clock')
                            ->schema([
                                Section::make('Member Status')
                                    ->schema([
                                        CustomOptionSelect::make('status')
                                            ->label('Status')
                                            ->customOptions('member_status', [
                                                'Draft'  => 'Draft',
                                                'Active' => 'Active',
                                                'Former' => 'Former',
                                            ])
                                            ->required()
                                            ->disabled(fn () => !Auth::user()->hasRole(['hr_head', 'admin', 'superadmin'])),
                                    ]),

                                Section::make('Assignment History')
                                    ->schema([
                                        Placeholder::make('assignment_history')
                                            ->label('Recent Group Assignments')
                                            ->content(fn ($record) => $record?->groupAssignments()
                                                ->with('group')
                                                ->latest()
                                                ->take(5)
                                                ->get()
                                                ->map(fn ($assignment) =>
                                                    $assignment->group->name . ' - ' .
                                                    $assignment->assigned_at->format('M d, Y')
                                                )
                                                ->join("\n") ?: 'No assignments yet')
                                            ->visible(fn ($record) => $record && $record->groupAssignments()->exists()),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function handleParentData($record, array $data): void
    {
        \Log::info('handleParentData called', [
            'record_id' => $record->id,
            'data_keys' => array_keys($data),
            'parentGuardians_exists' => isset($data['parentGuardians']),
            'parentGuardians_data' => $data['parentGuardians'] ?? 'not set'
        ]);

        if (!isset($data['parentGuardians']) || !is_array($data['parentGuardians'])) {
            return;
        }

        foreach ($data['parentGuardians'] as $parentData) {
            \Log::info('Processing parent data', ['parentData' => $parentData]);
            
            if (empty($parentData['parent_name'])) {
                continue;
            }

            // Check if parent_id is provided (existing parent)
            if (!empty($parentData['parent_id'])) {
                // Link to existing parent
                MemberParentGuardian::create([
                    'member_id' => $record->id,
                    'parent_id' => $parentData['parent_id'],
                    'parent_name' => $parentData['parent_name'],
                    'relationship' => $parentData['relationship'] ?? 'Guardian',
                    'phone' => $parentData['parent_phone'] ?? '',
                    'is_external' => false,
                ]);

                // Update member count for the existing parent
                $parent = ParentModel::find($parentData['parent_id']);
                if ($parent) {
                    $parent->updateMemberCount();
                }
            } else {
                // Create new parent in parents table
                $parent = ParentModel::create([
                    'full_name' => $parentData['parent_name'],
                    'phone' => $parentData['parent_phone'] ?? '',
                    'relationship_type' => $parentData['relationship'] ?? 'Guardian',
                    'is_active' => true,
                ]);

                // Update member count for the new parent
                $parent->updateMemberCount();

                // Link to new parent
                MemberParentGuardian::create([
                    'member_id' => $record->id,
                    'parent_id' => $parent->id,
                    'parent_name' => $parentData['parent_name'],
                    'relationship' => $parentData['relationship'] ?? 'Guardian',
                    'phone' => $parentData['parent_phone'] ?? '',
                    'is_external' => false,
                ]);
            }
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $parentId = request()->query('parent_id');
                if ($parentId) {
                    $query->whereHas('parentGuardians', function (Builder $q) use ($parentId) {
                        $q->where('parent_id', $parentId);
                    });
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('member_code')
                    ->label('Member ID')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Full Name')
                    ->searchable(['first_name', 'father_name', 'grandfather_name']),

                Tables\Columns\TextColumn::make('member_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Kids'  => 'info',
                        'Youth' => 'warning',
                        'Adult' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Draft'  => 'gray',
                        'Member' => 'info',
                        'Active' => 'success',
                        'Former' => 'danger',
                        default  => 'gray',
                    }),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),

                Tables\Columns\TextColumn::make('currentGroup.name')
                    ->label('Current Group')
                    ->getStateUsing(fn (Member $record): string => $record->currentGroup?->name ?? 'Unassigned')
                    ->badge()
                    ->color(fn (Member $record): string => $record->currentGroup ? 'primary' : 'gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->formatStateUsing(fn ($state) => $state ? app(EthiopianDateHelper::class)->toString($state) : '')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Draft'  => 'Draft',
                        'Member' => 'Member',
                        'Active' => 'Active',
                        'Former' => 'Former',
                    ]),

                Tables\Filters\SelectFilter::make('member_type')
                    ->options([
                        'Kids'  => 'Kids',
                        'Youth' => 'Youth',
                        'Adult' => 'Adult',
                    ]),

                Tables\Filters\SelectFilter::make('department')
                    ->label('Department')
                    ->options(fn () => 
                        \App\Models\Department::query()
                            ->withoutGlobalScope(\App\Models\Scopes\DepartmentScope::class)
                            ->orderBy('name_en')
                            ->pluck('name_en', 'id')
                            ->toArray()
                    )
                    ->query(function (Builder $query, array $data): Builder {
                        $departmentId = $data['value'] ?? null;
                        if (blank($departmentId)) return $query;
                        return $query->where('department_id', $departmentId);
                    }),

                Tables\Filters\SelectFilter::make('group_id')
                    ->label('Filter by Group')
                    ->options(fn () => MemberGroup::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $groupId = $data['value'] ?? null;
                        if (blank($groupId)) return $query;
                        return $query->whereHas('currentGroupAssignment', function (Builder $q) use ($groupId): void {
                            $q->active()->where('group_id', $groupId);
                        });
                    }),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                // Use Tables\Actions for individual row actions
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),

                Action::make('timeline')
                    ->label('Timeline')
                    ->icon('heroicon-o-clock')  
                    ->url(fn ($record): string => static::getUrl('timeline', ['record' => $record]))
                    ->color('primary'),

                Action::make('remove_from_group')
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
                                Notification::make()
                                    ->title('Member Removed')
                                    ->body("{$record->full_name} has been removed from {$record->currentGroup->name}")
                                    ->success()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Removal Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                // Use DeleteBulkAction instead of DeleteAction
                DeleteBulkAction::make(),

                // Use ExportBulkAction for bulk exporting
                ExportBulkAction::make()
                    ->exporter(MemberExporter::class),

                // Use Tables\Actions\BulkAction for custom bulk operations
                BulkAction::make('assign_to_group')
                    ->label('Assign to Group')
                    ->icon('heroicon-o-user-plus')
                    ->deselectRecordsAfterCompletion()
                    ->mountUsing(function (BulkAction $action): void {
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
                    Select::make('member_group_id')
                        ->label('Group')
                        ->options(function () {
                            $groups = MemberGroup::query()
                                ->active()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all();
                            
                            \Log::info('Group options loaded', [
                                'groups_count' => count($groups),
                                'groups' => $groups
                            ]);
                            
                            return $groups;
                        })
                        ->searchable()
                        ->preload()
                        ->required(),

                    DatePicker::make('effective_from')
                        ->label('Effective From Date')
                        ->default(now())
                        ->required(),
                ])
                ->action(function (BulkAction $action, array $data): void {
                    \Log::info('ACTION TRIGGERED - Group assignment started', [
                        'data' => $data,
                        'all_keys' => array_keys($data),
                        'member_group_id' => $data['member_group_id'] ?? 'MISSING',
                        'effective_from' => $data['effective_from'] ?? 'MISSING'
                    ]);

                    $group = MemberGroup::query()->findOrFail($data['member_group_id']);
                    $effectiveFrom = $data['effective_from'];
                    $members = $action->getSelectedRecords();

                    \Log::info('Group assignment details', [
                        'group_id' => $group->id,
                        'group_name' => $group->name,
                        'effective_from' => $effectiveFrom,
                        'members_count' => $members->count()
                    ]);

                    try {
                        DB::transaction(function () use ($members, $group, $effectiveFrom): void {
                            foreach ($members as $member) {
                                // End existing active assignments if any
                                MemberGroupAssignment::query()
                                    ->forMember($member->id)
                                    ->active()
                                    ->update([
                                        'effective_to' => $effectiveFrom,
                                        'removed_by' => auth()->id(),
                                    ]);

                                $assignment = MemberGroupAssignment::create([
                                    'member_id'      => $member->id,
                                    'group_id'       => $group->id,
                                    'effective_from' => $effectiveFrom,
                                    'assigned_by'    => auth()->id(),
                                ]);

                                Log::channel('audit')->warning('Tier 2 Audit Log', [
                                    'tier'           => 2,
                                    'action'         => 'member_group_assigned',
                                    'member_id'      => $member->id,
                                    'member_name'    => $member->full_name,
                                    'group_id'       => $group->id,
                                    'group_name'     => $group->name,
                                    'effective_from' => $assignment->effective_from?->toDateString(),
                                    'assigned_by'    => auth()->id(),
                                    'timestamp'      => now()->toDateTimeString(),
                                ]);
                            }
                        });

                        Notification::make()
                            ->title('Assignment successful')
                            ->body("{$members->count()} members assigned to {$group->name} successfully")
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
        ])
        ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'    => Pages\ListMembers::route('/'),
            'create'   => Pages\CreateMember::route('/create'),
            'edit'     => Pages\EditMember::route('/{record}/edit'),
            'view'     => Pages\ViewMember::route('/{record}'),
            'timeline' => Pages\Timeline::route('/{record}/timeline'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();
        return $user->hasRole([
            'admin', 'superadmin', 'hr_head', 'education_head', 'finance_head',
            'charity_head', 'internal_relations_head', 'department_secretary', 'nibret_hisab_head',
        ]);
    }

    public static function canCreate(): bool
    {
        return Auth::user()->hasRole(['hr_head', 'internal_relations_head', 'admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        return Auth::user()->hasRole(['hr_head', 'internal_relations_head', 'admin', 'superadmin']);
    }

    public static function canDelete($record): bool
    {
        return Auth::user()->hasRole(['hr_head', 'internal_relations_head', 'admin', 'superadmin']);
    }

    protected static function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getTableQuery();
        
        // Handle parent_id filter from ParentResource
        if (request()->has('parent_id')) {
            $parentId = request()->get('parent_id');
            $query->whereHas('parentGuardians', function ($q) use ($parentId) {
                $q->where('parent_id', $parentId);
            });
        }
        
        return $query;
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['department']);
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->full_name;
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Member Code' => $record->member_code,
            'Phone'       => $record->phone,
            'Type'        => $record->member_type,
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'father_name', 'phone', 'member_code'];
    }
}
