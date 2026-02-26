<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Member;

class Timeline extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MemberResource::class;

    protected string $view = 'filament.resources.member.pages.timeline';

    protected static ?string $title = 'Member Timeline';

    public Member $record;

    public ?string $eventTypeFilter = 'all';
    public ?string $dateRangeFilter = 'all';

    public function mount(int|string $record): void
    {
        $this->record = Member::findOrFail($record);
    }

    public function getTimelineEvents(): array
    {
        $events = [];

        // Attendance records
        $attendance = $this->record->attendance()
            ->when($this->eventTypeFilter !== 'all', function ($query) {
                return $query->where('status', $this->eventTypeFilter);
            })
            ->when($this->dateRangeFilter !== 'all', function ($query) {
                $dateFilter = match($this->dateRangeFilter) {
                    'week' => now()->subWeek(),
                    'month' => now()->subMonth(),
                    'quarter' => now()->subQuarter(),
                    'year' => now()->subYear(),
                    default => now()->subMonth(),
                };
                return $query->whereHas('session', function ($query) use ($dateFilter) {
                    $query->where('date', '>=', $dateFilter);
                });
            })
            ->with(['session', 'session.class', 'session.academicYear'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'type' => 'attendance',
                    'title' => 'Attendance Recorded',
                    'description' => $record->status === 'Present' 
                        ? "Present in {$record->session->class->name} class"
                        : "Absent from {$record->session->class->name} class",
                    'date' => $record->session->date,
                    'time' => $record->session->start_time,
                    'status' => $record->status,
                    'icon' => $record->status === 'Present' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle',
                    'color' => $record->status === 'Present' ? 'success' : 'danger',
                ];
            });

        // Contributions
        $contributions = $this->record->contributions()
            ->when($this->dateRangeFilter !== 'all', function ($query) {
                $dateFilter = match($this->dateRangeFilter) {
                    'week' => now()->subWeek(),
                    'month' => now()->subMonth(),
                    'quarter' => now()->subQuarter(),
                    'year' => now()->subYear(),
                    default => now()->subMonth(),
                };
                return $query->where('contribution_date', '>=', $dateFilter);
            })
            ->orderBy('contribution_date', 'desc')
            ->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'type' => 'contribution',
                    'title' => 'Contribution',
                    'description' => "{$record->type}: " . number_format($record->amount, 2) . " ETB",
                    'date' => $record->contribution_date,
                    'time' => null,
                    'status' => $record->status,
                    'icon' => 'heroicono-banknotes',
                    'color' => 'primary',
                ];
            });

        // Education milestones
        $education = $this->record->educationHistory()
            ->when($this->dateRangeFilter !== 'all', function ($query) {
                $dateFilter = match($this->dateRangeFilter) {
                    'week' => now()->subWeek(),
                    'month' => now()->subMonth(),
                    'quarter' => now()->subQuarter(),
                    'year' => now()->subYear(),
                    default => now()->subMonth(),
                };
                return $query->where('start_date', '>=', $dateFilter);
            })
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'type' => 'education',
                    'title' => 'Education Milestone',
                    'description' => "Started {$record->class->name} - {$record->academicYear->name}",
                    'date' => $record->start_date,
                    'time' => null,
                    'status' => 'completed',
                    'icon' => 'heroicono-academic-cap',
                    'color' => 'info',
                ];
            });

        // Parent/Guardian assignments
        $guardians = $this->record->parentGuardians()
            ->when($this->dateRangeFilter !== 'all', function ($query) {
                $dateFilter = match($this->dateRangeFilter) {
                    'week' => now()->subWeek(),
                    'month' => now()->subMonth(),
                    'quarter' => now()->subQuarter(),
                    'year' => now()->subYear(),
                    default => now()->subMonth(),
                };
                return $query->where('created_at', '>=', $dateFilter);
            })
            ->with(['parent'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'type' => 'guardian',
                    'title' => 'Parent/Guardian Assigned',
                    'description' => "{$record->parent->full_name} ({$record->relationship})",
                    'date' => $record->created_at->toDateString(),
                    'time' => $record->created_at->toTimeString(),
                    'status' => 'active',
                    'icon' => 'heroicono-users',
                    'color' => 'warning',
                ];
            });

        // Group assignments
        $groups = $this->record->groupAssignments()
            ->when($this->dateRangeFilter !== 'all', function ($query) {
                $dateFilter = match($this->dateRangeFilter) {
                    'week' => now()->subWeek(),
                    'month' => now()->subMonth(),
                    'quarter' => now()->subQuarter(),
                    'year' => now()->subYear(),
                    default => now()->subMonth(),
                };
                return $query->where('created_at', '>=', $dateFilter);
            })
            ->with(['group'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'type' => 'group',
                    'title' => 'Group Assignment',
                    'description' => "Joined {$record->group->name}",
                    'date' => $record->created_at->toDateString(),
                    'time' => $record->created_at->toTimeString(),
                    'status' => 'active',
                    'icon' => 'heroicono-user-group',
                    'color' => 'secondary',
                ];
            });

        // Tour participation
        $tourParticipation = $this->record->tourPassengers()
            ->when($this->dateRangeFilter !== 'all', function ($query) {
                $dateFilter = match($this->dateRangeFilter) {
                    'week' => now()->subWeek(),
                    'month' => now()->subMonth(),
                    'quarter' => now()->subQuarter(),
                    'year' => now()->subYear(),
                    default => now()->subMonth(),
                };
                return $query->where('created_at', '>=', $dateFilter);
            })
            ->with(['tour'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'type' => 'tour',
                    'title' => 'Tour Participation',
                    'description' => "Registered for {$record->tour->place} tour",
                    'date' => $record->created_at->toDateString(),
                    'time' => $record->created_at->toTimeString(),
                    'status' => $record->status,
                    'icon' => 'heroicono-map',
                    'color' => $record->status === 'Attended' ? 'success' : ($record->status === 'Confirmed' ? 'primary' : 'warning'),
                ];
            });

        // Status changes (password changes, role changes, etc.)
        $statusChanges = $this->record->audits()
            ->where('event', 'updated')
            ->when($this->dateRangeFilter !== 'all', function ($query) {
                $dateFilter = match($this->dateRangeFilter) {
                    'week' => now()->subWeek(),
                    'month' => now()->subMonth(),
                    'quarter' => now()->subQuarter(),
                    'year' => now()->subYear(),
                    default => now()->subMonth(),
                };
                return $query->where('created_at', '>=', $dateFilter);
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($record) {
                $oldValues = $record->old_values ?? [];
                $newValues = $record->new_values ?? [];
                
                $changes = [];
                foreach (['temp_password_changed', 'is_locked', 'department_id'] as $field) {
                    if (isset($oldValues[$field]) && isset($newValues[$field]) && $oldValues[$field] !== $newValues[$field]) {
                        $changes[] = ucfirst(str_replace('_', ' ', $field)) . ' changed';
                    }
                }
                
                return [
                    'id' => $record->id,
                    'type' => 'status_change',
                    'title' => 'Status Change',
                    'description' => implode(', ', $changes),
                    'date' => $record->created_at->toDateString(),
                    'time' => $record->created_at->toTimeString(),
                    'status' => 'system',
                    'icon' => 'heroicono-cog',
                    'color' => 'gray',
                ];
            });

        // Merge and sort all events
        $allEvents = collect([$attendance, $contributions, $education, $guardians, $groups, $tourParticipation, $statusChanges])
            ->flatten()
            ->sortByDesc('date')
            ->values()
            ->all();

        return $allEvents;
    }

    public function getEventIcon(string $type, string $status): string
    {
        $icons = [
            'attendance' => [
                'Present' => 'heroicon-o-check-circle',
                'Absent' => 'heroicon-o-x-circle',
                'Late' => 'heroicon-o-clock',
                'Excused' => 'heroicon-o-shield-check',
                'Permission' => 'heroicon-o-document-text',
            ],
            'contribution' => 'heroicono-banknotes',
            'education' => 'heroicono-academic-cap',
            'guardian' => 'heroicono-users',
            'group' => 'heroicono-user-group',
            'status_change' => 'heroicono-cog',
            'tour' => 'heroicono-map',
        ];

        return $icons[$type][$status] ?? $icons[$type] ?? 'heroicono-information-circle';
    }

    public function getEventColor(string $type, string $status): string
    {
        $colors = [
            'attendance' => [
                'Present' => 'success',
                'Absent' => 'danger',
                'Late' => 'warning',
                'Excused' => 'info',
                'Permission' => 'gray',
            ],
            'contribution' => 'primary',
            'education' => 'info',
            'guardian' => 'warning',
            'group' => 'secondary',
            'status_change' => 'gray',
            'tour' => 'primary',
        ];

        return $colors[$type][$status] ?? $colors[$type] ?? 'gray';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // This would be used if we wanted to show timeline events in a table
                // For now, we're using the custom timeline view
                Member::query()->where('id', $this->record->id)
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->label('Event Type')
                    ->options([
                        'all' => 'All Events',
                        'attendance' => 'Attendance',
                        'contribution' => 'Contributions',
                        'education' => 'Education',
                        'guardian' => 'Guardians',
                        'group' => 'Groups',
                        'status_change' => 'Status Changes',
                        'tour' => 'Tour Participation',
                    ])
                    ->default('all'),
                
                Tables\Filters\SelectFilter::make('date_range')
                    ->label('Date Range')
                    ->options([
                        'all' => 'All Time',
                        'week' => 'Last Week',
                        'month' => 'Last Month',
                        'quarter' => 'Last Quarter',
                        'year' => 'Last Year',
                    ])
                    ->default('all'),
            ]);
    }
}

