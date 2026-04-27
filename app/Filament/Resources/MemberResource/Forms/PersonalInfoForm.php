<?php

namespace App\Filament\Resources\MemberResource\Forms;

use App\Filament\Forms\Components\CustomOptionSelect;
use App\Services\UploadSanitizer;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

class PersonalInfoForm
{
    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function make(): array
    {
        return [
            Section::make('Basic Information')
                ->description('Primary personal identifiers and demographic details.')
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
        ];
    }
}
