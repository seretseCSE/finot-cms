@php
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
?>

<div class="space-y-4">
    <div class="text-sm text-gray-600 dark:text-gray-400 mb-4">
        {{ __('Enter your phone number or email address') }}
    </div>
    
    <div class="grid grid-cols-1 gap-4">
        <div class="col-span-full">
            {{ 
                Filament::form($this->getForm())
                    ->schema([
                        TextInput::make('login')
                            ->label(__('Phone Number or Email'))
                            ->placeholder('+251911000001 or email@example.com')
                            ->required()
                            ->autocomplete('username')
                            ->prefixIcon('heroicon-o-user')
                            ->helperText(__('You can use either your phone number or email address')),
                    ])
                    ->statePath('data.login')
            }}
        </div>
    </div>
    
    <div class="text-xs text-gray-500 dark:text-gray-400">
        {{ __('Phone numbers should include country code (e.g., +251...)') }}
    </div>
</div>
