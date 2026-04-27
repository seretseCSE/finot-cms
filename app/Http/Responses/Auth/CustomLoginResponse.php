<?php

namespace App\Http\Responses\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class CustomLoginResponse implements LoginResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $user = auth()->user();

        // If user needs to change temporary password, redirect to change password page
        if ($user && ! $user->temp_password_changed) {
            return redirect()->route('filament.admin.pages.change-password');
        }

        // Otherwise redirect to dashboard, ignoring intended if it's an API endpoint
        $intended = session()->pull('url.intended');

        if ($intended && ! str_contains($intended, '/api/')) {
            return redirect()->to($intended);
        }

        return redirect(Filament::getUrl());
    }
}
