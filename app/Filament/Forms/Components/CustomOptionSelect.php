<?php

namespace App\Filament\Forms\Components;

use App\Models\CustomOption;
use Filament\Forms;
use Filament\Forms\Components\Select;
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
                ->pluck('option_value')
                ->all();

            $pending = CustomOption::query()
                ->pending()
                ->where('field_name', $this->fieldName)
                ->orderByRaw('display_order is null')
                ->orderBy('display_order')
                ->orderBy('added_at', 'desc')
                ->pluck('option_value')
                ->all();

            $options = [];

            foreach (array_values(array_unique($predefinedValues)) as $v) {
                $options[$v] = $v;
            }

            foreach ($approved as $v) {
                $options[$v] = $v;
            }

            foreach ($pending as $v) {
                $options[$v] = $v . ' (Pending)';
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
                    ->pluck('option_value')
                    ->all(),
            )));

            if (! in_array($state, $known, true)) {
                $set($this->otherInputName, $state);
                $component->state($this->otherKey);
            }
        });

        $this->dehydrateStateUsing(function ($state, callable $get): ?string {
            if ($state === $this->otherKey) {
                $otherValue = trim((string) $get($this->otherInputName));

                if (blank($otherValue)) {
                    return null;
                }

                CustomOption::query()->firstOrCreate([
                    'field_name' => $this->fieldName,
                    'option_value' => $otherValue,
                ], [
                    'status' => 'pending',
                    'added_by' => Auth::id(),
                    'added_at' => now(),
                    'usage_count' => 0,
                ]);

                return $otherValue;
            }

            if (filled($state)) {
                CustomOption::recordUsage($this->fieldName, (string) $state);
            }

            return filled($state) ? (string) $state : null;
        });
    }

    /**
     * @param  array<string, string>  $predefinedOptions
     */
    public function customOptions(string $fieldName, array $predefinedOptions = []): static
    {
        $this->fieldName = $fieldName;
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
     * Convenience helper to render Select + inline "Other" TextInput.
     *
     * @param  array<string, string>  $predefinedOptions
     */
    public static function makeWithOther(string $name, string $fieldName, array $predefinedOptions = []): Forms\Components\Group
    {
        $select = static::make($name)
            ->label(Str::of($name)->replace('_', ' ')->title()->toString())
            ->customOptions($fieldName, $predefinedOptions);

        return Forms\Components\Group::make([
            $select,
            $select->getOtherTextInput(),
        ])->columns(1);
    }
}

