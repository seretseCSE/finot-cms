<?php

namespace App\Enums;

enum TransactionType: string
{
    case INCOME = 'income';
    case EXPENSE = 'expense';

    public function getLabel(): string
    {
        return match($this) {
            self::INCOME => 'Income',
            self::EXPENSE => 'Expense',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::INCOME => 'success',
            self::EXPENSE => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::INCOME => 'heroicon-o-arrow-trending-up',
            self::EXPENSE => 'heroicon-o-arrow-trending-down',
        };
    }

    public static function getAll(): array
    {
        return [
            self::INCOME->value => self::INCOME->getLabel(),
            self::EXPENSE->value => self::EXPENSE->getLabel(),
        ];
    }
}
