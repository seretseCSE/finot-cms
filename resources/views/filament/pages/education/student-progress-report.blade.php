<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ── Student Selection ───────────────────────────────────────────── --}}
        <x-filament::section>
            <x-slot name="heading">Student Selection</x-slot>

            {{ $this->form }}

            <div class="mt-4">
                <x-filament::button
                    wire:click="generateProgressReport"
                    wire:loading.attr="disabled"
                    wire:target="generateProgressReport"
                >
                    <span wire:loading.remove wire:target="generateProgressReport">
                        Generate Progress Report
                    </span>
                    <span wire:loading wire:target="generateProgressReport" class="flex items-center gap-2">
                        <x-filament::loading-indicator class="w-4 h-4" />
                        Generating…
                    </span>
                </x-filament::button>
            </div>
        </x-filament::section>

        {{--
            FIX: reads $this->reportData (a plain public property set by generateProgressReport())
                 instead of calling getProgressData() twice per render.
        --}}
        @if($this->reportData)
            @php $data = $this->reportData; @endphp

            {{-- ── Student Header ──────────────────────────────────────────── --}}
            <x-filament::section>
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center flex-shrink-0">
                            <x-filament::icon icon="heroicon-o-user" class="w-8 h-8 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ $data['student']->full_name }}
                            </h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $data['student']->phone }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $data['current_enrollment']->class->name }}
                            </p>
                        </div>
                    </div>
                    <x-filament::button
                        wire:click="generateReportCard"
                        color="gray"
                        icon="heroicon-o-document-arrow-down"
                    >
                        Generate Report Card
                    </x-filament::button>
                </div>
            </x-filament::section>

            {{-- ── Performance Overview Cards ──────────────────────────────── --}}
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem;">

                <x-filament::section>
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0 p-3 rounded-lg bg-green-100 dark:bg-green-900">
                            <x-filament::icon icon="heroicon-o-check-circle" class="w-6 h-6 text-green-600 dark:text-green-400" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Attendance Rate</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $data['attendance']['rate'] }}%</p>
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0 p-3 rounded-lg bg-blue-100 dark:bg-blue-900">
                            <x-filament::icon icon="heroicon-o-chart-bar" class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Average Score</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $data['tests']['average_score'] }}</p>
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0 p-3 rounded-lg bg-yellow-100 dark:bg-yellow-900">
                            <x-filament::icon icon="heroicon-o-trophy" class="w-6 h-6 text-yellow-600 dark:text-yellow-400" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Highest Score</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $data['tests']['highest_score'] }}</p>
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0 p-3 rounded-lg bg-purple-100 dark:bg-purple-900">
                            <x-filament::icon icon="heroicon-o-document-text" class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Tests</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $data['tests']['total_tests'] }}</p>
                        </div>
                    </div>
                </x-filament::section>

            </div>

            {{-- ── Contribution Summary ─────────────────────────────────────── --}}
            @php $contrib = $data['contributions']; @endphp
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-currency-dollar" class="w-5 h-5 text-amber-500" />
                        <span>Contribution Summary</span>
                    </div>
                </x-slot>
                <x-slot name="description">Academic year contribution tracking</x-slot>

                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:1rem;">
                    <x-filament::section>
                        <div class="flex items-center gap-4">
                            <div class="flex-shrink-0 p-3 rounded-lg bg-amber-100 dark:bg-amber-900">
                                <x-filament::icon icon="heroicon-o-calendar-days" class="w-6 h-6 text-amber-600 dark:text-amber-400" />
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Total Months</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $contrib['total_months'] }}</p>
                            </div>
                        </div>
                    </x-filament::section>

                    <x-filament::section>
                        <div class="flex items-center gap-4">
                            <div class="flex-shrink-0 p-3 rounded-lg bg-green-100 dark:bg-green-900">
                                <x-filament::icon icon="heroicon-o-check-circle" class="w-6 h-6 text-green-600 dark:text-green-400" />
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Paid</p>
                                <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $contrib['paid_count'] }}</p>
                            </div>
                        </div>
                    </x-filament::section>

                    <x-filament::section>
                        <div class="flex items-center gap-4">
                            <div class="flex-shrink-0 p-3 rounded-lg bg-red-100 dark:bg-red-900">
                                <x-filament::icon icon="heroicon-o-x-circle" class="w-6 h-6 text-red-600 dark:text-red-400" />
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Unpaid</p>
                                <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $contrib['unpaid_count'] }}</p>
                            </div>
                        </div>
                    </x-filament::section>

                    <x-filament::section>
                        <div class="flex items-center gap-4">
                            <div class="flex-shrink-0 p-3 rounded-lg bg-emerald-100 dark:bg-emerald-900">
                                <x-filament::icon icon="heroicon-o-banknotes" class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Total Paid</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($contrib['total_paid'], 2) }} ETB</p>
                            </div>
                        </div>
                    </x-filament::section>

                    <x-filament::section>
                        <div class="flex items-center gap-4">
                            <div class="flex-shrink-0 p-3 rounded-lg bg-blue-100 dark:bg-blue-900">
                                <x-filament::icon icon="heroicon-o-chart-pie" class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Payment Rate</p>
                                <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $contrib['payment_rate'] }}%</p>
                            </div>
                        </div>
                    </x-filament::section>
                </div>

                @if(count($contrib['monthly']) > 0)
                    <div class="mt-4 w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                        <div class="h-2.5 rounded-full bg-green-500 transition-all" style="width:{{ $contrib['payment_rate'] }}%"></div>
                    </div>

                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-800">
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Month</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Payment Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Method</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($contrib['monthly'] as $mn)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $mn['month_name'] }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-600 dark:text-gray-300">
                                            {{ number_format($mn['amount'], 2) }} ETB
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-center">
                                            @if($mn['status'] === 'Paid')
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300">
                                                    <x-filament::icon icon="heroicon-o-check" class="w-3 h-3" />
                                                    Paid
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300">
                                                    <x-filament::icon icon="heroicon-o-x-mark" class="w-3 h-3" />
                                                    Unpaid
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $mn['payment_date'] ? \Carbon\Carbon::parse($mn['payment_date'])->format('M d, Y') : '-' }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $mn['payment_method'] ?? '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>

            {{-- ── Attendance Breakdown ─────────────────────────────────────── --}}
            <x-filament::section>
                <x-slot name="heading">Attendance Breakdown</x-slot>

                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(120px,1fr)); gap:1rem; text-align:center;">
                    @foreach($data['attendance']['details'] as $status => $records)
                        @php
                            $color = match($status) {
                                'Present' => '#16a34a',
                                'Absent'  => '#dc2626',
                                default   => '#d97706',
                            };
                        @endphp
                        <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                            <div class="text-3xl font-bold" style="color:{{ $color }}">
                                {{ $records->count() }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $status }}</div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            {{-- ── Recent Test Results ──────────────────────────────────────── --}}
            <x-filament::section>
                <x-slot name="heading">Recent Test Results</x-slot>

                @if($data['tests']['results']->isEmpty())
                    <div class="py-8 text-center text-gray-400 dark:text-gray-500">
                        <x-filament::icon icon="heroicon-o-clipboard-document-list" class="w-10 h-10 mx-auto mb-2 opacity-40" />
                        <p class="text-sm">No test results recorded yet.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-800">
                                    @foreach(['Test','Date','Score','Grade'] as $th)
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            {{ $th }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($data['tests']['results']->take(10) as $result)
                                    @php
                                        $s = $result->score;
                                        [$grade, $gClass] = match(true) {
                                            $s >= 90 => ['A', 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'],
                                            $s >= 80 => ['B', 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300'],
                                            $s >= 70 => ['C', 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300'],
                                            $s >= 60 => ['D', 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300'],
                                            default  => ['F', 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'],
                                        };
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $result->test->title }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ \Carbon\Carbon::parse($result->created_at)->format('M d, Y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-semibold text-gray-900 dark:text-white w-8">{{ $s }}</span>
                                                <div class="flex-1 max-w-[80px] rounded-full h-2" style="background:#e5e7eb;">
                                                    <div class="h-2 rounded-full" style="width:{{ min($s,100) }}%; background:#2563eb;"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 inline-flex text-xs font-bold rounded-full {{ $gClass }}">
                                                {{ $grade }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>

            {{-- ── Progress Trend ───────────────────────────────────────────── --}}
            @if($data['progress_trend']->count() > 0)
                <x-filament::section>
                    <x-slot name="heading">Progress Trend</x-slot>

                    <div class="space-y-3">
                        @foreach($data['progress_trend'] as $month)
                            @php $pct = min($month['average_score'], 100); @endphp
                            <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                                <div>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $month['month'] }}</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">({{ $month['test_count'] }} tests)</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-bold text-gray-900 dark:text-white w-8 text-right">{{ $month['average_score'] }}</span>
                                    <div class="w-24 rounded-full h-2" style="background:#e5e7eb;">
                                        <div class="h-2 rounded-full" style="width:{{ $pct }}%; background:#16a34a;"></div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif

        @endif
    </div>
</x-filament-panels::page>
