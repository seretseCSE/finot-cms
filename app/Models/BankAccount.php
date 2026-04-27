<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'account_number',
        'account_name',
        'bank_name',
        'branch_name',
        'account_type',
        'current_balance',
        'currency',
        'phone_number',
        'email',
        'address',
        'is_active',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions()
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    public function incomeTransactions()
    {
        return $this->hasMany(FinancialTransaction::class)->where('type', 'income');
    }

    public function expenseTransactions()
    {
        return $this->hasMany(FinancialTransaction::class)->where('type', 'expense');
    }

    public function donations()
    {
        return $this->hasMany(Donation::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->current_balance, 2).' '.$this->currency;
    }

    public function getAccountTypeLabelAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->account_type));
    }

    public function updateBalance(): void
    {
        $income = $this->incomeTransactions()->sum('amount');
        $expenses = $this->expenseTransactions()->sum('amount');
        $donations = $this->donations()->sum('amount');
        $this->current_balance = ($income + $donations) - $expenses;
        $this->save();
    }

    public function adjustBalance(float $delta): void
    {
        if ($delta === 0.0) {
            return;
        }

        $this->increment('current_balance', $delta);
    }
}
