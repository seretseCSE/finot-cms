<?php

namespace App\Filament\Widgets;

use App\Services\ProductTourService;
use Filament\Widgets\AccountWidget as BaseAccountWidget;
use Illuminate\Support\Facades\Auth;

class AccountWidget extends BaseAccountWidget
{
    protected function getViewData(): array
    {
        $user = Auth::user();
        $productTourService = app(ProductTourService::class);
        
        return array_merge(parent::getViewData(), [
            'completedTours' => $productTourService->getAllCompletedTours($user),
            'userRole' => $user->roles->first()->name ?? 'staff',
        ]);
    }
}

