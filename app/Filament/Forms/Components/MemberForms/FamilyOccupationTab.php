<?php

namespace App\Filament\Forms\Components\MemberForms;

use App\Enums\MaritalStatus;
use App\Enums\OccupationStatus;
use App\Services\PhoneFormattingService;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;

class FamilyOccupationTab
{
    public static function make(): Tab
    {
        return Tab::make('Family & Occupation')
            ->icon('heroicon-o-briefcase')
            ->schema([
                Section::make('Family Information')
                    ->description('Details about family size and spiritual background.')
                    ->schema([
                        Forms\Components\TextInput::make('family_size')
                            ->label('Total Family Size')
                            ->numeric()
                            ->minValue(1),

                        Forms\Components\TextInput::make('brothers_count')
                            ->label('Number of Brothers')
                            ->numeric()
                            ->minValue(0),

                        Forms\Components\TextInput::make('sisters_count')
                            ->label('Number of Sisters')
                            ->numeric()
                            ->minValue(0),

                        Forms\Components\TextInput::make('family_confession_father')
                            ->label('Family Confession Father Name')
                            ->maxLength(200),

                        Forms\Components\DatePicker::make('sunday_school_entry_year')
                            ->label('Sunday School Entry Year')
                            ->format('Y')
                            ->displayFormat('Y'),

                        Forms\Components\Textarea::make('past_service_departments')
                            ->label('Past Service Departments')
                            ->rows(3),
                    ])
                    ->columns(2),

                Section::make('Occupation')
                    ->description('Educational and professional background details.')
                    ->schema([
                        Forms\Components\Select::make('occupation_status')
                            ->label('Occupation Status')
                            ->options(OccupationStatus::getAll())
                            ->enum(OccupationStatus::class)
                            ->live(),

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
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['school_name'] ?? 'New Education Entry')
                            ->addActionLabel('+ Add Education'),

                        Forms\Components\Select::make('employment_status')
                            ->label('Employment Status')
                            ->options([
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

                        Forms\Components\Repeater::make('occupation_categories')
                            ->label('Occupation Categories & Subcategories')
                            ->schema([
                                Forms\Components\Select::make('category')
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

                                Forms\Components\TextInput::make('subcategories')
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
                        Forms\Components\Select::make('marital_status')
                            ->label('Marital Status')
                            ->options(MaritalStatus::getAll())
                            ->enum(MaritalStatus::class)
                            ->live(),

                        Forms\Components\DatePicker::make('marriage_year')
                            ->label('Marriage Year')
                            ->format('Y')
                            ->displayFormat('Y')
                            ->required(fn (callable $get) => $get('marital_status') === 'Married')
                            ->visible(fn (callable $get) => $get('marital_status') === 'Married'),

                        Forms\Components\TextInput::make('spouse_name')
                            ->label('Spouse Name')
                            ->required(fn (callable $get) => $get('marital_status') === 'Married')
                            ->maxLength(200)
                            ->visible(fn (callable $get) => $get('marital_status') === 'Married'),

                        Forms\Components\TextInput::make('spouse_phone')
                            ->label('Spouse Phone')
                            ->prefix(PhoneFormattingService::prefix())
                            ->regex('/^[0-9]{9}$/')
                            ->placeholder('912345678')
                            ->helperText(PhoneFormattingService::helperText())
                            ->maxLength(9)
                            ->visible(fn (callable $get) => $get('marital_status') === 'Married')
                            ->formatStateUsing(fn ($state) => PhoneFormattingService::formatStateUsing($state))
                            ->dehydrateStateUsing(fn ($state) => PhoneFormattingService::dehydrateStateUsing($state)),

                        Forms\Components\Repeater::make('children')
                            ->label('Children Information')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Child Name')
                                    ->required()
                                    ->maxLength(200),

                                Forms\Components\DatePicker::make('birth_date')
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
            ->visible(fn (callable $get) => in_array($get('member_type'), ['Youth', 'Adult']));
    }
}
