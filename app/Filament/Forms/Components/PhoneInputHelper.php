<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\TextInput;

class PhoneInputHelper
{
    /**
     * Create a standardized phone input field with Ethiopian formatting
     */
    public static function ethiopian(string $name, string $label = 'Phone Number'): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->tel()
            ->prefix(config('finot.phone_prefix', '+251'))
            ->regex('/^[0-9]{9}$/')
            ->placeholder('912345678')
            ->helperText('Enter 9 digits after '.config('finot.phone_prefix', '+251'))
            ->maxLength(9);
    }

    /**
     * Add phone formatting to an existing TextInput
     */
    public static function addFormatting(TextInput $field): TextInput
    {
        return $field
            ->formatStateUsing(function ($state) {
                $prefix = config('finot.phone_prefix', '+251');
                return $state ? preg_replace('/^(' . preg_quote($prefix, '/') . '|0)/', '', $state) : null;
            })
            ->dehydrateStateUsing(fn ($state) => $state ? config('finot.phone_prefix', '+251').$state : null);
    }

    /**
     * Create phone input with PhoneFormattingService (for MemberResource compatibility)
     */
    public static function withService(string $name, string $label = 'Phone Number'): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->tel()
            ->prefix(\App\Services\PhoneFormattingService::prefix())
            ->regex('/^[0-9]{9}$/')
            ->placeholder('912345678')
            ->helperText(\App\Services\PhoneFormattingService::helperText())
            ->maxLength(9)
            ->formatStateUsing(fn ($state) => \App\Services\PhoneFormattingService::formatStateUsing($state))
            ->dehydrateStateUsing(fn ($state) => \App\Services\PhoneFormattingService::dehydrateStateUsing($state));
    }
}
