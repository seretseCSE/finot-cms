<div class="fi-topbar-item relative mr-1" data-tour="role-switcher">
    <form method="POST" action="{{ route('admin.active-role') }}" class="flex items-center">
        @csrf
        <label for="active-role" class="sr-only">{{ __('Active role') }}</label>
        <select
            id="active-role"
            name="role"
            onchange="this.form.submit()"
            class="rounded-lg border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 text-sm font-medium text-gray-700 dark:text-gray-200 py-1.5 pl-2.5 pr-8 max-w-[11rem]"
        >
            @foreach ($roles as $role)
                <option value="{{ $role['name'] }}" @selected($role['name'] === $active)>{{ $role['label'] }}</option>
            @endforeach
        </select>
    </form>
</div>
