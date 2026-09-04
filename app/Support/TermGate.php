<?php

namespace App\Support;

use App\Models\Term;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class TermGate
{
    public static function assertWritable(Term $term, ?User $actor = null): void
    {
        if ($term->status === 'closed') {
            throw ValidationException::withMessages(['term' => 'Semester is closed.']);
        }

        $writable = $term->status === 'active' || ($term->status !== 'closed' && $term->is_active);

        if ($writable) {
            return;
        }

        if ($actor?->hasRole(['admin', 'superadmin'])) {
            return;
        }

        throw ValidationException::withMessages(['term' => 'Semester is not active for encoding.']);
    }

    public static function activate(Term $term): Term
    {
        if ($term->batch_year_id) {
            Term::query()
                ->where('batch_year_id', $term->batch_year_id)
                ->where('id', '!=', $term->id)
                ->where('status', 'active')
                ->update(['status' => 'closed', 'is_active' => false]);
        }

        $term->update([
            'status' => 'active',
            'is_active' => true,
        ]);

        return $term->fresh();
    }

    public static function close(Term $term): Term
    {
        $term->update([
            'status' => 'closed',
            'is_active' => false,
        ]);

        return $term->fresh();
    }
}
