<?php

namespace App\Http\Responses\Auth;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class CustomLoginResponse implements LoginResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $user = auth()->user();

        if ($user instanceof User && $user->isStudentOnly()) {
            $redirect = redirect()->to($user->postLoginUrl());
            if (! $user->temp_password_changed) {
                $redirect->with('info', 'Please update your password.');
            }

            return $redirect;
        }

        $intended = session()->pull('url.intended');

        if ($user instanceof User && ! $user->temp_password_changed) {
            return redirect()->to($user->postLoginUrl());
        }

        if ($intended && ! str_contains($intended, '/api/')) {
            return redirect()->to($intended);
        }

        return redirect()->to($user instanceof User ? $user->postLoginUrl() : url('/admin'));
    }
}
