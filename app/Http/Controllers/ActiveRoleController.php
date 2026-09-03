<?php

namespace App\Http\Controllers;

use App\Support\RoleGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActiveRoleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role' => 'required|string|max:80',
        ]);

        if (! RoleGate::switchTo($validated['role'])) {
            return redirect()->to(url('/admin'))
                ->withErrors(['role' => 'You do not have that role.']);
        }

        return redirect()->to(url('/admin'));
    }
}
