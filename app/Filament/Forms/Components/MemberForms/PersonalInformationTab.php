<?php

namespace App\Filament\Forms\Components\MemberForms;

use App\Enums\MemberType;
use App\Services\UploadSanitizer;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;

class PersonalInformationTab
{
    public static function make(): Tab
    {
        return Tab::make('Personal Information')
            ->icon('heroicon-o-user')
            ->schema([
                Section::make('Basic Information')
                    ->description('Name, type, and gender are required. Other details can be added later.')
                    ->schema([
                        Forms\Components\Select::make('title')
                            ->label('Title')
                            ->options([
                                'Dn.' => 'Dn.',
                                'Mr.' => 'Mr.',
                                'Mrs.' => 'Mrs.',
                                'Ms.' => 'Ms.',
                                'Dr.' => 'Dr.',
                                'Kesis' => 'Kesis',
                            ])
                            ->live(),

                        Forms\Components\Select::make('member_type')
                            ->label('Member Type')
                            ->options(MemberType::getAll())
                            ->enum(MemberType::class)
                            ->required()
                            ->live(),

                        Forms\Components\DatePicker::make('member_since')
                            ->label('Member Since')
                            ->date()
                            ->required()
                            ->default(now())
                            ->helperText('Date when member formally became a member'),

                        Forms\Components\TextInput::make('first_name')
                            ->label('First Name')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('father_name')
                            ->label("Father's Name")
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('grandfather_name')
                            ->label("Grandfather's Name")
                            ->maxLength(100),

                        Forms\Components\TextInput::make('mother_name')
                            ->label("Mother's Name")
                            ->maxLength(100),

                        Forms\Components\DatePicker::make('date_of_birth')
                            ->label('Date of Birth')
                            ->maxDate(now()),

                        Forms\Components\Radio::make('gender')
                            ->label('Gender')
                            ->options([
                                'Male' => 'Male',
                                'Female' => 'Female',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('christian_name')
                            ->label('Baptism Name')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('member_code')
                            ->label('Member Code')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\FileUpload::make('photo')
                            ->disk('members')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                            ->maxSize(5120)
                            ->saveUploadedFileUsing(UploadSanitizer::saveCallback('members', 'members', ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])),

                        Forms\Components\Toggle::make('consent_for_photography')
                            ->label('Parent/Guardian has given consent for photography'),
                    ])
                    ->columns(3),
            ]);
    }
}
