@php
    use Illuminate\Support\Facades\Auth;
    
    $activeAnnouncements = $this->getActiveGlobalAnnouncements();
    $stats = $this->getAnnouncementStats();
    $user = Auth::user();
@endphp

<div class="space-y-4">
    {{-- Admin Statistics --}}
    @if(!empty($stats))
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg">
                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $stats['total_global'] }}</div>
                <div class="text-sm text-blue-600 dark:text-blue-400">Total Global</div>
            </div>
            <div class="bg-green-50 dark:bg-green-900/20 p-3 rounded-lg">
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $stats['active_global'] }}</div>
                <div class="text-sm text-green-600 dark:text-green-400">Active</div>
            </div>
            <div class="bg-red-50 dark:bg-red-900/20 p-3 rounded-lg">
                <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $stats['urgent_global'] }}</div>
                <div class="text-sm text-red-600 dark:text-red-400">Urgent</div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-900/20 p-3 rounded-lg">
                <div class="text-2xl font-bold text-gray-600 dark:text-gray-400">{{ $stats['total_users'] }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Total Users</div>
            </div>
        </div>
    @endif

    {{-- Active Global Announcements --}}
    @if(empty($activeAnnouncements))
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
            </svg>
            <p class="text-lg font-medium">No Active Global Announcements</p>
            <p class="text-sm mt-1">Check back later for important system-wide announcements.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($activeAnnouncements as $announcement)
                <div class="border rounded-lg p-4 @if($announcement['is_urgent']) border-red-300 bg-red-50 dark:border-red-600 dark:bg-red-900/20 @else border-gray-200 dark:border-gray-700 dark:bg-gray-800/50 @endif">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-2 mb-2">
                                @if($announcement['is_global'])
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM4.332 8.027a6.012 6.012 0 011.912-2.706C6.512 5.73 6.974 6 7.5 6A1.5 1.5 0 019 7.5V8a2 2 0 004 0 2 2 0 011.523-1.943A5.977 5.977 0 0116 10c0 .34-.028.675-.083 1H15a2 2 0 00-2 2v2.197A5.973 5.973 0 0110 16v-2a2 2 0 00-2-2 2 2 0 01-2-2 2 2 0 00-1.668-1.973z" clip-rule="evenodd"/>
                                        </svg>
                                        Global
                                    </span>
                                @endif
                                @if($announcement['is_urgent'])
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                        Urgent
                                    </span>
                                @endif
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ \Carbon\Carbon::parse($announcement['created_at'])->format('M j, Y') }}
                                </span>
                            </div>
                            
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                                {{ $announcement['title'] }}
                            </h3>
                            
                            <div class="prose prose-sm max-w-none dark:prose-invert">
                                {!! $announcement['content'] !!}
                            </div>
                            
                            @if($announcement['target_audience'] !== 'all_users')
                                <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                    Target: {{ \App\Models\Announcement::getTargetAudienceOptions()[$announcement['target_audience']] ?? $announcement['target_audience'] }}
                                </div>
                            @endif
                        </div>
                        
                        {{-- Acknowledge Button --}}
                        @if($announcement['is_global'] && !$announcement->isAcknowledgedBy($user->id))
                            <div class="ml-4">
                                <form wire:submit="acknowledgeAnnouncement({{ $announcement['id'] }})" class="inline">
                                    <button type="submit" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Acknowledge
                                    </button>
                                </form>
                            </div>
                        @elseif($announcement['is_global'] && $announcement->isAcknowledgedBy($user->id))
                            <div class="ml-4">
                                <span class="inline-flex items-center px-3 py-1.5 border border-green-300 text-xs font-medium rounded-md text-green-700 bg-green-50 dark:bg-green-900/20 dark:border-green-600 dark:text-green-300">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    Acknowledged
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Admin Actions --}}
    @if($user->hasRole(['admin', 'superadmin']) && !empty($stats))
        <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Global announcements management
                </div>
                <a href="{{ \App\Filament\Resources\AnnouncementResource::getUrl() }}" class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 shadow-sm text-xs font-medium rounded text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
                    </svg>
                    Manage All
                </a>
            </div>
        </div>
    @endif
</div>
