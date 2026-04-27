<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Media Preview Section --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-4 md:p-6 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">{{ $record->title }}</h2>
                    @if($record->description)
                        <p class="text-sm text-gray-500 mt-1">{{ $record->description }}</p>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        {{ $record->type === 'Photo' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        @if($record->type === 'Photo')
                            <x-heroicon-o-photo class="w-3.5 h-3.5 mr-1"/>
                        @else
                            <x-heroicon-o-video-camera class="w-3.5 h-3.5 mr-1"/>
                        @endif
                        {{ $record->type }}
                    </span>
                </div>
            </div>

            <div class="p-4 md:p-6 flex items-center justify-center bg-gray-950">
                @if($record->type === 'Photo')
                    <img
                        src="{{ $record->file_url }}"
                        alt="{{ $record->title }}"
                        class="max-w-full rounded-lg shadow-lg object-contain"
                        style="max-height: 70vh;"
                    />
                @else
                    <video
                        controls
                        class="max-w-full rounded-lg shadow-lg"
                        style="max-height: 70vh;"
                        preload="metadata"
                    >
                        <source src="{{ $record->file_url }}" type="video/mp4">
                        <source src="{{ $record->file_url }}" type="video/webm">
                        <source src="{{ $record->file_url }}" type="video/quicktime">
                        Your browser does not support the video tag.
                    </video>
                @endif
            </div>
        </div>

        {{-- Metadata Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- Details Card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 md:col-span-2">
                <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4">Details</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-4 gap-x-6">
                    <div>
                        <span class="text-xs text-gray-500 block">Category</span>
                        <span class="text-sm font-medium text-gray-900">{{ $record->category?->name ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 block">Sub-category</span>
                        <span class="text-sm font-medium text-gray-900">{{ $record->subcategory?->name ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 block">Event Album</span>
                        <span class="text-sm font-medium text-gray-900">{{ $record->event_album ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 block">File Size</span>
                        <span class="text-sm font-medium text-gray-900">{{ $record->formatted_file_size }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 block">Visibility</span>
                        @php
                            $visibilityClasses = match ($record->visibility) {
                                'Public' => 'bg-green-100 text-green-800',
                                'Members Only' => 'bg-blue-100 text-blue-800',
                                'Department Only' => 'bg-purple-100 text-purple-800',
                                default => 'bg-gray-100 text-gray-800',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $visibilityClasses }}">
                            {{ $record->visibility }}
                        </span>
                    </div>
                    @if($record->visibility === 'Department Only')
                        <div>
                            <span class="text-xs text-gray-500 block">Department</span>
                            <span class="text-sm font-medium text-gray-900">{{ $record->department?->name ?? '—' }}</span>
                        </div>
                    @endif
                </div>

                @if(!empty($record->parsed_tags))
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <span class="text-xs text-gray-500 block mb-2">Tags</span>
                        <div class="flex flex-wrap gap-2">
                            @foreach($record->parsed_tags as $tag)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-gray-100 text-gray-700">
                                    {{ $tag }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Upload Info Card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4">Upload Info</h3>
                <div class="space-y-4">
                    <div>
                        <span class="text-xs text-gray-500 block">Uploaded By</span>
                        <div class="flex items-center mt-1">
                            <div class="w-8 h-8 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-xs font-bold mr-2">
                                {{ strtoupper(substr($record->uploadedBy?->name ?? 'U', 0, 1)) }}
                            </div>
                            <span class="text-sm font-medium text-gray-900">{{ $record->uploadedBy?->name ?? 'Unknown' }}</span>
                        </div>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 block">Upload Date</span>
                        <span class="text-sm font-medium text-gray-900">{{ $record->created_at?->format('M d, Y h:i A') }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 block">Last Updated</span>
                        <span class="text-sm font-medium text-gray-900">{{ $record->updated_at?->format('M d, Y h:i A') }}</span>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-gray-100">
                    <a
                        href="{{ $record->file_url }}"
                        target="_blank"
                        download
                        class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                    >
                        <x-heroicon-o-arrow-down-tray class="w-4 h-4 mr-2"/>
                        Download File
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
