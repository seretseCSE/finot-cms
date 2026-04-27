<?php

namespace App\Filament\Resources\MemberResource\Forms;

use App\Filament\Forms\Components\CustomOptionSelect;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

class EmploymentEducationForm
{
    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function make(): array
    {
        return [
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
                                ->prefix(config('finot.phone_prefix', '+251'))
                                ->regex('/^[0-9]{9}$/')
                                ->placeholder('912345678')
                                ->helperText('Enter 9 digits after '.config('finot.phone_prefix', '+251'))
                                ->maxLength(9)
                                ->formatStateUsing(function ($state) {
                                    $prefix = config('finot.phone_prefix', '+251');

                                    return $state ? preg_replace('/^('.preg_quote($prefix, '/').'|0)/', '', $state) : null;
                                })
                                ->dehydrateStateUsing(fn ($state) => $state ? config('finot.phone_prefix', '+251').$state : null),
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
                                fn ($pg) => '• **'.$pg->parent_name.'** ('.$pg->relationship.') — '.
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
                        ->prefix(config('finot.phone_prefix', '+251'))
                        ->regex('/^[0-9]{9}$/')
                        ->placeholder('912345678')
                        ->helperText('Enter 9 digits after '.config('finot.phone_prefix', '+251'))
                        ->maxLength(9)
                        ->visible(fn (callable $get) => $get('marital_status') === 'Married')
                        ->formatStateUsing(function ($state) {
                            $prefix = config('finot.phone_prefix', '+251');

                            return $state ? preg_replace('/^('.preg_quote($prefix, '/').'|0)/', '', $state) : null;
                        })
                        ->dehydrateStateUsing(fn ($state) => $state ? config('finot.phone_prefix', '+251').$state : null),

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
        ];
    }
}
