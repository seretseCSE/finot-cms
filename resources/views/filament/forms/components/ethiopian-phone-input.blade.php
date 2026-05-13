@props([
    'id' => null,
    'name' => null,
    'label' => 'Phone Number',
    'required' => false,
    'unique' => null,
    'disabled' => false,
    'live' => false,
    'debounce' => null,
    'afterStateUpdated' => null,
])

{{ $this->form->textInput($name)
    ->id($id)
    ->label($label)
    ->tel()
    ->prefix(PhoneFormattingService::prefix())
    ->regex('/^[0-9]{9}$/')
    ->placeholder('912345678')
    ->helperText(PhoneFormattingService::helperText())
    ->maxLength(9)
    ->required($required)
    ->unique($unique)
    ->disabled($disabled)
    ->live($live, $debounce)
    ->afterStateUpdated($afterStateUpdated)
    ->formatStateUsing(fn ($state) => PhoneFormattingService::formatStateUsing($state))
    ->dehydrateStateUsing(fn ($state) => PhoneFormattingService::dehydrateStateUsing($state))
}}
