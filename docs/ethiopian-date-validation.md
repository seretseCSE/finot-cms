# Ethiopian Date Validation

## Overview
The Ethiopian date validation system provides comprehensive validation for Ethiopian calendar dates with bilingual error messages and flexible options.

## Validation Rule Registration

The validation rule is registered in `AppServiceProvider` as `ethiopian_date` and supports parameters for customization.

## Usage Examples

### Basic Validation
```php
// In Form Request
public function rules(): array
{
    return [
        'birth_date' => 'required|ethiopian_date',
        'wedding_date' => 'nullable|ethiopian_date',
    ];
}

// In Controller
$request->validate([
    'event_date' => 'required|ethiopian_date',
]);
```

### With Parameters
```php
// Exclude Pagume month (for contributions)
'contribution_date' => 'required|ethiopian_date:exclude_pagume',

// Specify locale
'birth_date' => 'required|ethiopian_date:locale,en',

// Multiple parameters
'contribution_date' => 'required|ethiopian_date:exclude_pagume,locale,am',
```

### Using Rule Class
```php
use App\Rules\EthiopianDateRule;

// Basic validation
'birth_date' => ['required', new EthiopianDateRule()],

// Exclude Pagume
'contribution_date' => ['required', EthiopianDateRule::excludePagume()],

// With locale
'birth_date' => ['required', EthiopianDateRule::locale('en')],

// Combined
'contribution_date' => ['required', EthiopianDateRule::excludePagume('am')],
```

## Validation Rules

### Format Validation
- **Required Format**: YYYY-MM-DD
- **Example**: 2016-09-15 (15 Tikimt 2016)

### Year Range Validation
- **Valid Range**: 1900-2100 Ethiopian calendar
- **Error Messages**:
  - Amharic: "የ :attribute ዓመት 1900 እስከ 2100 የኢትዮጵያ ካሌንደር መሆን አለበት።"
  - English: "The :attribute year must be between 1900 and 2100 Ethiopian calendar."

### Month Validation
- **Valid Range**: 1-13
- **Error Messages**:
  - Amharic: "የ :attribute ወር 1 እስከ 13 መሆን አለበት።"
  - English: "The :attribute month must be between 1 and 13."

### Day Validation
- **Regular Months (1-12)**: 1-30 days
- **Pagume Month (13)**: 1-5 days (6 in leap year)
- **Error Messages**:
  - Regular months: "The :attribute day must be between 1 and 30 for regular months."
  - Pagume month: "The :attribute day must be between 1 and 5 for Pagume month (6 in leap year)."

### Pagume Exclusion
- **Purpose**: For contribution dates and financial calculations
- **Error Messages**:
  - Amharic: "የ :attribute ለዚህ መስክ በጳጉሜን ወር መሆን አይችልም።"
  - English: "The :attribute cannot be in Pagume month for this field."

## Error Messages

### Bilingual Support
All error messages are available in both Amharic and English:

```php
// Format errors
'am' => 'The :attribute must be in Ethiopian date format (YYYY-MM-DD).'
'en' => 'The :attribute must be in Ethiopian date format (YYYY-MM-DD).'

// Year range errors
'am' => 'The :attribute year must be between 1900 and 2100 Ethiopian calendar.'
'en' => 'The :attribute year must be between 1900 and 2100 Ethiopian calendar.'

// Month errors
'am' => 'The :attribute month must be between 1 and 13.'
'en' => 'The :attribute month must be between 1 and 13.'

// Day errors
'am' => 'The :attribute day must be between 1 and 30 for regular months.'
'en' => 'The :attribute day must be between 1 and 30 for regular months.'

// Pagume exclusion errors
'am' => 'The :attribute cannot be in Pagume month for this field.'
'en' => 'The :attribute cannot be in Pagume month for this field.'

// Pagume day errors
'am' => 'The :attribute day must be between 1 and 5 for Pagume month (6 in leap year).'
'en' => 'The :attribute day must be between 1 and 5 for Pagume month (6 in leap year).'
```

## Real-World Examples

### Church Member Registration
```php
class MemberRegistrationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'birth_date' => 'required|ethiopian_date',
            'baptism_date' => 'nullable|ethiopian_date',
            'marriage_date' => 'nullable|ethiopian_date',
            'language_preference' => 'required|in:am,en',
        ];
    }
}
```

### Contribution Tracking
```php
class ContributionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'member_id' => 'required|exists:members,id',
            'amount' => 'required|numeric|min:0',
            'contribution_date' => 'required|ethiopian_date:exclude_pagume',
            'type' => 'required|in:tithe,offering,building,fund',
        ];
    }
}
```

### Event Planning
```php
class EventRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'event_date' => 'required|ethiopian_date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ];
    }
}
```

### Custom Validation with Locale
```php
class CustomFormRequest extends FormRequest
{
    public function rules(): array
    {
        $locale = $this->input('language_preference', 'am');
        
        return [
            'ethiopian_date' => [
                'required',
                new EthiopianDateRule(false, $locale)
            ],
            'contribution_date' => [
                'required',
                EthiopianDateRule::excludePagume($locale)
            ],
        ];
    }
}
```

## Integration with Filament

### Form Validation
```php
protected function getFormSchema(): array
{
    return [
        EthiopianDatePicker::make('birth_date')
            ->label('Birth Date')
            ->required()
            ->rules(['ethiopian_date']),
            
        EthiopianDatePicker::make('contribution_date')
            ->label('Contribution Date')
            ->excludePagume()
            ->required()
            ->rules(['ethiopian_date:exclude_pagume']),
    ];
}
```

### Table Validation
```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            // ... other columns
        ])
        ->rules([
            'birth_date' => ['ethiopian_date'],
            'contribution_date' => ['ethiopian_date:exclude_pagume'],
        ]);
}
```

## Testing

### Unit Tests
```php
public function test_ethiopian_date_validation()
{
    $validator = Validator::make([
        'date' => '2016-09-15'
    ], [
        'date' => 'ethiopian_date'
    ]);

    $this->assertTrue($validator->passes());
}

public function test_invalid_ethiopian_date()
{
    $validator = Validator::make([
        'date' => '2016-14-15' // Invalid month
    ], [
        'date' => 'ethiopian_date'
    ]);

    $this->assertTrue($validator->fails());
    $this->assertStringContains('month', $validator->errors()->first('date'));
}

public function test_pagume_exclusion()
{
    $validator = Validator::make([
        'date' => '2016-13-05' // Pagume month
    ], [
        'date' => 'ethiopian_date:exclude_pagume'
    ]);

    $this->assertTrue($validator->fails());
    $this->assertStringContains('Pagume', $validator->errors()->first('date'));
}
```

### Feature Tests
```php
public function test_member_registration_with_ethiopian_date()
{
    $response = $this->post('/members', [
        'name' => 'John Doe',
        'birth_date' => '2016-09-15', // Valid Ethiopian date
        'language_preference' => 'am'
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('members', [
        'name' => 'John Doe',
        'birth_date' => '2024-01-15' // Converted to Gregorian
    ]);
}
```

## Performance Considerations

- The EthiopianDateHelper is registered as a singleton
- Validation rules are cached by Laravel
- Minimal overhead for format validation
- Efficient regex pattern matching

## Security

- Strict format validation prevents injection
- Year range validation prevents unrealistic dates
- Day validation ensures calendar accuracy
- No SQL injection risks in validation logic
