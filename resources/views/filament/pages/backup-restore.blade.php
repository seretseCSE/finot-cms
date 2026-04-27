<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Available Backups -->
        <x-filament::section>
            <x-filament::section.header>
                <x-filament::section.heading>
                    Available Backups
                </x-filament::section.heading>
                <x-filament::section.description>
                    Last 30 system backups. Automatic backups are created daily.
                </x-filament::section.description>
            </x-filament::section.header>

            <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                <table class="min-w-full divide-y divide-gray-300">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Filename
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Type
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Size
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Created
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($this->getBackups() as $backup)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $backup['filename'] }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                        {{ $backup['type'] === 'Manual' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                                        {{ $backup['type'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $backup['size'] }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $backup['created_at'] }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        <button 
                                            wire:click="downloadBackup('{{ $backup['filename'] }}')"
                                            class="text-indigo-600 hover:text-indigo-900"
                                            title="Download Backup">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4 4m4-4v12" />
                                            </svg>
                                        </button>
                                        <button 
                                            wire:click="restoreBackup('{{ $backup['filename'] }}')"
                                            wire:confirm="Are you sure you want to restore from this backup? This will put the system in maintenance mode and replace all current data. Type 'CONFIRM RESTORE' to proceed."
                                            wire:target="restore"
                                            class="text-orange-600 hover:text-orange-900"
                                            title="Restore from Backup">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                        </button>
                                        <button 
                                            wire:click="deleteBackup('{{ $backup['filename'] }}')"
                                            wire:confirm="Are you sure you want to delete this backup?"
                                            class="text-red-600 hover:text-red-900"
                                            title="Delete Backup">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No backups available. Create your first backup using the "Create Backup" button.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <!-- System Information -->
        <x-filament::section>
            <x-filament::section.header>
                <x-filament::section.heading>
                    Backup Information
                </x-filament::section.heading>
            </x-filament::section.header>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white p-4 rounded-lg shadow">
                    <h3 class="text-sm font-medium text-gray-500">Backup Retention</h3>
                    <p class="mt-1 text-lg font-semibold text-gray-900">30 backups</p>
                    <p class="mt-1 text-sm text-gray-500">Last 30 backups are kept</p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow">
                    <h3 class="text-sm font-medium text-gray-500">Backup Location</h3>
                    <p class="mt-1 text-lg font-semibold text-gray-900">storage/backups</p>
                    <p class="mt-1 text-sm text-gray-500">Secure local storage</p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow">
                    <h3 class="text-sm font-medium text-gray-500">Backup Contents</h3>
                    <p class="mt-1 text-lg font-semibold text-gray-900">Full System</p>
                    <p class="mt-1 text-sm text-gray-500">Database + Files + Config</p>
                </div>
            </div>
        </x-filament::section>

        <!-- Warning Notice -->
        <x-filament::section>
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Important Notice</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <ul class="list-disc list-inside space-y-1">
                                <li>Restoring from backup will replace ALL current data</li>
                                <li>System will enter maintenance mode during restore</li>
                                <li>Only Superadmin can perform backup/restore operations</li>
                                <li>All backup/restore actions are logged in audit trail</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
