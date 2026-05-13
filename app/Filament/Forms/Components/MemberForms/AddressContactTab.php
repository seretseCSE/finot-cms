<?php

namespace App\Filament\Forms\Components\MemberForms;

use App\Services\PhoneFormattingService;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;

class AddressContactTab
{
    public static function make(): Tab
    {
        return Tab::make('Address & Contact')
            ->icon('heroicon-o-map-pin')
            ->schema([
                Section::make('Residential Address')
                    ->description('Current address details')
                    ->schema([
                        Forms\Components\TextInput::make('city')
                            ->label('City')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('sub_city')
                            ->label('Sub-City')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('woreda')
                            ->label('Woreda')
                            ->required()
                            ->maxLength(50),

                        Forms\Components\TextInput::make('zone')
                            ->label('Ketena')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('block')
                            ->label('Block')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('neighborhood')
                            ->label('Neighborhood Specific Name')
                            ->maxLength(200),
                    ])
                    ->columns(3),

                Section::make('Contact Information')
                    ->description('Phone and email contact details')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
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

                        Forms\Components\TextInput::make('email')
                            ->label('Email (Optional)')
                            ->email()
                            ->maxLength(191),
                    ])
                    ->columns(2),
            ]);
    }
}
