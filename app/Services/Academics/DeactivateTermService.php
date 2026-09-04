<?php

namespace App\Services\Academics;

use App\Models\Term;
use App\Models\User;
use App\Services\Notifications\Notifier;
use App\Support\TermGate;

class DeactivateTermService
{
    public function __construct(private Notifier $notifier)
    {
    }

    public function deactivate(Term $term): Term
    {
        TermGate::close($term);

        $incompleteOfferings = $term->offerings()
            ->whereDoesntHave('assessments.scores')
            ->count();

        if ($incompleteOfferings > 0) {
            $heads = User::role(['education_head', 'admin', 'superadmin'])->get();
            $this->notifier->toUsers($heads, 'academics.term_closed_incomplete', [
                'term' => $term->name,
                'incomplete' => $incompleteOfferings,
            ]);
        }

        activity()->performedOn($term)->log('term.deactivated');

        return $term->fresh();
    }

    public function activate(Term $term): Term
    {
        $activated = TermGate::activate($term);
        activity()->performedOn($activated)->log('term.activated');

        return $activated;
    }
}
