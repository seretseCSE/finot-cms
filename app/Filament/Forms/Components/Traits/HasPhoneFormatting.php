<?php

namespace App\Filament\Forms\Components\Traits;

use App\Services\PhoneFormattingService;
use Filament\Forms\Components\TextInput;

trait HasPhoneFormatting
{
    /**
     * Create a phone input field with proper formatting
     */
    public static function phoneInput(string $name, string $label = null, bool $required = false): TextInput
    {
        $field = TextInput::make($name)
            ->label($label ?? 'Phone Number')
            ->tel()
            ->prefix(PhoneFormattingService::prefix())
            ->regex('/^[0-9]{9}$/')
            ->placeholder('912345678')
            ->helperText(PhoneFormattingService::helperText())
            ->maxLength(9)
            ->formatStateUsing(fn ($state) => PhoneFormattingService::formatStateUsing($state))
            ->dehydrateStateUsing(fn ($state) => PhoneFormattingService::dehydrateStateUsing($state));

        if ($required) {
            $field->required();
        }

        return $field;
    }

    /**
     * Create a phone input field with unique validation
     */
    public static function uniquePhoneInput(string $name, string $label = null, bool $required = false): TextInput
    {
        return static::phoneInput($name, $label, $required)
            ->unique(ignoreRecord: true);
    }
}
