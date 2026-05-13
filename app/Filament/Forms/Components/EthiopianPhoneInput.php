<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\TextInput;
use App\Services\PhoneFormattingService;

class EthiopianPhoneInput extends TextInput
{
    protected string $view = 'filament.forms.components.ethiopian-phone-input';

    public static function make(string $name): static
    {
        $static = new static($name);
        $static->configure();
        return $static;
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->label('Phone Number')
            ->tel()
            ->prefix(PhoneFormattingService::prefix())
            ->regex('/^[0-9]{9}$/')
            ->placeholder('912345678')
            ->helperText(PhoneFormattingService::helperText())
            ->maxLength(9)
            ->formatStateUsing(fn ($state) => PhoneFormattingService::formatStateUsing($state))
            ->dehydrateStateUsing(fn ($state) => PhoneFormattingService::dehydrateStateUsing($state));
    }

    public function required(bool|callable $condition = true): static
    {
        $this->required($condition);
        return $this;
    }

    public function unique(string|callable|null $condition = null, ?string $column = null, ?string $table = null): static
    {
        $this->unique($condition, $column, $table);
        return $this;
    }

    public function disabled(bool|callable $condition = true): static
    {
        $this->disabled($condition);
        return $this;
    }

    public function live(bool|callable $condition = true, ?int $debounce = null): static
    {
        $this->live($condition, $debounce);
        return $this;
    }

    public function afterStateUpdated(callable $callback): static
    {
        $this->afterStateUpdated($callback);
        return $this;
    }
}
