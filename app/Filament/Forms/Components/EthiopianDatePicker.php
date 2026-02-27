<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\TextInput;
use Illuminate\Support\Carbon;

/**
 * Simple EthiopianDatePicker that just uses standard date input
 */
class EthiopianDatePicker extends TextInput
{
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->type('date')
            ->default(Carbon::now()->format('Y-m-d'));
    }
}

