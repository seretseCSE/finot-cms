<?php

namespace App\Services\Academics;

use App\Enums\MarklistStatus;
use App\Models\Term;
use App\Models\User;
use App\Services\Notifications\Notifier;

class DeactivateTermService
{
    public function __construct(private Notifier $notifier)
    {
    }

    public function deactivate(Term $term): Term
    {
        $term->update(['is_active' => false]);

        $incomplete = $term->marklists()
            ->whereIn('status', [MarklistStatus::Draft->value, MarklistStatus::Submitted->value])
            ->count();

        if ($incomplete > 0) {
            $heads = User::permission('results.approve')->get();
            $this->notifier->toUsers($heads, 'academics.term_closed_incomplete', [
                'term' => $term->name,
                'incomplete' => $incomplete,
            ]);
        }

        activity()->performedOn($term)->log('term.deactivated');

        return $term->fresh();
    }
}
