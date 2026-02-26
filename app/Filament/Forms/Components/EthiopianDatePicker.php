<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\TextInput;
use Illuminate\Support\Carbon;

/**
 * Simple EthiopianDatePicker that just uses standard date input
 */
class EthiopianDatePicker extends TextInput
{
    protected string $view = 'filament.forms.components.ethiopian-date-picker';

    public function __construct(string $name)
    {
        parent::__construct($name);
        
        $this->type('date')
            ->default(Carbon::now()->format('Y-m-d'));
    }
}

