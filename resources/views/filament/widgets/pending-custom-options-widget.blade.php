<a href="{{ $url ?? '#' }}" class="block p-6" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-medium text-gray-900">{{ $description }}</h3>
                <p class="text-sm text-gray-600 mt-1">{{ $count }} pending options</p>
            </div>
            <div class="text-2xl font-bold text-primary-600">{{ $count }}</div>
        </div>
    </div>
</a>

@if (isset($chartOptions))
    <div class="mt-6 bg-white rounded-lg shadow-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Trend Overview</h3>
        <div class="h-64">
            {{ $chart($chartOptions) }}
        </div>
    </div>
@endif
