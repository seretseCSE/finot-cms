<?php

namespace App\Filament\Resources\MemberResource\Forms;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class AddressContactForm
{
    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function make(): array
    {
        return [
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
                        ->prefix(config('finot.phone_prefix', '+251'))
                        ->regex('/^[0-9]{9}$/')
                        ->placeholder('912345678')
                        ->helperText('Enter 9 digits after '.config('finot.phone_prefix', '+251'))
                        ->maxLength(9)
                        ->unique(ignoreRecord: true)
                        ->live(debounce: 500)
                        ->formatStateUsing(function ($state) {
                            $prefix = config('finot.phone_prefix', '+251');

                            return $state ? preg_replace('/^('.preg_quote($prefix, '/').'|0)/', '', $state) : null;
                        })
                        ->dehydrateStateUsing(fn ($state) => $state ? config('finot.phone_prefix', '+251').$state : null),

                    TextInput::make('email')
                        ->label('Email (Optional)')
                        ->email()
                        ->maxLength(191),
                ])
                ->columns(2),
        ];
    }
}
