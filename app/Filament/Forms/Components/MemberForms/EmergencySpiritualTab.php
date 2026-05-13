<?php

namespace App\Filament\Forms\Components\MemberForms;

use App\Services\PhoneFormattingService;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;

class EmergencySpiritualTab
{
    public static function make(): Tab
    {
        return Tab::make('Emergency & Spiritual')
            ->icon('heroicon-o-phone')
            ->schema([
                Section::make('Emergency Contact')
                    ->description('Contact person for emergencies')
                    ->schema([
                        Forms\Components\TextInput::make('emergency_contact_name')
                            ->label('Emergency Contact Name')
                            ->required()
                            ->maxLength(200),

                        Forms\Components\TextInput::make('emergency_contact_phone')
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

                Section::make('Spiritual Information')
                    ->description('Confession father and spiritual details')
                    ->schema([
                        Forms\Components\TextInput::make('confession_father_name')
                            ->label("Confession Father's Name")
                            ->maxLength(200),

                        Forms\Components\TextInput::make('confession_father_phone')
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
            ]);
    }
}
