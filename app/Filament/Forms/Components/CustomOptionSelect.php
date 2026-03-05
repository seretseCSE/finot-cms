<?php

namespace App\Filament\Forms\Components;

use App\Models\CustomOption;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Group;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CustomOptionSelect extends Select
{
    protected string $fieldName;

    /**
     * @var array<string, string>
     */
    protected array $predefinedOptions = [];

    protected string $otherKey = '__other__';

    protected string $otherInputName;

    protected function setUp(): void
    {
        parent::setUp();

        $this->searchable();
        $this->preload();
        $this->live();

        $this->options(function (callable $get): array {
            $predefinedValues = array_values($this->predefinedOptions);

            $approved = CustomOption::query()
                ->approved()
                ->where('field_name', $this->fieldName)
                ->orderByRaw('display_order is null')
                ->orderBy('display_order')
                ->orderBy('usage_count', 'desc')
                ->orderBy('option_value')
                ->get();

            $pending = CustomOption::query()
                ->pending()
                ->where('field_name', $this->fieldName)
                ->orderByRaw('display_order is null')
                ->orderBy('display_order')
                ->orderBy('added_at', 'desc')
                ->get();

            $options = [];

            foreach (array_values(array_unique($predefinedValues)) as $v) {
                $options[$v] = $v;
            }

            foreach ($approved as $option) {
                $options[$option->option_value] = $option->option_value;
            }

            foreach ($pending as $option) {
                $options[$option->option_value] = $option->option_value . ' (Pending)';
            }

            $options[$this->otherKey] = 'Other';

            $currentOther = $get($this->otherInputName);
            if (filled($currentOther)) {
                $options[$currentOther] = $currentOther . ' (Pending)';
            }

            return $options;
        });

        $this->afterStateHydrated(function (Select $component, $state, callable $set): void {
            if (blank($state)) {
                return;
            }

            $known = array_values(array_unique(array_merge(
                array_values($this->predefinedOptions),
                CustomOption::query()
                    ->where('field_name', $this->fieldName)
                    ->whereIn('status', ['approved', 'pending']) 
                    ->get()->pluck('option_value')->toArray(),
            )));

            if (! in_array($state, $known, true)) {
                $set($this->otherInputName, $state);
                $component->state($this->otherKey);
            }
        });

        // dehydrateStateUsing: ONLY transform value, no DB writes.
        // DB writes (pending option creation, usage tracking) happen in saveUsageAndPending().
        $this->dehydrateStateUsing(function ($state, callable $get): ?string {
            if ($state === $this->otherKey) {
                $otherValue = trim((string) $get($this->otherInputName));
                return blank($otherValue) ? null : $otherValue;
            }

            return filled($state) ? (string) $state : null;
        });
    }

    /**
     * @param  array<string, string>  $predefinedOptions
     */
    public function customOptions(string $fieldName, array $predefinedOptions = []): static
    {
        $this->fieldName      = $fieldName;
        $this->predefinedOptions = $predefinedOptions;
        $this->otherInputName = $this->getName() . '_other';

        return $this;
    }

    public function getOtherInputName(): string
    {
        return $this->otherInputName;
    }

    public function getOtherTextInput(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($this->otherInputName)
            ->label('Other')
            ->maxLength(255)
            ->visible(fn (callable $get): bool => $get($this->getName()) === $this->otherKey)
            ->live(debounce: 500);
    }

    /**
     * Convenience helper: renders Select + inline "Other" TextInput.
     *
     * Returns a Filament\Schemas\Components\Group (Filament 5).
     *
     * @param  array<string, string>  $predefinedOptions
     */
    /**
     * Call this from your resource's afterCreate() / afterSave() to persist
     * pending custom options and record usage counts.
     * e.g. CustomOptionSelect::saveUsageAndPending($this->form->getState());
     *
     * @param array<string, mixed> $formState
     */
    public static function saveUsageAndPending(array $formState, array $fields): void
    {
        foreach ($fields as $fieldName => $stateName) {
            $value = $formState[$stateName] ?? null;
            if (blank($value)) continue;

            // Create pending option if it doesn't already exist
            $exists = CustomOption::query()
                ->where('field_name', $fieldName)
                ->where('option_value', $value)
                ->exists();

            if (!$exists) {
                CustomOption::query()->create([
                    'field_name'   => $fieldName,
                    'option_value' => $value,
                    'status'       => 'pending',
                    'added_by'     => Auth::id(),
                    'added_at'     => now(),
                    'usage_count'  => 0,
                ]);
            } else {
                // Find and increment usage count of existing option
                $option = CustomOption::query()
                    ->where('field_name', $fieldName)
                    ->where('option_value', $value)
                    ->first();
                if ($option) {
                    $option->incrementUsage();
                }
            }
        }
    }

        public static function makeWithOther(
        string $name,
        string $fieldName,
        array  $predefinedOptions = [],
        bool   $required = false,
    ): Group {
        $select = static::make($name)
            ->label(Str::of($name)->replace('_', ' ')->title()->toString())
            ->customOptions($fieldName, $predefinedOptions);

        if ($required) {
            $select->required();
        }

        return Group::make([
            $select,
            $select->getOtherTextInput(),
        ])->columns(1);
    }
}
