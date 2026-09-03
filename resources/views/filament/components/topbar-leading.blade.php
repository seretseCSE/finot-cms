<div class="flex items-center gap-1">
    @php
        $roles = \App\Support\RoleGate::switchableRoles();
    @endphp
    @if (count($roles) > 1)
        @include('filament.components.role-switcher', [
            'roles' => $roles,
            'active' => \App\Support\RoleGate::activeRole(),
        ])
    @endif
    @include('filament.components.in-app-bell')
</div>
