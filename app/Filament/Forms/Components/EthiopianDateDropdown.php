<?php

namespace App\Filament\Forms\Components;

use App\Helpers\EthiopianDateHelper;
use Filament\Forms\Components\Field;

class EthiopianDateDropdown extends Field
{
    protected string $view = 'filament.forms.components.ethiopian-date-dropdown';

    protected int $yearsBefore = 5;

    protected int $yearsAfter = 3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->afterStateHydrated(function (Field $component): void {
            $state = $component->getState();

            if ($state !== null && ! is_array($state)) {
                try {
                    $et = EthiopianDateHelper::toEthiopian($state);
                    $component->state([
                        'year' => (int) $et['year'],
                        'month' => (int) $et['month'],
                        'day' => (int) $et['day'],
                    ]);
                } catch (\Exception $e) {
                    $component->state(['year' => null, 'month' => null, 'day' => null]);
                }

                return;
            }

            if (! is_array($state) || ! isset($state['year'], $state['month'], $state['day'])) {
                $component->state(['year' => null, 'month' => null, 'day' => null]);
            }
        });

        $this->dehydrateStateUsing(function ($state) {
            if (! is_array($state) || empty($state['year']) || empty($state['month']) || empty($state['day'])) {
                return null;
            }

            return (new EthiopianDateHelper())->toGregorian(
                (int) $state['day'],
                (int) $state['month'],
                (int) $state['year']
            )->format('Y-m-d');
        });

        $this->rule(function (Field $component): \Closure {
            return function (string $attribute, $value, \Closure $fail) {
                if (! $component->isRequired()) {
                    return;
                }

                if (! is_array($value) || empty($value['year']) || empty($value['month']) || empty($value['day'])) {
                    $fail(__('validation.required', ['attribute' => $component->getLabel()]));
                }
            };
        });
    }

    public function defaultToday(): static
    {
        $today = new \Andegna\DateTime();

        $this->default([
            'year' => $today->getYear(),
            'month' => $today->getMonth(),
            'day' => $today->getDay(),
        ]);

        return $this;
    }

    public function yearsBefore(int $count): static
    {
        $this->yearsBefore = $count;

        return $this;
    }

    public function yearsAfter(int $count): static
    {
        $this->yearsAfter = $count;

        return $this;
    }

    public function getYearsBefore(): int
    {
        return $this->yearsBefore;
    }

    public function getYearsAfter(): int
    {
        return $this->yearsAfter;
    }
}
