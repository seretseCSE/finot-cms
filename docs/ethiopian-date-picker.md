# Ethiopian DatePicker Component

## Overview
The EthiopianDatePicker is a custom Filament form component that handles Ethiopian calendar dates with full conversion between Ethiopian and Gregorian calendars.

## Features
- Ethiopian year/month/day dropdowns
- Automatic Ethiopian ↔ Gregorian conversion
- Client-side validation
- Bilingual support (Amharic/English)
- Pagume month handling
- Database storage in Gregorian format

## Basic Usage

```php
use App\Filament\Forms\Components\EthiopianDatePicker;

EthiopianDatePicker::make('birth_date')
    ->label('Birth Date')
    ->required(),
```

## Modifiers

### excludePagume()
Removes Pagume (13th month) from month dropdown - useful for contribution dates.

```php
EthiopianDatePicker::make('contribution_date')
    ->excludePagume() // Only shows months 1-12
    ->required(),
```

### showAllMonths()
Shows all 13 months including Pagume (default behavior).

```php
EthiopianDatePicker::make('event_date')
    ->showAllMonths() // Shows months 1-13
    ->required(),
```

### locale()
Set the display language for month names.

```php
EthiopianDatePicker::make('date')
    ->locale('en') // Show English month names
    ->required(),

EthiopianDatePicker::make('date')
    ->locale('am') // Show Amharic month names (default)
    ->required(),
```

## Validation

### Using Custom Rule
```php
use App\Rules\EthiopianDateRule;

// Basic Ethiopian date validation
'date' => ['required', new EthiopianDateRule()],

// Exclude Pagume month (for contributions)
'contribution_date' => ['required', EthiopianDateRule::excludePagume()],
```

### Form Validation
```php
protected function getFormSchema(): array
{
    return [
        EthiopianDatePicker::make('ethiopian_date')
            ->label('Ethiopian Date')
            ->required()
            ->rules([
                function ($attribute, $value, $fail) {
                    if ($value && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                        $fail('Date must be in YYYY-MM-DD format.');
                    }
                }
            ]),
    ];
}
```

## Database Storage
- Dates are automatically converted to Gregorian format (Y-m-d) before saving
- Dates are automatically converted back to Ethiopian format when loading forms
- No special database column handling needed - use regular DATE columns

## Examples

### Church Member Registration
```php
EthiopianDatePicker::make('birth_date')
    ->label('Date of Birth')
    ->locale('am')
    ->required()
    ->helperText('Enter your birth date in Ethiopian calendar'),

EthiopianDatePicker::make('baptism_date')
    ->label('Baptism Date')
    ->excludePagume() // Baptisms typically not in Pagume
    ->required(),
```

### Contribution Tracking
```php
EthiopianDatePicker::make('contribution_date')
    ->label('Contribution Date')
    ->excludePagume() // Contributions only in regular months
    ->required()
    ->default(fn() => now()->ethiopian()),
```

### Event Planning
```php
EthiopianDatePicker::make('event_date')
    ->label('Event Date')
    ->showAllMonths() // Allow events in any month
    ->required()
    ->locale('en'), // English for international events
```

## Client-Side Features

### Automatic Validation
- Days limited to 1-30 for months 1-12
- Days limited to 1-5/6 for Pagume based on leap year
- Prevents invalid date combinations

### Dynamic Updates
- Day dropdown updates when month/year changes
- Leap year consideration for Pagume month
- Real-time date validation

### Bilingual Support
- Month names change based on locale setting
- Uses session locale or defaults to Amharic
- Supports both Amharic and English labels

## Helper Methods

The component provides several helper methods:

```php
// Get current Ethiopian year
$component->getCurrentEthiopianYear();

// Get month name in specific locale
$component->getMonthName(9, 'am'); // "ጥቅምት"
$component->getMonthName(9, 'en'); // "Tikimt"

// Validate Ethiopian date
$component->validateEthiopianDate(2016, 9, 15); // true/false

// Get max days for month
$component->getMaxDaysForMonth(2016, 13); // 6 (leap year)
$component->getMaxDaysForMonth(2017, 13); // 5 (non-leap year)
```

## Styling
The component uses standard Filament styling classes and automatically adapts to:
- Light/dark mode
- Filament color scheme
- Responsive design
- Accessibility features

## Integration with EthiopianDateHelper
The component integrates seamlessly with the EthiopianDateHelper for:
- Date conversion
- Validation
- Month name retrieval
- Leap year calculation
