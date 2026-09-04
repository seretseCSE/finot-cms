<?php

namespace App\Observers;

use App\Models\ParentModel;
use App\Services\Identity\ProvisionParentUser;

class ParentObserver
{
    public function saved(ParentModel $parent): void
    {
        app(ProvisionParentUser::class)->sync($parent);
    }
}
