<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="space-y-4">
            <h3 class="text-lg font-medium">Recipients</h3>

            <div class="space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Recipient Type</label>
                    <select wire:model.live="recipient_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="all">All Active Users</option>
                        <option value="roles">By Roles</option>
                        <option value="users">Specific Users</option>
                    </select>
                </div>

                @if ($recipient_type === 'roles')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Select Roles</label>
                        <select wire:model="recipient_ids" multiple class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @foreach (\Spatie\Permission\Models\Role::query()->orderBy('name')->get() as $role)
                                <option value="{{ $role->name }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Hold Ctrl/Cmd to select multiple roles.</p>
                    </div>
                @endif

                @if ($recipient_type === 'users')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Select Users</label>
                        <select wire:model="recipient_ids" multiple class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @foreach (\App\Models\User::query()->where('is_active', true)->orderBy('name')->get() as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email ?? $user->phone }})</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Hold Ctrl/Cmd to select multiple users.</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-4">
            <h3 class="text-lg font-medium">Message</h3>

            <div class="space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Title</label>
                    <input type="text" wire:model="title_input" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white" placeholder="Notification title">
                    @error('title_input') <span class="text-sm text-danger-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Body</label>
                    <textarea wire:model="body" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white" placeholder="Notification message"></textarea>
                    @error('body') <span class="text-sm text-danger-600">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center">
                    <input type="checkbox" wire:model="send_push" id="send_push" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500">
                    <label for="send_push" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Also send as PWA push notification</label>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
