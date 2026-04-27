<?php

namespace App\Filament\Resources;

use App\Filament\Exports\MemberExporter;
use App\Jobs\BulkAssignToDepartmentJob;
use App\Jobs\BulkAssignToGroupJob;
use App\Services\PhoneFormattingService;
use App\Services\UploadSanitizer;
use App\Actions\Members\BulkAssignmentValidationAction;
use Filament\Schemas\Schema;
use App\Filament\Forms\Components\CustomOptionSelect;
use App\Filament\Resources\MemberResource\Pages;
use App\Helpers\EthiopianDateHelper;
use App\Models\Member;
use App\Models\MemberGroup;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ExportBulkAction;
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
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MemberResource extends BaseResource
{
    protected static ?string $model = Member::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-users';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources.navigation.membership_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.member.plural_label');
    }

    public static function getModelLabel(): string
    {
        return __('resources.member.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.member.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Tabs::make('MemberTabs')
                    ->contained(false)
                    ->tabs([
                        // Tab 1 - Personal Information
                        Tab::make(__('resources.member.tabs.personal_information'))
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

                                Section::make(__('resources.member.sections.basic_information'))
                                    ->description(__('resources.member.sections.basic_information_description'))
                                    ->schema([
                                        CustomOptionSelect::make('title')
                                            ->label('Title')
                                            ->customOptions('title', [
                                                'Dn.' => 'Dn.',
                                                'Mr.' => 'Mr.',
                                                'Mrs.' => 'Mrs.',
                                                'Ms.' => 'Ms.',
                                                'Dr.' => 'Dr.',
                                                'Kesis' => 'Kesis',
                                            ])
                                            ->required(),

                                        CustomOptionSelect::make('member_type')
                                            ->label('Member Type')
                                            ->customOptions('member_type', [
                                                'Kids' => 'Kids',
                                                'Youth' => 'Youth',
                                                'Adult' => 'Adult',
                                            ])
                                            ->required()
                                            ->live(),

                                        DatePicker::make('member_since')
                                            ->label('Member Since')
                                            ->date()
                                            ->required()
                                            ->default(now())
                                            ->helperText('Date when member formally became a member'),

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
                                                'Male' => 'Male',
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
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                                            ->maxSize(5120)
                                            ->saveUploadedFileUsing(UploadSanitizer::saveCallback('members', 'members', ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])),

                                        Toggle::make('consent_for_photography')
                                            ->label('Parent/Guardian has given consent for photography'),
                                    ])
                                    ->columns(3),
                            ]),

                        // Tab 2 - Address & Contact
                        Tab::make(__('resources.member.tabs.address_contact'))
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Section::make(__('resources.member.sections.residential_address'))
                                    ->description(__('resources.member.sections.residential_address_description'))
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

                                Section::make(__('resources.member.sections.contact_information'))
                                    ->description(__('resources.member.sections.contact_information_description'))
                                    ->schema([
                                        TextInput::make('phone')
                                            ->label('Personal Phone')
                                            ->required(fn (callable $get) => $get('member_type') !== 'Kids')
                                            ->prefix(PhoneFormattingService::prefix())
                                            ->regex('/^[0-9]{9}$/')
                                            ->placeholder('912345678')
                                            ->helperText(PhoneFormattingService::helperText())
                                            ->maxLength(9)
                                            ->unique(ignoreRecord: true)
                                            ->live(debounce: 500)
                                            ->formatStateUsing(fn ($state) => PhoneFormattingService::formatStateUsing($state))
                                            ->dehydrateStateUsing(fn ($state) => PhoneFormattingService::dehydrateStateUsing($state)),

                                        TextInput::make('email')
                                            ->label('Email (Optional)')
                                            ->email()
                                            ->maxLength(191),
                                    ])
                                    ->columns(2),
                            ]),

                        // Tab 3 - Emergency & Spiritual
                        Tab::make(__('resources.member.tabs.emergency_spiritual'))
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Section::make(__('resources.member.sections.emergency_contact'))
                                    ->description(__('resources.member.sections.emergency_contact_description'))
                                    ->schema([
                                        TextInput::make('emergency_contact_name')
                                            ->label('Emergency Contact Name')
                                            ->required()
                                            ->maxLength(200),

                                        TextInput::make('emergency_contact_phone')
                                            ->label('Emergency Contact Phone')
                                            ->required()
                                            ->prefix(PhoneFormattingService::prefix())
                                            ->regex('/^[0-9]{9}$/')
                                            ->placeholder('912345678')
                                            ->helperText(PhoneFormattingService::helperText())
                                            ->maxLength(9)
                                            ->formatStateUsing(fn ($state) => PhoneFormattingService::formatStateUsing($state))
                                            ->dehydrateStateUsing(fn ($state) => PhoneFormattingService::dehydrateStateUsing($state)),
                                    ])
                                    ->columns(2),

                                Section::make(__('resources.member.sections.spiritual_information'))
                                    ->description(__('resources.member.sections.spiritual_information_description'))
                                    ->schema([
                                        TextInput::make('confession_father_name')
                                            ->label("Confession Father's Name")
                                            ->maxLength(200),

                                        TextInput::make('confession_father_phone')
                                            ->label("Confession Father's Phone")
                                            ->prefix(PhoneFormattingService::prefix())
                                            ->regex('/^[0-9]{9}$/')
                                            ->placeholder('912345678')
                                            ->helperText(PhoneFormattingService::helperText())
                                            ->maxLength(9)
                                            ->formatStateUsing(fn ($state) => PhoneFormattingService::formatStateUsing($state))
                                            ->dehydrateStateUsing(fn ($state) => PhoneFormattingService::dehydrateStateUsing($state)),
                                    ])
                                    ->columns(2),
                            ]),

                        // Tab 4 - Parent/Guardian (Kids + historical)
                        Tab::make(__('resources.member.tabs.parent_guardian'))
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
                                                    ->tel()
                                                    ->prefix(PhoneFormattingService::prefix())
                                                    ->regex('/^[0-9]{9}$/')
                                                    ->placeholder('912345678')
                                                    ->helperText(PhoneFormattingService::helperText())
                                                    ->maxLength(9)
                                                    ->formatStateUsing(fn ($state) => PhoneFormattingService::formatStateUsing($state))
                                                    ->dehydrateStateUsing(fn ($state) => PhoneFormattingService::dehydrateStateUsing($state)),
                                            ])
                                            ->columns(2)
                                            ->collapsible()
                                            ->itemLabel(
                                                fn (array $state): ?string => $state['parent_name'] ?? 'New Parent/Guardian'
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
                                                'Beginner' => 'Beginner',
                                                'Intermediate' => 'Intermediate',
                                                'Advanced' => 'Advanced',
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
                                                ->map(
                                                    fn ($pg) => "• **{$pg->parent_name}** ({$pg->relationship}) — ".
                                                    ($pg->phone ?? 'No phone')
                                                )
                                                ->join("\n") ?: 'No historical parent records found.')
                                            ->visible(fn ($record) => $record && $record->parentGuardians()->exists()),
                                    ])
                                    ->collapsed()
                                    ->visible(
                                        fn ($record, callable $get) => in_array($get('member_type'), ['Youth', 'Adult']) &&
                                        $record && $record->parentGuardians()->exists()
                                    ),
                            ])
                            ->visible(
                                fn ($record, callable $get) => $get('member_type') === 'Kids' ||
                                (in_array($get('member_type'), ['Youth', 'Adult']) && $record && $record->parentGuardians()->exists())
                            ),

                        // Tab 5 - Family & Occupation (Youth/Adult only)
                        Tab::make(__('resources.member.tabs.family_occupation'))
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

                                Section::make('Occupation')
                                    ->description('Educational and professional background details.')
                                    ->schema([
                                        CustomOptionSelect::make('occupation_status')
                                            ->label('Occupation Status')
                                            ->customOptions('occupation_status', [
                                                'Student' => 'Student',
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
                                                'Hired' => 'Hired',
                                                'Not Hired' => 'Not Hired',
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
                                                    ->separator(','),
                                            ])
                                            ->columns(2)
                                            ->collapsible()
                                            ->itemLabel(
                                                fn (array $state): ?string => $state['category'] ?? 'New Category'
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
                                                'Single' => 'Single',
                                                'Married' => 'Married',
                                            ])
                                            ->live(),

                                        DatePicker::make('marriage_year')
                                            ->label('Marriage Year')
                                            ->format('Y')
                                            ->displayFormat('Y')
                                            ->required(fn (callable $get) => $get('marital_status') === 'Married')
                                            ->visible(fn (callable $get) => $get('marital_status') === 'Married'),

                                        TextInput::make('spouse_name')
                                            ->label('Spouse Name')
                                            ->required(fn (callable $get) => $get('marital_status') === 'Married')
                                            ->maxLength(200)
                                            ->visible(fn (callable $get) => $get('marital_status') === 'Married'),

                                        TextInput::make('spouse_phone')
                                            ->label('Spouse Phone')
                                            ->prefix(PhoneFormattingService::prefix())
                                            ->regex('/^[0-9]{9}$/')
                                            ->placeholder('912345678')
                                            ->helperText(PhoneFormattingService::helperText())
                                            ->maxLength(9)
                                            ->visible(fn (callable $get) => $get('marital_status') === 'Married')
                                            ->formatStateUsing(fn ($state) => PhoneFormattingService::formatStateUsing($state))
                                            ->dehydrateStateUsing(fn ($state) => PhoneFormattingService::dehydrateStateUsing($state)),

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
                        Tab::make(__('resources.member.tabs.status_history'))
                            ->icon('heroicon-o-clock')
                            ->schema([
                                Section::make('Member Status')
                                    ->schema([
                                        CustomOptionSelect::make('status')
                                            ->label('Status')
                                            ->customOptions('member_status', [
                                                'Draft' => 'Draft',
                                                'Active' => 'Active',
                                                'Former' => 'Former',
                                            ])
                                            ->required()
                                            ->disabled(fn () => ! Auth::user()->hasRole(['hr_head', 'admin', 'superadmin'])),
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
                                                ->map(
                                                    fn ($assignment) => $assignment->group->name.' - '.
                                                    ($assignment->assigned_at?->format('M d, Y') ?? 'No date')
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
        if (! isset($data['parentGuardians']) || ! is_array($data['parentGuardians'])) {
            return;
        }

        app(\App\Actions\Members\SyncParentGuardiansAction::class)
            ->execute($record, $data['parentGuardians']);
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
                    ->label(__('resources.member.table.member_id'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label(__('resources.member.table.full_name'))
                    ->searchable(['first_name', 'father_name', 'grandfather_name']),

                Tables\Columns\TextColumn::make('member_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Kids' => 'info',
                        'Youth' => 'warning',
                        'Adult' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Draft' => 'gray',
                        'Member' => 'info',
                        'Active' => 'success',
                        'Former' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),

                Tables\Columns\TextColumn::make('currentGroupAssignment.group.name')
                    ->label('Current Group')
                    ->getStateUsing(fn (Member $record): string => $record->currentGroup?->name ?? 'Unassigned')
                    ->badge()
                    ->color(fn (Member $record): string => $record->currentGroup ? 'primary' : 'gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('resources.member.table.created_at'))
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

                Tables\Filters\SelectFilter::make('department')
                    ->label('Department')
                    ->options(
                        fn () => \App\Models\Department::query()
                        ->withoutGlobalScope(\App\Models\Scopes\DepartmentScope::class)
                        ->orderBy('name_en')
                        ->pluck('name_en', 'id')
                        ->toArray()
                    )
                    ->query(function (Builder $query, array $data): Builder {
                        $departmentId = $data['value'] ?? null;
                        if (blank($departmentId)) {
                            return $query;
                        }

                        return $query->where('department_id', $departmentId);
                    }),

                Tables\Filters\SelectFilter::make('group_id')
                    ->label('Filter by Group')
                    ->options(fn () => MemberGroup::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $groupId = $data['value'] ?? null;
                        if (blank($groupId)) {
                            return $query;
                        }

                        return $query->whereHas('currentGroupAssignment', function (Builder $q) use ($groupId): void {
                            $q->active()->where('group_id', $groupId);
                        });
                    }),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                // Use Tables\Actions for individual row actions
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
                Actions\RestoreAction::make(),

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
                    ->modalDescription(
                        fn ($record) => "Are you sure you want to remove {$record->full_name} from {$record->currentGroup->name}?"
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
                    ->mountUsing(fn (BulkAction $action) => BulkAssignmentValidationAction::validateSelectionLimit($action))
                    ->form([
                        Select::make('assigned_group_id')
                            ->label('Group')
                            ->options(fn () => MemberGroup::query()->active()->orderBy('name')->pluck('name', 'id'))
                            ->required(),

                        DatePicker::make('effective_from')
                            ->label('Effective From Date')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function (BulkAction $action, array $data): void {
                        if (! BulkAssignmentValidationAction::validateRequiredField($data, 'assigned_group_id', 'group')) {
                            $action->halt();
                        }

                        $memberIds = $action->getSelectedRecords()->pluck('id')->toArray();

                        BulkAssignToGroupJob::dispatch(
                            $memberIds,
                            $data['assigned_group_id'],
                            $data['effective_from'],
                            auth()->id()
                        );

                        Notification::make()
                            ->title('Assignment queued')
                            ->body('The group assignment is being processed in the background.')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),

                BulkAction::make('assign_to_department')
                    ->label('Assign to Department')
                    ->icon('heroicon-o-building-office')
                    ->deselectRecordsAfterCompletion()
                    ->visible(fn (): bool => Auth::user()?->hasRole(['hr_head', 'internal_relations_head', 'admin', 'superadmin']) ?? false)
                    ->mountUsing(fn (BulkAction $action) => BulkAssignmentValidationAction::validateSelectionLimit($action))
                    ->form([
                        Select::make('department_id')
                            ->label('Department')
                            ->options(function () {
                                try {
                                    $departments = \App\Models\Department::query()
                                        ->withoutGlobalScope(\App\Models\Scopes\DepartmentScope::class)
                                        ->where('is_active', true)
                                        ->orderBy('name_en')
                                        ->pluck('name_en', 'id')
                                        ->toArray();

                                    return $departments;
                                } catch (\Exception $e) {
                                    return [];
                                }
                            })
                            ->required()
                            ->helperText('Select: department to assign selected members to'),

                        Textarea::make('reason')
                            ->label('Reason for Assignment')
                            ->rows(2)
                            ->helperText('Optional: Provide a reason for this department assignment'),
                    ])
                    ->action(function (BulkAction $action, array $data): void {
                        if (! BulkAssignmentValidationAction::validateRequiredField($data, 'department_id', 'department')) {
                            $action->halt();
                        }

                        $memberIds = $action->getSelectedRecords()->pluck('id')->toArray();

                        BulkAssignToDepartmentJob::dispatch(
                            $memberIds,
                            $data['department_id'],
                            $data['reason'] ?? null,
                            auth()->id()
                        );

                        Notification::make()
                            ->title('Assignment queued')
                            ->body('The department assignment is being processed in the background.')
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label(__('resources.member.actions.new_member'))
                    ->icon('heroicon-o-plus')
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateHeading(__('resources.member.empty_state.heading'))
            ->emptyStateDescription(__('resources.member.empty_state.description'))
            ->emptyStateIcon('heroicon-o-users')
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
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
            'Phone' => $record->phone,
            'Type' => $record->member_type,
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'father_name', 'phone', 'member_code'];
    }
}
