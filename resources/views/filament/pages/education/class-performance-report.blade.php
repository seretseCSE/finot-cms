<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ── Filters ─────────────────────────────────────────────────────── --}}
        <x-filament::section>
            <x-slot name="heading">Class Selection</x-slot>

            {{ $this->form }}

            <div class="mt-4">
                <x-filament::button wire:click="generateClassReport">
                    Generate Class Report
                </x-filament::button>
            </div>
        </x-filament::section>

        @if($this->reportData)
            @php $data = $this->reportData; @endphp

            {{-- ── Class Header ────────────────────────────────────────────── --}}
            <x-filament::section>
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $data['class']['name'] }}
                    </h2>
                    <x-filament::button wire:click="exportClassReport" color="gray" icon="heroicon-m-document-arrow-down">
                        Export Report
                    </x-filament::button>
                </div>
            </x-filament::section>

            {{-- ── Stat Cards ──────────────────────────────────────────────── --}}
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1rem;">

                <x-filament::section>
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0 p-3 rounded-lg bg-blue-100 dark:bg-blue-900">
                            <x-filament::icon icon="heroicon-o-users" class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Students</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $data['class_stats']['total_students'] }}</p>
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0 p-3 rounded-lg bg-green-100 dark:bg-green-900">
                            <x-filament::icon icon="heroicon-o-check-circle" class="w-6 h-6 text-green-600 dark:text-green-400" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Avg Attendance</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $data['class_stats']['average_attendance_rate'] }}%</p>
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0 p-3 rounded-lg bg-purple-100 dark:bg-purple-900">
                            <x-filament::icon icon="heroicon-o-chart-bar" class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Avg Test Score</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $data['class_stats']['average_test_score'] }}</p>
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
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $data['class_stats']['highest_test_score'] }}</p>
                        </div>
                    </div>
                </x-filament::section>

            </div>

            {{-- ── Distribution Charts ─────────────────────────────────────── --}}
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:1.5rem;">

                {{-- Attendance Distribution --}}
                <x-filament::section>
                    <x-slot name="heading">Attendance Distribution</x-slot>

                    @php $total = max($data['class_stats']['total_students'], 1); @endphp

                    @foreach([
                        ['label' => 'Excellent (90%+)', 'key' => 'excellent', 'color' => '#16a34a'],
                        ['label' => 'Good (75–89%)',    'key' => 'good',      'color' => '#2563eb'],
                        ['label' => 'Fair (60–74%)',    'key' => 'fair',      'color' => '#d97706'],
                        ['label' => 'Poor (<60%)',      'key' => 'poor',      'color' => '#dc2626'],
                    ] as $row)
                        @php $count = $data['attendance_distribution'][$row['key']]; $pct = round($count / $total * 100); @endphp
                        <div class="mb-3">
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-medium" style="color:{{ $row['color'] }}">{{ $row['label'] }}</span>
                                <span class="font-bold text-gray-800 dark:text-gray-200">{{ $count }} students</span>
                            </div>
                            <div class="w-full rounded-full h-2" style="background:#e5e7eb;">
                                <div class="h-2 rounded-full transition-all" style="width:{{ $pct }}%; background:{{ $row['color'] }};"></div>
                            </div>
                        </div>
                    @endforeach
                </x-filament::section>

                {{-- Test Score Distribution --}}
                <x-filament::section>
                    <x-slot name="heading">Test Score Distribution</x-slot>

                    @foreach([
                        ['label' => 'Excellent (90–100)', 'key' => 'excellent', 'color' => '#16a34a'],
                        ['label' => 'Good (80–89)',        'key' => 'good',      'color' => '#2563eb'],
                        ['label' => 'Fair (70–79)',        'key' => 'fair',      'color' => '#d97706'],
                        ['label' => 'Poor (<70)',          'key' => 'poor',      'color' => '#dc2626'],
                    ] as $row)
                        @php $count = $data['test_distribution'][$row['key']]; $pct = round($count / $total * 100); @endphp
                        <div class="mb-3">
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-medium" style="color:{{ $row['color'] }}">{{ $row['label'] }}</span>
                                <span class="font-bold text-gray-800 dark:text-gray-200">{{ $count }} students</span>
                            </div>
                            <div class="w-full rounded-full h-2" style="background:#e5e7eb;">
                                <div class="h-2 rounded-full transition-all" style="width:{{ $pct }}%; background:{{ $row['color'] }};"></div>
                            </div>
                        </div>
                    @endforeach
                </x-filament::section>

            </div>

            {{-- ── Individual Student Table ────────────────────────────────── --}}
            <x-filament::section>
                <x-slot name="heading">Individual Student Performance</x-slot>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                @foreach(['Student','Attendance Rate','Avg Test Score','Total Tests','Performance'] as $th)
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        {{ $th }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($data['students'] as $student)
                                @php
                                    $sid   = $student['id'];
                                    $att   = $data['student_attendance'][$sid] ?? [];
                                    $tst   = $data['student_tests'][$sid] ?? [];
                                    $aRate = $att['attendance_rate'] ?? 0;
                                    $aScore= $tst['average_score'] ?? 0;
                                    $perf  = ($aRate + $aScore) / 2;
                                    [$perfLabel,$perfClass] = match(true) {
                                        $perf >= 85 => ['Excellent','bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'],
                                        $perf >= 70 => ['Good',     'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300'],
                                        $perf >= 55 => ['Fair',     'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300'],
                                        default     => ['Needs Attention','bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'],
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    {{-- Student --}}
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            <div class="h-9 w-9 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center flex-shrink-0">
                                                <x-filament::icon icon="heroicon-o-user" class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $student['full_name'] }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $student['phone'] }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    {{-- Attendance --}}
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-semibold text-gray-900 dark:text-white w-10">{{ $aRate }}%</span>
                                            <div class="flex-1 max-w-[80px] rounded-full h-2" style="background:#e5e7eb;">
                                                <div class="h-2 rounded-full" style="width:{{ $aRate }}%; background:#16a34a;"></div>
                                            </div>
                                        </div>
                                    </td>
                                    {{-- Score --}}
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-semibold text-gray-900 dark:text-white w-10">{{ $aScore }}</span>
                                            <div class="flex-1 max-w-[80px] rounded-full h-2" style="background:#e5e7eb;">
                                                <div class="h-2 rounded-full" style="width:{{ min($aScore,100) }}%; background:#2563eb;"></div>
                                            </div>
                                        </div>
                                    </td>
                                    {{-- Tests --}}
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        {{ $tst['total_tests'] ?? 0 }}
                                    </td>
                                    {{-- Badge --}}
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 inline-flex text-xs font-semibold rounded-full {{ $perfClass }}">
                                            {{ $perfLabel }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

        @endif
    </div>
</x-filament-panels::page>
